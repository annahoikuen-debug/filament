<?php

namespace App\Services\Auth;

use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;

class TwoFactorAuthService
{
    /**
     * 新しいBase32 TOTP シークレットキーを生成
     */
    public function generateSecretKey(): string
    {
        $validChars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $secret = '';
        for ($i = 0; $i < 32; $i++) {
            $secret .= $validChars[random_int(0, 31)];
        }
        return $secret;
    }

    /**
     * 1回限り有効なリカバリーコードを生成（8組）
     *
     * @return array<int, string>
     */
    public function generateRecoveryCodes(): array
    {
        return Collection::times(8, function () {
            return Str::random(10) . '-' . Str::random(10);
        })->all();
    }

    /**
     * TOTP コードを検証（時間窓 ±1 = 前後30秒）
     */
    public function verifyCode(string $secret, string $code): bool
    {
        $code = trim($code);
        if (strlen($code) !== 6 || !ctype_digit($code)) {
            return false;
        }

        $currentTimeSlice = (int) floor(time() / 30);

        for ($i = -1; $i <= 1; $i++) {
            $calculatedCode = $this->calculateTotp($secret, $currentTimeSlice + $i);
            if (hash_equals($calculatedCode, $code)) {
                return true;
            }
        }

        return false;
    }

    /**
     * リカバリーコードで認証し、使用済みコードを消費
     */
    public function verifyAndConsumeRecoveryCode(User $user, string $code): bool
    {
        if (empty($user->two_factor_recovery_codes)) {
            return false;
        }

        try {
            $codes = json_decode(Crypt::decryptString($user->two_factor_recovery_codes), true);
        } catch (\Throwable $e) {
            return false;
        }

        if (!is_array($codes)) {
            return false;
        }

        foreach ($codes as $key => $storedCode) {
            if (hash_equals($storedCode, trim($code))) {
                unset($codes[$key]);
                $user->two_factor_recovery_codes = Crypt::encryptString(json_encode(array_values($codes)));
                $user->save();
                return true;
            }
        }

        return false;
    }

    /**
     * RFC 6238 TOTP 計算
     */
    protected function calculateTotp(string $secret, int $timeSlice): string
    {
        $secretKey = $this->base32Decode($secret);
        $timeBytes = pack('N*', 0) . pack('N*', $timeSlice);

        $hmac = hash_hmac('sha1', $timeBytes, $secretKey, true);
        $offset = ord(substr($hmac, -1)) & 0x0F;

        $unpacked = unpack('N', substr($hmac, $offset, 4));
        $value = $unpacked[1] & 0x7FFFFFFF;

        return str_pad((string) ($value % 1000000), 6, '0', STR_PAD_LEFT);
    }

    /**
     * Base32 デコード
     */
    protected function base32Decode(string $base32): string
    {
        $base32 = strtoupper($base32);
        $validChars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $buffer = 0;
        $bitsLeft = 0;
        $output = '';

        for ($i = 0; $i < strlen($base32); $i++) {
            $char = $base32[$i];
            $val = strpos($validChars, $char);
            if ($val === false) {
                continue;
            }

            $buffer = ($buffer << 5) | $val;
            $bitsLeft += 5;

            if ($bitsLeft >= 8) {
                $bitsLeft -= 8;
                $output .= chr(($buffer >> $bitsLeft) & 0xFF);
            }
        }

        return $output;
    }
}
