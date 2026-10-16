<?php

namespace Tests\Unit;

use App\Models\Facility;
use App\Models\Resident;
use App\Models\ResidentBankAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ResidentBankAccountEncryptionTest extends TestCase
{
    use RefreshDatabase;

    public function test_bank_account_fields_are_encrypted_in_database_and_decrypted_on_access(): void
    {
        $facility = Facility::factory()->create();
        $resident = Resident::factory()->create(['facility_id' => $facility->id]);

        $account = ResidentBankAccount::create([
            'resident_id' => $resident->id,
            'bank_code' => '0001',
            'branch_code' => '123',
            'account_type' => ResidentBankAccount::ACCOUNT_TYPE_ORDINARY,
            'account_number' => '1234567',
            'account_holder_kana' => 'ヤマダタロウ',
            'delegate_code' => '1234567890',
            'is_active' => true,
        ]);

        // 1. Raw DB レコードを直接取得し、平文でなく暗号化（base64/JSON構造）されていることを検証
        $rawRecord = DB::table('resident_bank_accounts')->where('id', $account->id)->first();

        $this->assertNotEquals('0001', $rawRecord->bank_code);
        $this->assertNotEquals('123', $rawRecord->branch_code);
        $this->assertNotEquals('1234567', $rawRecord->account_number);
        $this->assertNotEquals('ヤマダタロウ', $rawRecord->account_holder_kana);

        // 暗号化文字列形式（LaravelのencrypterによるBase64ペイロード）であることの確認
        $this->assertStringStartsWith('eyJ', $rawRecord->account_number);

        // 2. Eloquent モデル経由で取得したときに正しく平文に復号されることを検証
        $fresh = ResidentBankAccount::find($account->id);

        $this->assertEquals('0001', $fresh->bank_code);
        $this->assertEquals('123', $fresh->branch_code);
        $this->assertEquals('1234567', $fresh->account_number);
        $this->assertEquals('ヤマダタロウ', $fresh->account_holder_kana);
    }
}
