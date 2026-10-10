<?php

namespace Database\Seeders;

use App\Enums\PaymentMethod;
use App\Enums\ResidentStatus;
use App\Models\ChargeItem;
use App\Models\DailyCharge;
use App\Models\Facility;
use App\Models\MonthlyInvoice;
use App\Models\Resident;
use App\Models\User;
use App\Services\InvoiceCalculationService;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class FacilityBillingSeeder extends Seeder
{
    public function run(): void
    {
        // 0. 管理者ユーザーの作成
        User::updateOrCreate(
            ['email' => 'admin@care-himawari.example.jp'],
            [
                'name' => '施設管理者',
                'password' => Hash::make('password'),
                'is_admin' => true,
            ]
        );

        // 1. デフォルト施設を取得または作成
        $facility = Facility::first();
        if (! $facility) {
            $facility = Facility::create([
                'name' => 'ケアレジデンス ひまわり',
                'operator' => '株式会社ひまわりケア',
                'postal_code' => '123-4567',
                'address' => '東京都〇〇区〇〇町 1-2-3',
                'phone' => '03-1234-5678',
                'fax' => '03-1234-5679',
                'email' => 'info@care-himawari.example.jp',
                'invoice_registration_number' => 'T1234567890123',
                'bank' => [
                    'name' => '〇〇銀行',
                    'branch_name' => '〇〇支店',
                    'account_type' => '普通',
                    'account_number' => '1234567',
                    'account_holder' => 'カ）ヒマワリケア',
                ],
                'billing' => [
                    'direct_debit_day' => 27,
                    'bank_transfer_due_days' => 30,
                ],
                'is_active' => true,
            ]);
        }

        // ファシリティ情報が正しく設定されているか確認
        if (empty($facility->bank['name']) || empty($facility->bank['account_number']) || empty($facility->bank['account_holder'])) {
            // データが不正な場合は再作成
            $facility->update([
                'bank' => [
                    'name' => '〇〇銀行',
                    'branch_name' => '〇〇支店',
                    'account_type' => '普通',
                    'account_number' => '1234567',
                    'account_holder' => 'カ）ヒマワリケア',
                ],
            ]);
        }

        // 2. 自費サービス品目マスタの登録
        $items = [
            ['name' => '紙おむつ (パンツタイプ)', 'default_price' => 180],
            ['name' => '尿とりパッド', 'default_price' => 70],
            ['name' => '理美容代 (カット)', 'default_price' => 2200],
            ['name' => '理美容代 (カラー・パーマ)', 'default_price' => 4500],
            ['name' => '受診付き添い費 (30分)', 'default_price' => 1500],
            ['name' => '個別洗濯代行 (1回)', 'default_price' => 500],
            ['name' => '日用品・嗜好品立替金', 'default_price' => 0], // 都度入力
        ];

        $createdItems = [];
        foreach ($items as $item) {
            $createdItems[] = ChargeItem::updateOrCreate(['name' => $item['name']], $item);
        }

        // 3. 入居者25名分の登録 (定員25名施設、入居日設定)
        $sampleResidents = [
            ['101', '佐藤 一郎', 'サトウ イチロウ', 65000, 30000],
            ['102', '鈴木 ハナ', 'スズキ ハナ', 60000, 30000],
            ['103', '高橋 健二', 'タカハシ ケンジ', 65000, 30000],
            ['104', '田中 トメ', 'タナカ トメ', 60000, 30000],
            ['105', '伊藤 清', 'イトウ キヨシ', 70000, 35000],
            ['106', '渡辺 チヨ', 'ワタナベ チヨ', 65000, 30000],
            ['107', '山本 勇', 'ヤマモト イサム', 60000, 30000],
            ['108', '中村 マツ', 'ナカムラ マツ', 65000, 30000],
            ['109', '小林 義雄', 'コバヤシ ヨシオ', 70000, 35000],
            ['110', '加藤 シズ', 'カトウ シズ', 60000, 30000],
            ['201', '吉田 繁', 'ヨシダ シゲル', 68000, 30000],
            ['202', '山田 敏子', 'ヤマダ トシコ', 65000, 30000],
            ['203', '佐々木 正', 'ササキ タダシ', 68000, 30000],
            ['204', '山口 キク', 'ヤマグチ キク', 60000, 30000],
            ['205', '松本 修', 'マツモト オサム', 72000, 35000],
            ['206', '井上 フミ', 'イノウエ フミ', 65000, 30000],
            ['207', '木村 喜代次', 'キムラ キヨジ', 65000, 30000],
            ['208', '林 ミヨ', 'ハヤシ ミヨ', 60000, 30000],
            ['209', '斎藤 忠雄', 'サイトウ タダオ', 70000, 35000],
            ['210', '清水 サヨ', 'シミズ サヨ', 65000, 30000],
            ['211', '山崎 武', 'ヤマザキ タケシ', 68000, 30000],
            ['212', '森 コト', 'モリ コト', 60000, 30000],
            ['213', '阿部 浩', 'アベ ヒロシ', 65000, 30000],
            ['214', '池田 スミ', 'イケダ スミ', 65000, 30000],
            ['215', '橋本 昭', 'ハシモト アキラ', 70000, 35000],
        ];

        $residentModels = [];
        foreach ($sampleResidents as [$room, $name, $kana, $rent, $fee]) {
            $residentModels[] = Resident::updateOrCreate(
                ['room_number' => $room],
                [
                    'facility_id' => $facility->id,
                    'name' => $name,
                    'name_kana' => $kana,
                    'base_rent' => $rent,
                    'base_management_fee' => $fee,
                    'status' => ResidentStatus::Active,
                    'move_in_date' => '2025-04-01',
                    'move_out_date' => null,
                ]
            );
        }

        // 4. 当月（今月）の自費利用記録サンプル
        $currentMonth = Carbon::now()->format('Y-m');
        $today = Carbon::today();

        foreach ($residentModels as $index => $resident) {
            if ($index % 2 === 0) {
                DailyCharge::create([
                    'resident_id' => $resident->id,
                    'charge_item_id' => $createdItems[0]->id,
                    'date' => $today->copy()->subDays(rand(1, 10))->toDateString(),
                    'unit_price' => $createdItems[0]->default_price,
                    'quantity' => rand(2, 5),
                    'note' => '夜間交換分など',
                ]);
            }

            if ($index % 5 === 0) {
                DailyCharge::create([
                    'resident_id' => $resident->id,
                    'charge_item_id' => $createdItems[2]->id,
                    'date' => $today->copy()->subDays(rand(3, 15))->toDateString(),
                    'unit_price' => $createdItems[2]->default_price,
                    'quantity' => 1,
                    'note' => '訪問理美容',
                ]);
            }
        }

        // 5. 今月の請求書を一括集計生成（施設スコープ付き）
        $service = app()->make(InvoiceCalculationService::class);
        $service->generateForMonth($currentMonth, false, $facility->id);

        // 6. 先月分の請求データを作成し、一部を入金済みに設定（領収書検証用）
        $lastMonth = Carbon::now()->subMonth()->format('Y-m');
        $service->generateForMonth($lastMonth, false, $facility->id);

        $lastMonthInvoices = MonthlyInvoice::forYearMonth($lastMonth)
            ->whereHas('resident', function ($q) use ($facility) {
                $q->where('facility_id', $facility->id);
            })
            ->take(5)
            ->get();
        foreach ($lastMonthInvoices as $inv) {
            $inv->markAsPaid(PaymentMethod::DirectDebit, Carbon::now()->subDays(5)->toDateString());
        }
    }
}
