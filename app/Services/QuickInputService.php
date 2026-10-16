<?php

namespace App\Services;

use App\Models\DailyCharge;
use App\Models\Resident;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class QuickInputService
{
    /**
     * 直前入力からクイック入力用の初期値候補を取得
     *
     * @return array{
     *   resident_id: int|null,
     *   charge_item_id: int|null,
     *   unit_price: int,
     *   quantity: int,
     *   note: string,
     *   date: string
     * }
     */
    public function getLastInputForUser(User $user): array
    {
        $today = now()->toDateString();

        $lastCharge = DailyCharge::query()
            ->where('created_by', $user->id)
            ->whereHas('resident', function ($query) {
                $query->where('status', '!=', 'moved_out');
            })
            ->latest('created_at')
            ->first();

        if (! $lastCharge) {
            return $this->getDefaults($today);
        }

        $resident = $lastCharge->resident;
        if (! $resident || ! $resident->isLivingAt($lastCharge->date)) {
            return $this->getDefaults($today);
        }

        return [
            'resident_id'   => null,
            'charge_item_id' => $lastCharge->charge_item_id,
            'unit_price'    => $lastCharge->unit_price,
            'quantity'      => $lastCharge->quantity,
            'note'          => $lastCharge->note ?? '',
            'date'          => $today,
        ];
    }

    /**
     * ユーザーの定型備考上位5件を取得
     *
     * @return array<int, string>
     */
    public function getFrequentNotesForUser(User $user, int $limit = 5): array
    {
        return DailyCharge::query()
            ->where('created_by', $user->id)
            ->whereNotNull('note')
            ->where('note', '!=', '')
            ->select('note')
            ->selectRaw('COUNT(*) as cnt')
            ->groupBy('note')
            ->orderByDesc('cnt')
            ->limit($limit)
            ->pluck('note')
            ->toArray();
    }

    /**
     * デフォルト値を返す
     */
    private function getDefaults(string $today): array
    {
        return [
            'resident_id'    => null,
            'charge_item_id' => null,
            'unit_price'     => 0,
            'quantity'       => 1,
            'note'           => '',
            'date'           => $today,
        ];
    }
}