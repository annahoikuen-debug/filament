<?php

namespace App\Filament\Pages;

use App\Enums\InvoiceStatus;
use App\Enums\ResidentStatus;
use App\Models\ChargeItem;
use App\Models\DailyCharge;
use App\Models\DailyChargePreset;
use App\Models\MonthlyInvoice;
use App\Models\Resident;
use App\Services\DailyChargePresetService;
use Carbon\Carbon;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Step 13: 「単一品目モード」と「プリセット一括モード」をタブ切替可能な一括入力ページ
 * Step 14: N+1防止・確定月ガード・同一日同一品目の重複スキップ
 */
class BulkDailyChargeEntry extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static ?string $navigationLabel = '自費一括入力';

    protected static ?string $title = '自費サービス一括入力';

    protected static ?string $navigationGroup = '請求管理';

    protected static ?int $navigationSort = 3;

    protected static string $view = 'filament.pages.bulk-daily-charge-entry';

    /** モード: 'single' | 'preset' */
    public string $mode = 'single';

    // --- 単一品目モード ---
    public ?int $chargeItemId = null;

    public ?string $date = null;

    public int $unitPrice = 0;

    public array $entries = [];

    // --- プリセット一括モード ---
    public ?int $presetId = null;

    public ?string $presetStartDate = null;

    public ?string $presetEndDate = null;

    /** 適用曜日（0=日〜6=土）。空=毎日 */
    public array $presetWeekdays = [];

    public array $presets = [];

    /** プリセットのプレビュー（品目・単価・件数） */
    public array $presetPreview = [];

    public int $presetEstimatedTotal = 0;

    public function mount(): void
    {
        abort_unless(Auth::user()?->canAccessPanel(filament()->getCurrentPanel()), 403);

        $this->date = now()->toDateString();
        $this->presetStartDate = now()->toDateString();
        $this->presetEndDate = now()->toDateString();
        $this->loadChargeItems();
        $this->loadPresets();
        $this->loadResidents();
    }

    public function updatedMode(): void
    {
        if ($this->mode === 'preset') {
            $this->updatedPresetId();
        }
    }

    public function loadChargeItems(): void
    {
        $firstItem = ChargeItem::where('is_active', true)->first();
        if ($firstItem) {
            $this->chargeItemId = $firstItem->id;
            $this->unitPrice = $firstItem->default_price ?? 0;
        }
    }

    public function updatedChargeItemId(): void
    {
        if ($this->chargeItemId) {
            $item = ChargeItem::find($this->chargeItemId);
            if ($item) {
                $targetDate = $this->date ? Carbon::parse($this->date) : now();
                $this->unitPrice = $item->getPriceForDate($targetDate) ?? $item->default_price ?? 0;
            }
        }
    }

    public function updatedDate(): void
    {
        $this->updatedChargeItemId();
    }

    public function loadPresets(): void
    {
        $user = Auth::user();

        $this->presets = DailyChargePreset::query()
            ->availableFor($user?->isFacilityAdmin() ? $user->facility_id : null)
            ->with(['items.chargeItem:id,name,default_price'])
            ->get()
            ->map(fn ($p) => [
                'id' => $p->id,
                'name' => $p->name,
                'description' => $p->description,
                'items' => $p->items->map(fn ($i) => [
                    'charge_item_id' => $i->charge_item_id,
                    'name' => $i->chargeItem?->name ?? '',
                    'quantity' => $i->default_quantity,
                ])->toArray(),
            ])
            ->toArray();

        if ($this->presets !== []) {
            $this->presetId = $this->presets[0]['id'];
            $this->updatedPresetId();
        }
    }

    public function updatedPresetId(): void
    {
        $this->refreshPresetPreview();
    }

    public function updatedPresetStartDate(): void
    {
        $this->refreshPresetPreview();
    }

    public function updatedPresetEndDate(): void
    {
        $this->refreshPresetPreview();
    }

    public function updatedPresetWeekdays(): void
    {
        $this->refreshPresetPreview();
    }

    /** 金額のリアルタイム合計プレビュー（Step 16） */
    public function refreshPresetPreview(): void
    {
        $this->presetPreview = [];
        $this->presetEstimatedTotal = 0;

        $preset = $this->getPreset();
        if (! $preset) {
            return;
        }

        $dates = $this->getPresetDates();
        $preset->loadMissing('items.chargeItem');

        $targetDate = $dates !== [] ? Carbon::parse($dates[0]) : now();
        $this->presetPreview = $preset->items->map(function ($item) use ($targetDate) {
            $price = $item->chargeItem?->getPriceForDate($targetDate) ?? $item->chargeItem?->default_price ?? 0;

            return [
                'name' => $item->chargeItem?->name ?? '',
                'quantity' => $item->default_quantity,
                'unit_price' => (int) $price,
            ];
        })->toArray();

        $this->presetEstimatedTotal = collect($this->presetPreview)
            ->sum(fn ($i) => $i['unit_price'] * $i['quantity']) * count($dates);
    }

    public function getPreset(): ?DailyChargePreset
    {
        return $this->presetId ? DailyChargePreset::find($this->presetId) : null;
    }

    /** 曜日パターンを展開した日付リスト（Step 12） */
    public function getPresetDates(): array
    {
        if (! $this->presetStartDate || ! $this->presetEndDate) {
            return [];
        }

        return app(DailyChargePresetService::class)
            ->expandDates($this->presetStartDate, $this->presetEndDate, $this->presetWeekdays);
    }

    public function loadResidents(): void
    {
        $user = Auth::user();
        $query = Resident::where('status', ResidentStatus::Active)
            ->orderBy('room_number');

        if ($user?->isFacilityAdmin() && $user->facility_id) {
            $query->where('facility_id', $user->facility_id);
        }

        $residents = $query->get();

        $this->entries = $residents->map(function ($r) {
            return [
                'resident_id' => $r->id,
                'room_number' => $r->room_number,
                'floor' => $this->extractFloor($r->room_number),
                'name' => $r->name,
                'selected' => false,
                'quantity' => 1,
                'note' => '',
            ];
        })->toArray();
    }

    /** 部屋番号からフロア（先頭の階数）を抽出（フロア別選択用） */
    protected function extractFloor(?string $roomNumber): string
    {
        if ($roomNumber && preg_match('/^(\d)/', $roomNumber, $m)) {
            return $m[1].'階';
        }

        return 'その他';
    }

    public function getFloorsProperty(): array
    {
        return array_values(array_unique(array_column($this->entries, 'floor')));
    }

    public function selectAll(): void
    {
        foreach ($this->entries as $key => $entry) {
            $this->entries[$key]['selected'] = true;
        }
    }

    public function deselectAll(): void
    {
        foreach ($this->entries as $key => $entry) {
            $this->entries[$key]['selected'] = false;
        }
    }

    /** フロア別選択（Step 13） */
    public function selectFloor(string $floor): void
    {
        foreach ($this->entries as $key => $entry) {
            if (($entry['floor'] ?? '') === $floor) {
                $this->entries[$key]['selected'] = true;
            }
        }
    }

    /** プリセット対象者ワンクリック選択（Step 13） */
    public function selectAllForPreset(): void
    {
        // プリセットモードでは曜日適用の想定から全員を選択対象とする
        $this->selectAll();
    }

    public function getSelectedCountProperty(): int
    {
        return count(array_filter($this->entries, fn ($e) => ! empty($e['selected'])));
    }

    public function getTotalAmountProperty(): int
    {
        if ($this->mode === 'preset') {
            return $this->presetEstimatedTotal * max($this->selectedCount, 0);
        }

        $totalQuantity = 0;
        foreach ($this->entries as $entry) {
            if (! empty($entry['selected'])) {
                $totalQuantity += (int) ($entry['quantity'] ?? 0);
            }
        }

        return $totalQuantity * $this->unitPrice;
    }

    public function saveBulk(): void
    {
        if ($this->mode === 'preset') {
            $this->savePresetBulk();

            return;
        }

        $this->saveSingleBulk();
    }

    protected function saveSingleBulk(): void
    {
        $this->validate([
            'chargeItemId' => 'required|exists:charge_items,id',
            'date' => 'required|date',
        ], [
            'chargeItemId.required' => '品目を選択してください。',
            'date.required' => '日付を指定してください。',
        ]);

        $chargeItem = ChargeItem::findOrFail($this->chargeItemId);
        $saved = 0;
        $skippedLocked = 0;

        // 対象入居者IDを収集
        $selectedResidentIds = [];
        foreach ($this->entries as $entry) {
            if (! empty($entry['selected']) && (int) ($entry['quantity'] ?? 0) > 0) {
                $selectedResidentIds[] = $entry['resident_id'];
            }
        }

        if (empty($selectedResidentIds)) {
            Notification::make()
                ->title('保存対象が選択されていません')
                ->body('チェックボックスにチェックを入れ、数量1以上を入力してください。')
                ->warning()
                ->send();

            return;
        }

        // 1. N+1解消: 入居者を一括ロード
        $residents = Resident::whereIn('id', $selectedResidentIds)->get()->keyBy('id');

        // 2. 確定月ガード: 該当月に既に請求済・入金済の請求書がある入居者を検出
        $yearMonth = Carbon::parse($this->date)->format('Y-m');
        $lockedResidentIds = MonthlyInvoice::whereIn('resident_id', $selectedResidentIds)
            ->where('billing_year_month', $yearMonth)
            ->whereIn('status', [InvoiceStatus::Billed, InvoiceStatus::Paid])
            ->pluck('resident_id')
            ->toArray();

        DB::transaction(function () use ($chargeItem, $residents, $lockedResidentIds, &$saved, &$skippedLocked) {
            foreach ($this->entries as $entry) {
                if (empty($entry['selected'])) {
                    continue;
                }

                $qty = (int) ($entry['quantity'] ?? 0);
                if ($qty <= 0) {
                    continue;
                }

                $residentId = $entry['resident_id'];

                // 確定済み請求書が存在する場合はスキップ
                if (in_array($residentId, $lockedResidentIds, true)) {
                    $skippedLocked++;

                    continue;
                }

                $resident = $residents->get($residentId);
                if (! $resident) {
                    continue;
                }

                // 同一日・同一品目の重複スキップ（Step 14）
                $exists = DailyCharge::where('resident_id', $residentId)
                    ->where('charge_item_id', $chargeItem->id)
                    ->where('date', $this->date)
                    ->exists();
                if ($exists) {
                    continue;
                }

                DailyCharge::create([
                    'resident_id' => $resident->id,
                    'facility_id' => $resident->facility_id,
                    'charge_item_id' => $chargeItem->id,
                    'date' => $this->date,
                    'unit_price' => $this->unitPrice,
                    'quantity' => $qty,
                    'note' => ! empty($entry['note']) ? $entry['note'] : null,
                ]);

                $saved++;
            }
        });

        $skippedDuplicate = count($selectedResidentIds) - $saved - $skippedLocked;

        if ($saved === 0 && $skippedLocked > 0) {
            Notification::make()
                ->title('登録できませんでした')
                ->body("対象の入居者は {$yearMonth} 月分が既に請求済または入金済のため、自費記録を追加できません。")
                ->danger()
                ->send();

            return;
        }

        $message = "品目: {$chargeItem->name} / 利用日: {$this->date}";
        if ($skippedLocked > 0) {
            $message .= "（※確定済の {$skippedLocked} 件をスキップしました）";
        }
        if ($skippedDuplicate > 0) {
            $message .= "（※同日同品目の重複 {$skippedDuplicate} 件をスキップしました）";
        }

        Notification::make()
            ->title("{$saved}件の自費記録を一括登録しました")
            ->body($message)
            ->success()
            ->send();

        // 登録後に選択状態をクリア
        $this->deselectAll();
    }

    /** プリセット一括モード保存（Step 11-14） */
    protected function savePresetBulk(): void
    {
        $this->validate([
            'presetId' => 'required|exists:daily_charge_presets,id',
            'presetStartDate' => 'required|date',
            'presetEndDate' => 'required|date|after_or_equal:presetStartDate',
        ], [
            'presetId.required' => 'プリセットを選択してください。',
            'presetStartDate.required' => '開始日を指定してください。',
            'presetEndDate.required' => '終了日を指定してください。',
            'presetEndDate.after_or_equal' => '終了日は開始日以降を指定してください。',
        ]);

        $selectedResidentIds = $this->getSelectedResidentIds();
        if ($selectedResidentIds === []) {
            Notification::make()
                ->title('保存対象が選択されていません')
                ->body('チェックボックスにチェックを入れてください。')
                ->warning()
                ->send();

            return;
        }

        $preset = $this->getPreset();
        $dates = $this->getPresetDates();
        if ($dates === []) {
            Notification::make()
                ->title('適用対象日がありません')
                ->body('指定した期間と曜日パターンに該当する日付がありません。')
                ->warning()
                ->send();

            return;
        }

        // 確定月ガード: 対象期間に請求済・入金済の請求書がある入居者を除外
        $yearMonths = array_unique(array_map(fn ($d) => Carbon::parse($d)->format('Y-m'), $dates));
        $lockedResidentIds = MonthlyInvoice::whereIn('resident_id', $selectedResidentIds)
            ->whereIn('billing_year_month', $yearMonths)
            ->whereIn('status', [InvoiceStatus::Billed, InvoiceStatus::Paid])
            ->pluck('resident_id')
            ->unique()
            ->toArray();

        $targetResidentIds = array_values(array_diff($selectedResidentIds, $lockedResidentIds));
        $skippedLocked = count($selectedResidentIds) - count($targetResidentIds);

        $service = app(DailyChargePresetService::class);
        $records = $service->buildRecords($preset, $targetResidentIds, $dates);

        try {
            $result = DB::transaction(fn () => $service->bulkCreate($records));
        } catch (\DomainException $e) {
            Notification::make()
                ->title('登録エラー')
                ->body($e->getMessage())
                ->danger()
                ->send();

            return;
        }

        $message = 'プリセット: '.$preset->name.' / 期間: '.$this->presetStartDate.' 〜 '.$this->presetEndDate
            .' / 対象日数: '.count($dates).'日';
        if ($skippedLocked > 0) {
            $message .= "（※確定済入居者 {$skippedLocked} 名をスキップ）";
        }
        if ($result['skipped'] > 0) {
            $message .= "（※同日同品目の重複 {$result['skipped']} 件をスキップ）";
        }

        Notification::make()
            ->title("{$result['created']}件の自費記録を一括登録しました")
            ->body($message)
            ->success()
            ->send();

        $this->deselectAll();
    }

    protected function getSelectedResidentIds(): array
    {
        return collect($this->entries)
            ->filter(fn ($e) => ! empty($e['selected']))
            ->pluck('resident_id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
    }
}
