<?php

namespace Database\Seeders;

use App\Models\AccountingExportProfile;
use App\Models\ChartOfAccount;
use App\Models\Facility;
use Illuminate\Database\Seeder;

class AccountingExportSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $facilities = Facility::all();

        foreach ($facilities as $facility) {
            // デフォルト勘定科目マスタ作成
            ChartOfAccount::createDefaultsForFacility($facility->id);

            // デフォルト会計エクスポートプロファイル作成
            AccountingExportProfile::createDefaultsForFacility($facility->id);
        }

        $this->command?->info('会計連携用デフォルトデータを作成しました。');
    }
}