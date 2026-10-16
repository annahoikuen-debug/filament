<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Auth\TwoFactorAuthService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Tests\TestCase;

class TwoFactorAuthSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_two_factor_auth_service_generates_and_validates_totp_code(): void
    {
        $service = new TwoFactorAuthService();
        $secret = $service->generateSecretKey();

        $this->assertEquals(32, strlen($secret));

        // 現在時刻のTOTPコードをシミュレーション計算して検証
        $timeSlice = (int) floor(time() / 30);
        $method = new \ReflectionMethod(TwoFactorAuthService::class, 'calculateTotp');
        $code = $method->invoke($service, $secret, $timeSlice);

        $this->assertEquals(6, strlen($code));
        $this->assertTrue($service->verifyCode($secret, $code));

        // 不正なコードは不合格
        $this->assertFalse($service->verifyCode($secret, '999999'));
    }

    public function test_recovery_codes_can_be_consumed_only_once(): void
    {
        $service = new TwoFactorAuthService();
        $recoveryCodes = $service->generateRecoveryCodes();

        $this->assertCount(8, $recoveryCodes);

        $user = User::factory()->create([
            'two_factor_recovery_codes' => Crypt::encryptString(json_encode($recoveryCodes)),
            'two_factor_confirmed_at' => now(),
        ]);

        $codeToUse = $recoveryCodes[0];

        // 1回目の使用: 成功
        $this->assertTrue($service->verifyAndConsumeRecoveryCode($user, $codeToUse));

        // 2回目の使用: すでに消費されているため失敗
        $this->assertFalse($service->verifyAndConsumeRecoveryCode($user->fresh(), $codeToUse));

        // 残りコード数が7件になっていること
        $remaining = json_decode(Crypt::decryptString($user->fresh()->two_factor_recovery_codes), true);
        $this->assertCount(7, $remaining);
    }
}
