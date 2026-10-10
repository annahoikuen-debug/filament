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
    /**
     * 施設スコープを適用した入居者検索
     * facility_admin は所属施設内のみ、corporate_admin は全施設を検索可能
     */
    public function findResident(string $keyword, User $user): ?Resident
    {
        $query = Resident::query()
            ->where(fn (Builder $q) => $q
                ->where('name', 'like', "%{$keyword}%")
                ->orWhere('name_kana', 'like', "%{$keyword}%")
                ->orWhere('room_number', $keyword));

        $this->applyFacilityScope($query, $user);

        return $query->first();
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

    public function getLatestInvoice(Resident $resident): ?MonthlyInvoice
    {
        return $resident->monthlyInvoices()
            ->orderByDesc('billing_year_month')
            ->first();
    }

    /**
     * @return array<string, mixed>
     */
    public function getPaymentStatus(Resident $resident): array
    {
        $invoice = $this->getLatestInvoice($resident);

        if ($invoice === null) {
            return [
                'has_invoice' => false,
                'status' => null,
                'status_label' => '請求データなし',
                'billing_year_month' => null,
                'total_amount' => null,
                'paid_at' => null,
            ];
        }

        return [
            'has_invoice' => true,
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
