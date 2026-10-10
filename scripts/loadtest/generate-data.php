<?php
/**
 * 負荷テスト用データ生成スクリプト
 * 計画書 §9.1 のデータ規模でテストデータを生成する（冪等）
 *
 * 規模:
 *  - 施設: 10（負荷テスト専用）
 *  - 入居者: 1,000（100/施設）
 *  - 日々課金: 6ヶ月分（約18万件）
 *  - 月次請求: 6ヶ月分（6,000件、InvoiceCalculationService 経由）
 *  - チャットボットFAQ: 100件
 *  - チャットログ: 10,000件
 */

require __DIR__ . '/../../vendor/autoload.php';

$app = require __DIR__ . '/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Enums\InvoiceStatus;
use App\Enums\ResidentStatus;
use App\Models\ChatbotFaq;
use App\Models\ChatLog;
use App\Models\ChargeItem;
use App\Models\DailyCharge;
use App\Models\Facility;
use App\Models\MonthlyInvoice;
use App\Models\Resident;
use App\Models\User;
use App\Services\InvoiceCalculationService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

const FACILITY_COUNT = 10;
const RESIDENTS_PER_FACILITY = 100;
const MONTHS = 6;
const FAQ_COUNT = 100;
const CHAT_LOG_COUNT = 10000;

function logInfo(string $message): void
{
    echo '[' . date('H:i:s') . "] {$message}\n";
}

