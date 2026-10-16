<?php

namespace Tests\Feature;

use App\Models\Facility;
use App\Models\Resident;
use App\Models\ResidentBankAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class EncryptBankAccountsCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_encrypts_plain_records_idempotently(): void
    {
        $facility = Facility::factory()->create();
        $resident = Resident::factory()->create(['facility_id' => $facility->id]);

        // 直接 DB に平文で挿入
        $id = DB::table('resident_bank_accounts')->insertGetId([
            'resident_id' => $resident->id,
            'bank_code' => '0005',
            'branch_code' => '012',
            'account_type' => 1,
            'account_number' => '9876543',
            'account_holder_kana' => 'スズキイチロウ',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // コマンド実行（1回目）
        $exitCode = Artisan::call('resident:encrypt-bank-accounts');
        $this->assertEquals(0, $exitCode);

        // DB 内で暗号化されたことを検証
        $raw = DB::table('resident_bank_accounts')->where('id', $id)->first();
        $this->assertNotEquals('9876543', $raw->account_number);

        // モデル経由で取得できることを検証
        $model = ResidentBankAccount::find($id);
        $this->assertEquals('9876543', $model->account_number);
        $this->assertEquals('スズキイチロウ', $model->account_holder_kana);

        // コマンド実行（2回目：スキップされるべき・二重暗号化で壊れないこと）
        $exitCode2 = Artisan::call('resident:encrypt-bank-accounts');
        $this->assertEquals(0, $exitCode2);

        $modelAgain = ResidentBankAccount::find($id);
        $this->assertEquals('9876543', $modelAgain->account_number);
        $this->assertEquals('スズキイチロウ', $modelAgain->account_holder_kana);
    }
}
