<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

class EncryptBankAccountsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'resident:encrypt-bank-accounts';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Encrypt plain bank account records in resident_bank_accounts table';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Starting encryption of resident bank accounts...');

        $records = DB::table('resident_bank_accounts')->get();
        $encryptedCount = 0;
        $skippedCount = 0;

        foreach ($records as $record) {
            $isAlreadyEncrypted = false;
            try {
                // すでに暗号化されているかテスト復号
                if (!empty($record->account_number)) {
                    Crypt::decryptString($record->account_number);
                    $isAlreadyEncrypted = true;
                }
            } catch (\Throwable $e) {
                $isAlreadyEncrypted = false;
            }

            if ($isAlreadyEncrypted) {
                $skippedCount++;
                continue;
            }

            // 平文レコードを暗号化して更新
            DB::table('resident_bank_accounts')
                ->where('id', $record->id)
                ->update([
                    'bank_code' => !empty($record->bank_code) ? Crypt::encryptString($record->bank_code) : $record->bank_code,
                    'branch_code' => !empty($record->branch_code) ? Crypt::encryptString($record->branch_code) : $record->branch_code,
                    'account_number' => !empty($record->account_number) ? Crypt::encryptString($record->account_number) : $record->account_number,
                    'account_holder_kana' => !empty($record->account_holder_kana) ? Crypt::encryptString($record->account_holder_kana) : $record->account_holder_kana,
                    'updated_at' => now(),
                ]);

            $encryptedCount++;
        }

        $this->info("Completed. Encrypted: {$encryptedCount}, Skipped: {$skippedCount}");

        return self::SUCCESS;
    }
}
