<?php

namespace App\Services\Chatbot;

use App\Models\MonthlyInvoice;
use App\Models\Resident;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ResidentQueryService
{
    public function __construct(
        private readonly ?FuzzyMatcher $fuzzyMatcher = null,
    ) {}

    /**
     * 施設スコープを適用した入居者検索（完全一致 → カナ一致 → 曖昧一致）
     */
    public function findResident(string $keyword, User $user): ?Resident
    {
        $candidates = $this->findCandidates($keyword, $user);

        return $candidates->first()['resident'] ?? null;
    }

    /**
     * 入居者候補のスコアリング検索（完全一致: 1.0, カナ一致: 0.95, 曖昧一致: similarity）
     *
     * @return Collection<int, array{resident: Resident, score: float}>
     */
    public function findCandidates(string $keyword, User $user): Collection
    {
        $keyword = trim($keyword);
        if ($keyword === '') {
            return collect();
        }

        // 1. 完全一致・部屋番号完全一致（高速パス）
        $exactQuery = Resident::query()
            ->where(fn (Builder $q) => $q
                ->where('name', $keyword)
                ->orWhere('name_kana', $keyword)
                ->orWhere('room_number', $keyword));
        $this->applyFacilityScope($exactQuery, $user);
        $exact = $exactQuery->get();

        if ($exact->isNotEmpty()) {
            return $exact->map(fn (Resident $r) => ['resident' => $r, 'score' => 1.0]);
        }

        // 部分一致の直接確認
        $partialQuery = Resident::query()
            ->where(fn (Builder $q) => $q
                ->where('name', 'like', "%{$keyword}%")
                ->orWhere('name_kana', 'like', "%{$keyword}%"));
        $this->applyFacilityScope($partialQuery, $user);
        $partial = $partialQuery->get();

        if ($partial->isNotEmpty()) {
            return $partial->map(fn (Resident $r) => ['resident' => $r, 'score' => 0.95]);
        }

        // 2. カナ一致 & 曖昧一致
        $matcher = $this->fuzzyMatcher ?? new FuzzyMatcher;
        $threshold = (float) config('chatbot.fuzzy.threshold', 0.7);

        $normalizedKeyword = $matcher->normalizeKana($keyword);

        // 2-1. 正規化インデックスによる高速完全・前方一致（DBインデックス活用）
        if ($normalizedKeyword !== '') {
            $normQuery = Resident::query()
                ->where(fn (Builder $q) => $q
                    ->where('normalized_kana', $normalizedKeyword)
                    ->orWhere('normalized_name', $normalizedKeyword)
                    ->orWhere('normalized_kana', 'like', "{$normalizedKeyword}%")
                    ->orWhere('normalized_name', 'like', "{$normalizedKeyword}%"));
            $this->applyFacilityScope($normQuery, $user);
            $normResults = $normQuery->limit(5)->get();

            if ($normResults->isNotEmpty()) {
                return $normResults->map(fn (Resident $r) => [
                    'resident' => $r,
                    'score' => ($r->normalized_kana === $normalizedKeyword || $r->normalized_name === $normalizedKeyword) ? 0.95 : 0.9,
                ]);
            }
        }

        // 2-2. 誤字等のためのフォールバック曖昧一致（レーベンシュタイン距離）
        $allQuery = Resident::query();
        $this->applyFacilityScope($allQuery, $user);
        $allResidents = $allQuery->get();

        $scored = [];

        foreach ($allResidents as $resident) {
            $normalizedKana = $resident->normalized_kana ?? $matcher->normalizeKana($resident->name_kana ?? '');
            $normalizedName = $resident->normalized_name ?? $matcher->normalizeKana($resident->name);

            // カナ正規化後の完全一致/部分一致
            if ($normalizedKeyword !== '' && (
                $normalizedKana === $normalizedKeyword ||
                $normalizedName === $normalizedKeyword ||
                str_contains($normalizedKana, $normalizedKeyword)
            )) {
                $scored[] = [
                    'resident' => $resident,
                    'score' => 0.9,
                ];
                continue;
            }

            // 曖昧一致（名前およびカナに対するレーベンシュタイン類似度）
            $nameSim = max(
                $matcher->similarity($resident->name, $keyword),
                $matcher->similarity($normalizedName, $normalizedKeyword)
            );
            $kanaSim = $resident->name_kana ? $matcher->similarity($normalizedKana, $normalizedKeyword) : 0.0;
            $bestSim = max($nameSim, $kanaSim);

            // プレフィックスに「施設」「ホーム」等の共通語が含まれていて識別部が異なる場合の過剰一致を抑制
            // 施設A vs 施設B のように「施設」プレフィックスで全体の類似度が上がってしまうケースをガード
            $isPrefixFalseMatch = false;
            if (mb_strlen($keyword) >= 4 && mb_strlen($resident->name) >= 4) {
                // 先頭2文字が共通で3文字目が異なる（例: 「施設A」と「施設B」）場合
                if (mb_substr($keyword, 0, 2) === mb_substr($resident->name, 0, 2)
                    && mb_substr($keyword, 2, 1) !== mb_substr($resident->name, 2, 1)
                    && in_array(mb_substr($keyword, 0, 2), ['施設', 'ホー', 'セン'])) {
                    $isPrefixFalseMatch = true;
                }
            }

            if (! $isPrefixFalseMatch && $bestSim >= $threshold) {
                $scored[] = [
                    'resident' => $resident,
                    'score' => $bestSim,
                ];
            }
        }

        return collect($scored)
            ->sortByDesc('score')
            ->values();
    }

    /**
     * 施設スコープ内の入居者一覧（部分一致）
     *
     * @return Collection<int, Resident>
     */
    public function searchResidents(string $keyword, User $user): Collection
    {
        $query = Resident::query()
            ->where(fn (Builder $q) => $q
                ->where('name', 'like', "%{$keyword}%")
                ->orWhere('name_kana', 'like', "%{$keyword}%")
                ->orWhere('room_number', $keyword));

        $this->applyFacilityScope($query, $user);

        return $query->limit(10)->get();
    }

    public function getLatestInvoice(Resident $resident, ?string $yearMonth = null): ?MonthlyInvoice
    {
        $query = $resident->monthlyInvoices();

        if ($yearMonth !== null) {
            return $query->where('billing_year_month', $yearMonth)->first();
        }

        return $query->orderByDesc('billing_year_month')->first();
    }

    /**
     * @return array<string, mixed>
     */
    public function getPaymentStatus(Resident $resident, ?string $yearMonth = null): array
    {
        $invoice = $this->getLatestInvoice($resident, $yearMonth);

        if ($invoice === null) {
            return [
                'has_invoice' => false,
                'status' => null,
                'status_label' => '請求データなし',
                'billing_year_month' => $yearMonth,
                'total_amount' => null,
                'paid_at' => null,
            ];
        }

        return [
            'has_invoice' => true,
            'invoice_id' => $invoice->id,
            'status' => $invoice->status->value,
            'status_label' => $invoice->status->getLabel(),
            'billing_year_month' => $invoice->billing_year_month,
            'total_amount' => $invoice->total_amount,
            'paid_at' => $invoice->paid_at?->format('Y/m/d'),
        ];
    }

    /**
     * 指定月の日々の自費利用料合計（税抜）
     */
    public function getMonthlyDailyChargeTotal(Resident $resident, string $yearMonth): int
    {
        return (int) $resident->dailyCharges()
            ->whereYear('date', (int) substr($yearMonth, 0, 4))
            ->whereMonth('date', (int) substr($yearMonth, 5, 2))
            ->sum(DB::raw('unit_price * quantity'));
    }

    /**
     * ログ用氏名マスキングのための入居者名一覧（施設スコープ内の全件）
     *
     * @return Collection<int, string>
     */
    public function residentNames(User $user): Collection
    {
        $query = Resident::query()->select('name', 'name_kana');

        $this->applyFacilityScope($query, $user);

        return $query->get()
            ->flatMap(fn (Resident $r) => array_filter([$r->name, $r->name_kana], fn (?string $n) => $n !== null && $n !== ''))
            ->unique()
            ->values();
    }

    /**
     * 日割り計算の根拠データ（月中途入居・退去の場合）
     *
     * @return array<string, mixed>|null
     */
    public function getProrationBasis(Resident $resident, string $yearMonth): ?array
    {
        $year = (int) substr($yearMonth, 0, 4);
        $month = (int) substr($yearMonth, 5, 2);
        $daysInMonth = cal_days_in_month(CAL_GREGORIAN, $month, $year);

        $moveIn = $resident->move_in_date;
        $moveOut = $resident->move_out_date;

        $startDay = 1;
        $endDay = $daysInMonth;

        if ($moveIn !== null && $moveIn->year === $year && $moveIn->month === $month) {
            $startDay = (int) $moveIn->day;
        }
        if ($moveOut !== null && $moveOut->year === $year && $moveOut->month === $month) {
            $endDay = (int) $moveOut->day;
        }

        $activeDays = $endDay - $startDay + 1;

        if ($startDay === 1 && $endDay === $daysInMonth) {
            return null;
        }

        return [
            'days_in_month' => $daysInMonth,
            'active_days' => $activeDays,
            'start_day' => $startDay,
            'end_day' => $endDay,
            'base_rent' => $resident->base_rent,
            'base_management_fee' => $resident->base_management_fee,
            'prorated_rent' => (int) round($resident->base_rent * $activeDays / $daysInMonth),
            'prorated_management_fee' => (int) round($resident->base_management_fee * $activeDays / $daysInMonth),
        ];
    }

    /**
     * 施設スコープを強制適用（他施設のデータにアクセスできないようにする）
     */
    private function applyFacilityScope(Builder $query, User $user): void
    {
        if ($user->isFacilityAdmin() && $user->facility_id) {
            $query->where('facility_id', $user->facility_id);
        }
    }
}
