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

class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        // 1. 法人管理者ユーザー作成
        User::updateOrCreate(
            ['email' => 'corporate@demo.example.jp'],
            [
                'name' => '法人管理者',
                'password' => Hash::make('password'),
                'is_admin' => true,
                'role' => 'corporate_admin',
                'facility_id' => null,
            ]
        );

        // 2. 3つの施設を作成
        $facilities = [];

        // 施設A: ケアレジデンス ひまわり
        $facilities[] = Facility::updateOrCreate(
            ['name' => 'ケアレジデンス ひまわり'],
            [
                'operator' => '株式会社ひまわりケア',
                'postal_code' => '123-4567',
                'address' => '東京都新宿区西新宿 1-2-3',
                'phone' => '03-1234-5678',
                'fax' => '03-1234-5679',
                'email' => 'info@himawari.example.jp',
                'invoice_registration_number' => 'T1234567890123',
                'bank' => [
                    'name' => '三菱UFJ銀行',
                    'branch_name' => '新宿支店',
                    'account_type' => '普通',
                    'account_number' => '1234567',
                    'account_holder' => 'カ）ヒマワリケア',
                ],
                'billing' => [
                    'direct_debit_day' => 27,
                    'bank_transfer_due_days' => 30,
                ],
                'is_active' => true,
            ]
        );

        // 施設B: さくらケアホーム
        $facilities[] = Facility::updateOrCreate(
            ['name' => 'さくらケアホーム'],
            [
                'operator' => '医療法人桜会',
                'postal_code' => '231-0012',
                'address' => '神奈川県横浜市中区桜木町 2-3-4',
                'phone' => '045-234-5678',
                'fax' => '045-234-5679',
                'email' => 'info@sakura-care.example.jp',
                'invoice_registration_number' => 'T2345678901234',
                'bank' => [
                    'name' => '横浜銀行',
                    'branch_name' => '桜木町支店',
                    'account_type' => '普通',
                    'account_number' => '2345678',
                    'account_holder' => 'イリョウホウジンサクラカイ',
                ],
                'billing' => [
                    'direct_debit_day' => 25,
                    'bank_transfer_due_days' => 30,
                ],
                'is_active' => true,
            ]
        );

        // 施設C: もみじガーデン
        $facilities[] = Facility::updateOrCreate(
            ['name' => 'もみじガーデン'],
            [
                'operator' => '社会福祉法人紅葉福祉会',
                'postal_code' => '150-0001',
                'address' => '東京都渋谷区神宮前 3-4-5',
                'phone' => '03-3456-7890',
                'fax' => '03-3456-7891',
                'email' => 'info@momiji-garden.example.jp',
                'invoice_registration_number' => 'T3456789012345',
                'bank' => [
                    'name' => 'みずほ銀行',
                    'branch_name' => '渋谷支店',
                    'account_type' => '普通',
                    'account_number' => '3456789',
                    'account_holder' => 'シャカイフクシホウジンモミジフクシカイ',
                ],
                'billing' => [
                    'direct_debit_day' => 26,
                    'bank_transfer_due_days' => 30,
                ],
                'is_active' => true,
            ]
        );

        // 施設管理者ユーザー作成（各施設1名）
        foreach ($facilities as $index => $facility) {
            User::updateOrCreate(
                ['email' => "facility{$index}@demo.example.jp"],
                [
                    'name' => "{$facility->name} 管理者",
                    'password' => Hash::make('password'),
                    'is_admin' => true,
                    'role' => 'facility_admin',
                    'facility_id' => $facility->id,
                ]
            );
        }

        // 3. 自費サービス品目マスタの登録（全施設共通）
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

        // 4. 入居者30名分の登録（各施設10名ずつ）
        $allResidents = [];
        $baseDate = '2025-04-01';

        // 施設Aの入居者（10名）
        $facilityAResidents = [
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
        ];

        foreach ($facilityAResidents as [$room, $name, $kana, $rent, $fee]) {
            $allResidents[] = Resident::updateOrCreate(
                ['room_number' => $room, 'facility_id' => $facilities[0]->id],
                [
                    'facility_id' => $facilities[0]->id,
                    'name' => $name,
                    'name_kana' => $kana,
                    'base_rent' => $rent,
                    'base_management_fee' => $fee,
                    'status' => ResidentStatus::Active,
                    'move_in_date' => $baseDate,
                    'move_out_date' => null,
                ]
            );
        }

        // 施設Bの入居者（10名）
        $facilityBResidents = [
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
        ];

        foreach ($facilityBResidents as [$room, $name, $kana, $rent, $fee]) {
            $allResidents[] = Resident::updateOrCreate(
                ['room_number' => $room, 'facility_id' => $facilities[1]->id],
                [
                    'facility_id' => $facilities[1]->id,
                    'name' => $name,
                    'name_kana' => $kana,
                    'base_rent' => $rent,
                    'base_management_fee' => $fee,
                    'status' => ResidentStatus::Active,
                    'move_in_date' => $baseDate,
                    'move_out_date' => null,
                ]
            );
        }

        // 施設Cの入居者（10名）
        $facilityCResidents = [
            ['301', '山崎 武', 'ヤマザキ タケシ', 68000, 30000],
            ['302', '森 コト', 'モリ コト', 60000, 30000],
            ['303', '阿部 浩', 'アベ ヒロシ', 65000, 30000],
            ['304', '池田 スミ', 'イケダ スミ', 65000, 30000],
            ['305', '橋本 昭', 'ハシモト アキラ', 70000, 35000],
            ['306', '石川 ヒサ', 'イシカワ ヒサ', 68000, 30000],
            ['307', '前田 義男', 'マエダ ヨシオ', 65000, 30000],
            ['308', '藤田 ヨシ', 'フジタ ヨシ', 60000, 30000],
            ['309', '小川 晴夫', 'オガワ ハルオ', 72000, 35000],
            ['310', '岡田 ミチ', 'オカダ ミチ', 65000, 30000],
        ];

        foreach ($facilityCResidents as [$room, $name, $kana, $rent, $fee]) {
            $allResidents[] = Resident::updateOrCreate(
                ['room_number' => $room, 'facility_id' => $facilities[2]->id],
                [
                    'facility_id' => $facilities[2]->id,
                    'name' => $name,
                    'name_kana' => $kana,
                    'base_rent' => $rent,
                    'base_management_fee' => $fee,
                    'status' => ResidentStatus::Active,
                    'move_in_date' => $baseDate,
                    'move_out_date' => null,
                ]
            );
        }

        // 5. 過去3ヶ月分の自費利用記録サンプルを生成
        $currentMonth = Carbon::now()->format('Y-m');
        $today = Carbon::today();

        for ($monthOffset = 0; $monthOffset < 3; $monthOffset++) {
            $targetMonth = Carbon::now()->subMonths($monthOffset);
            $daysInMonth = $targetMonth->daysInMonth;
            $monthStart = $targetMonth->copy()->startOfMonth();
            $monthEnd = $targetMonth->copy()->endOfMonth();

            foreach ($allResidents as $index => $resident) {
                // おむつ使用（偶数インデックスの入居者）
                if ($index % 2 === 0) {
                    DailyCharge::updateOrCreate(
                        [
                            'resident_id' => $resident->id,
                            'charge_item_id' => $createdItems[0]->id,
                            'date' => $monthStart->copy()->addDays(rand(1, $daysInMonth - 1))->toDateString(),
                        ],
                        [
                            'facility_id' => $resident->facility_id,
                            'unit_price' => $createdItems[0]->default_price,
                            'quantity' => rand(2, 6),
                            'note' => '夜間交換分など',
                        ]
                    );
                }

                // 尿取りパッド（3の倍数インデックス）
                if ($index % 3 === 0) {
                    DailyCharge::updateOrCreate(
                        [
                            'resident_id' => $resident->id,
                            'charge_item_id' => $createdItems[1]->id,
                            'date' => $monthStart->copy()->addDays(rand(1, $daysInMonth - 1))->toDateString(),
                        ],
                        [
                            'facility_id' => $resident->facility_id,
                            'unit_price' => $createdItems[1]->default_price,
                            'quantity' => rand(10, 30),
                            'note' => '1日分',
                        ]
                    );
                }

                // 理美容（5の倍数インデックス）
                if ($index % 5 === 0) {
                    DailyCharge::updateOrCreate(
                        [
                            'resident_id' => $resident->id,
                            'charge_item_id' => $createdItems[2]->id,
                            'date' => $monthStart->copy()->addDays(rand(1, $daysInMonth - 1))->toDateString(),
                        ],
                        [
                            'facility_id' => $resident->facility_id,
                            'unit_price' => $createdItems[2]->default_price,
                            'quantity' => 1,
                            'note' => '訪問理美容',
                        ]
                    );
                }

                // 受診付き添い（7の倍数インデックス）
                if ($index % 7 === 0) {
                    DailyCharge::updateOrCreate(
                        [
                            'resident_id' => $resident->id,
                            'charge_item_id' => $createdItems[4]->id,
                            'date' => $monthStart->copy()->addDays(rand(1, $daysInMonth - 1))->toDateString(),
                        ],
                        [
                            'facility_id' => $resident->facility_id,
                            'unit_price' => $createdItems[4]->default_price,
                            'quantity' => rand(1, 2),
                            'note' => '通院付き添い',
                        ]
                    );
                }
            }
        }

        // 6. 過去3ヶ月分の請求書を一括集計生成
        $service = app()->make(InvoiceCalculationService::class);
        for ($monthOffset = 0; $monthOffset < 3; $monthOffset++) {
            $targetMonth = Carbon::now()->subMonths($monthOffset)->format('Y-m');
            foreach ($facilities as $facility) {
                $service->generateForMonth($targetMonth, false, $facility->id);
            }
        }

        // 7. 先月分の請求データのうち、施設Aの5件を入金済みに設定（領収書検証用）
        $lastMonth = Carbon::now()->subMonth()->format('Y-m');
        $lastMonthInvoicesA = MonthlyInvoice::forYearMonth($lastMonth)
            ->whereHas('resident', function ($q) use ($facilities) {
                $q->where('facility_id', $facilities[0]->id);
            })
            ->take(5)
            ->get();
        foreach ($lastMonthInvoicesA as $inv) {
            $inv->markAsPaid(PaymentMethod::DirectDebit, Carbon::now()->subDays(5)->toDateString());
        }

        // 8. 2ヶ月前の請求データのうち、施設Bの3件を入金済みに設定
        $twoMonthsAgo = Carbon::now()->subMonths(2)->format('Y-m');
        $twoMonthsAgoInvoicesB = MonthlyInvoice::forYearMonth($twoMonthsAgo)
            ->whereHas('resident', function ($q) use ($facilities) {
                $q->where('facility_id', $facilities[1]->id);
            })
            ->take(3)
            ->get();
        foreach ($twoMonthsAgoInvoicesB as $inv) {
            $inv->markAsPaid(PaymentMethod::BankTransfer, Carbon::now()->subDays(10)->toDateString());
        }

        $this->command->info('Demo data seeded successfully!');
        $this->command->info('Facilities: 3');
        $this->command->info('Residents: 30 (10 per facility)');
        $this->command->info('Charge Items: 7');
        $this->command->info('Daily Charges: ~100 records across 3 months');
        $this->command->info('Monthly Invoices: ~90 records (3 months × 3 facilities × 10 residents)');
        $this->command->info('Users: 1 corporate_admin + 3 facility_admin');
    }
}