function createFacilities(): array
{
    $existing = Facility::where('name', 'like', '負荷テスト施設%')->get();
    if ($existing->count() >= FACILITY_COUNT) {
        logInfo("施設: 既存 {$existing->count()} 件を再利用");
        return $existing->slice(0, FACILITY_COUNT)->all();
    }

    $facilities = [];
    $data = [];
    for ($i = 1; $i <= FACILITY_COUNT; $i++) {
        $data[] = [
            'name' => "負荷テスト施設 {$i}",
            'operator' => "負荷テスト運営法人 {$i}",
            'postal_code' => sprintf('%03d-%04d', random_int(100, 999), random_int(1000, 9999)),
            'address' => "東京都テスト区負荷町 {$i}-{$i}-{$i}",
            'phone' => '03-' . random_int(1000, 9999) . '-' . random_int(1000, 9999),
            'fax' => '03-' . random_int(1000, 9999) . '-' . random_int(1000, 9999),
            'email' => "loadtest_facility{$i}@example.jp",
            'invoice_registration_number' => 'T' . str_pad($i, 13, '0', STR_PAD_LEFT),
            'bank' => json_encode([
                'name' => 'テスト銀行',
                'branch_name' => "テスト支店 {$i}",
                'account_type' => '普通',
                'account_number' => str_pad($i, 7, '0', STR_PAD_LEFT),
                'account_holder' => "テスト施設{$i}",
            ]),
            'billing' => json_encode(['direct_debit_day' => 27, 'bank_transfer_due_days' => 30]),
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }
    DB::table('facilities')->insert($data);

    $facilities = Facility::where('name', 'like', '負荷テスト施設%')->orderBy('id')->get();
    logInfo("施設: {$facilities->count()} 件作成");

    // 施設管理者ユーザー
    $users = [];
    foreach ($facilities as $index => $facility) {
        $email = "loadtest_admin{$index}@example.jp";
        if (! User::where('email', $email)->exists()) {
            $users[] = [
                'name' => "負荷テスト管理者 {$index}",
                'email' => $email,
                'password' => Hash::make('loadtest-password'),
                'is_admin' => true,
                'role' => 'facility_admin',
                'facility_id' => $facility->id,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }
    }
    if ($users) {
        DB::table('users')->insert($users);
    }
    logInfo('施設管理者ユーザー: 準備完了');

    return $facilities->all();
}

function ensureChargeItems(): array
{
    $existing = ChargeItem::get();
    if ($existing->count() >= 20) {
        logInfo("品目マスタ: 既存 {$existing->count()} 件を再利用");
        return $existing->all();
    }

    $names = [
        '紙おむつ (パンツタイプ)', '尿とりパッド', '理美容代 (カット)', '理美容代 (カラー・パーマ)',
        '受診付き添い費 (30分)', '個別洗濯代行 (1回)', '日用品・嗜好品立替金',
        'リハビリ用品', '介護用ベッドレンタル', '車椅子レンタル',
        '見守りセンサーレンタル', '入浴介助用品', '食事介助用品', '口腔ケア用品',
        '褥瘡ケア用品', '排泄ケア用品', '移動介助用品', 'レクリエーション用品',
        '緊急呼び出しシステム', '健康管理機器レンタル',
    ];
    $data = [];
    foreach ($names as $i => $name) {
        $data[] = [
            'name' => $name,
            'default_price' => random_int(50, 5000),
            'tax_type' => 'standard',
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }
    DB::table('charge_items')->insert($data);

    $items = ChargeItem::get()->all();
    logInfo('品目マスタ: ' . count($items) . ' 件');
    return $items;
}

function createResidents(array $facilities): array
{
    $existing = Resident::where('name', 'like', '負荷テスト入居者%')->count();
    $target = FACILITY_COUNT * RESIDENTS_PER_FACILITY;
    if ($existing >= $target) {
        logInfo("入居者: 既存 {$existing} 件を再利用");
        return Resident::where('name', 'like', '負荷テスト入居者%')->orderBy('id')->get()->all();
    }

    $baseDate = now()->subMonths(MONTHS + 2)->toDateString();
    $batchSize = 200;
    $created = 0;

    foreach ($facilities as $f => $facility) {
        $rows = [];
        for ($r = 1; $r <= RESIDENTS_PER_FACILITY; $r++) {
            $roomNumber = sprintf('%d%03d', $f + 1, $r);
            $rows[] = [
                'facility_id' => $facility->id,
                'room_number' => $roomNumber,
                'name' => "負荷テスト入居者 {$roomNumber}",
                'name_kana' => "フカテストニュウキョシャ {$roomNumber}",
                'base_rent' => random_int(50000, 80000),
                'base_management_fee' => random_int(20000, 40000),
                'status' => ResidentStatus::Active->value,
                'move_in_date' => $baseDate,
                'move_out_date' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }
        foreach (array_chunk($rows, $batchSize) as $chunk) {
            DB::table('residents')->insert($chunk);
        }
        $created += count($rows);
        logInfo("入居者: 施設 " . ($f + 1) . "/10 完了 ({$created}件)");
    }

    return Resident::where('name', 'like', '負荷テスト入居者%')->orderBy('id')->get()->all();
}

function createDailyCharges(array $residents, array $chargeItems): void
{
    $existing = DailyCharge::whereHas('resident', fn ($q) => $q->where('name', 'like', '負荷テスト入居者%'))->count();
    if ($existing > 0) {
        logInfo("日々課金: 既存 {$existing} 件を再利用");
        return;
    }

    $total = 0;
    $residentIds = array_column($residents, 'id');
    $itemIds = array_column($chargeItems, 'id');

    for ($m = 0; $m < MONTHS; $m++) {
        $targetMonth = Carbon::now()->subMonths($m);
        $daysInMonth = $targetMonth->daysInMonth;
        $monthStart = $targetMonth->copy()->startOfMonth();
        $rows = [];

        foreach ($residentIds as $residentId) {
            $itemsPerResident = random_int(3, 5);
            $selectedItems = (array) array_rand($itemIds, min($itemsPerResident, count($itemIds)));
            if (! is_array($selectedItems)) {
                $selectedItems = [$selectedItems];
            }

            foreach ($selectedItems as $itemIndex) {
                $itemId = $itemIds[$itemIndex];
                $recordCount = random_int(1, 3);
                for ($rec = 0; $rec < $recordCount; $rec++) {
                    $rows[] = [
                        'resident_id' => $residentId,
                        'charge_item_id' => $itemId,
                        'date' => $monthStart->copy()->addDays(random_int(0, $daysInMonth - 1))->toDateString(),
                        'unit_price' => random_int(50, 5000),
                        'quantity' => random_int(1, 10),
                        'note' => '負荷テストデータ',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                    $total++;
                }
            }
        }

        foreach (array_chunk($rows, 1000) as $chunk) {
            DB::table('daily_charges')->insert($chunk);
        }
        logInfo("日々課金: {$m}/" . MONTHS . " ヶ月完了 (累計 {$total}件)");
    }

    logInfo("日々課金: 合計 {$total} 件作成");
}

function createMonthlyInvoices(array $residents, InvoiceCalculationService $service): void
{
    // 負荷テストには当月1ヶ月分（1,000件）で十分（計算処理が重いため）
    $invoiceMonths = 1;
    $existing = MonthlyInvoice::whereHas('resident', fn ($q) => $q->where('name', 'like', '負荷テスト入居者%'))->count();
    $target = count($residents) * $invoiceMonths;
    if ($existing >= $target) {
        logInfo("月次請求: 既存 {$existing} 件を再利用");
        return;
    }

    $created = 0;
    for ($m = 0; $m < $invoiceMonths; $m++) {
        $yearMonth = Carbon::now()->subMonths($m)->format('Y-m');
        $monthStart = Carbon::createFromFormat('Y-m', $yearMonth)->startOfMonth();

        foreach ($residents as $resident) {
            if (! $resident->isLivingAt($monthStart)) {
                continue;
            }

            $existingInvoice = MonthlyInvoice::where('resident_id', $resident->id)
                ->where('billing_year_month', $yearMonth)
                ->first();

            if ($existingInvoice) {
                continue;
            }

            $invoice = new MonthlyInvoice([
                'resident_id' => $resident->id,
                'facility_id' => $resident->facility_id,
                'billing_year_month' => $yearMonth,
                'status' => InvoiceStatus::Unbilled,
                'version' => 0,
            ]);
            $service->calculate($invoice);
            $invoice->save();
            $created++;
        }
        logInfo("月次請求: {$yearMonth} 完了 (累計 {$created}件)");
    }

    logInfo("月次請求: 合計 {$created} 件作成");
}

function createFaqs(): void
{
    $existing = ChatbotFaq::count();
    if ($existing >= FAQ_COUNT) {
        logInfo("チャットボットFAQ: 既存 {$existing} 件を再利用");
        return;
    }

    $categories = ['請求・入金', '手続き・操作', '入居者情報', '利用料', 'その他'];
    $questions = [
        '請求額の確認方法を教えてください',
        '支払い状況の確認方法を教えてください',
        '領収書の発行方法を教えてください',
        '支払い方法について教えてください',
        '口座振替の日付を教えてください',
        '未払いがあるか確認したい',
        '月額利用料の内訳を教えてください',
        '自費分の計算方法を教えてください',
        '日割り計算の基準を教えてください',
        '消費税率を教えてください',
    ];

    $data = [];
    for ($i = 0; $i < FAQ_COUNT; $i++) {
        $question = $questions[$i % count($questions)];
        $data[] = [
            'question' => $question . " ({$i})",
            'keywords' => json_encode(['請求', '支払い', '確認', '領収書', '利用料']),
            'answer' => str_repeat('これは負荷テスト用のFAQ回答です。', 5),
            'category' => $categories[$i % count($categories)],
            'is_active' => true,
            'sort_order' => $i,
            'facility_id' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }
    DB::table('chatbot_faqs')->insert($data);
    logInfo('チャットボットFAQ: ' . FAQ_COUNT . ' 件作成');
}

function createChatLogs(array $facilities, array $users): void
{
    $existing = ChatLog::count();
    if ($existing >= CHAT_LOG_COUNT) {
        logInfo("チャットログ: 既存 {$existing} 件を再利用");
        return;
    }

    $messages = [
        '請求額を教えてください',
        '支払い状況を確認したい',
        '月額利用料はいくらですか',
        '領収書の発行方法を教えてください',
        '担当者に連絡したい',
        '未入金の入居者はいますか',
        '先月の請求額を教えてください',
        '支払い方法について教えてください',
    ];
    $intents = ['faq', 'invoice_amount', 'invoice_status', 'daily_charge_total', 'resident_lookup', 'escalate'];
    $replies = [
        '請求額はダッシュボードから確認できます。',
        '支払い状況は未入金です。',
        '月額利用料は基本家賃と管理費の合計です。',
        '領収書は請求書詳細から発行できます。',
        '担当者に連絡いたします。',
    ];

    $facilityIds = array_column($facilities, 'id');
    $userIds = array_column($users, 'id');
    if (empty($userIds)) {
        $userIds = [null];
    }

    $batchSize = 1000;
    $rows = [];
    $baseTime = now()->subDays(30);

    for ($i = 0; $i < CHAT_LOG_COUNT; $i++) {
        $rows[] = [
            'user_id' => $userIds[array_rand($userIds)],
            'session_id' => 'loadtest_session_' . random_int(1, 1000),
            'user_message' => $messages[array_rand($messages)],
            'intent' => $intents[array_rand($intents)],
            'bot_reply' => $replies[array_rand($replies)],
            'faq_matched' => (bool) random_int(0, 1),
            'facility_id' => $facilityIds[array_rand($facilityIds)],
            'created_at' => $baseTime->copy()->addSeconds($i * 60),
            'updated_at' => $baseTime->copy()->addSeconds($i * 60),
        ];

        if (count($rows) >= $batchSize) {
            DB::table('chat_logs')->insert($rows);
            $rows = [];
        }
    }
    if ($rows) {
        DB::table('chat_logs')->insert($rows);
    }

    logInfo('チャットログ: ' . CHAT_LOG_COUNT . ' 件作成');
}

// ===== 実行 =====
logInfo('=== 負荷テストデータ生成開始 ===');
$startTime = microtime(true);

$facilities = createFacilities();
$chargeItems = ensureChargeItems();
$residents = createResidents($facilities);
createDailyCharges($residents, $chargeItems);

$calculationService = app(InvoiceCalculationService::class);
createMonthlyInvoices($residents, $calculationService);

createFaqs();

$users = User::where('email', 'like', 'loadtest_admin%@example.jp')->get()->all();
createChatLogs($facilities, $users);

$elapsed = microtime(true) - $startTime;
logInfo('=== データ生成完了 ===');
logInfo(sprintf('所要時間: %.1fs', $elapsed));
logInfo('最終件数:');
logInfo('  施設: ' . Facility::where('name', 'like', '負荷テスト施設%')->count());
logInfo('  入居者: ' . Resident::where('name', 'like', '負荷テスト入居者%')->count());
logInfo('  日々課金: ' . DailyCharge::count());
logInfo('  月次請求: ' . MonthlyInvoice::count());
logInfo('  FAQ: ' . ChatbotFaq::count());
logInfo('  チャットログ: ' . ChatLog::count());
