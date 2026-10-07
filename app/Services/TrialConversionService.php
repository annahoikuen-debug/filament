<?php

namespace App\Services;

use App\Mail\ContractCompletedMail;
use App\Models\Subscription;
use App\Models\Trial;
use Illuminate\Support\Facades\Log;

class TrialConversionService
{
    public const PLAN_PRICES = [
        'starter' => 15000,
        'standard' => 35000,
    ];

    public function __construct(
        private readonly MailService $mailService,
    ) {
    }

    /**
     * トライアルを本契約に移行
     *
     * @param  array{plan: string, invoice_registration_number: string, bank: array, contract_accepted: bool, quoted_price?: int, contract_accepted_ip?: string, contract_accepted_user_agent?: string}  $data
     */
    public function convert(Trial $trial, array $data): Subscription
    {
        if ($trial->status !== 'active') {
            throw new \RuntimeException('トライアルが有効ではないため移行できません');
        }

        $plan = $data['plan'];
        $monthlyPrice = $this->resolvePrice($plan, $data['quoted_price'] ?? null);

        return \DB::transaction(function () use ($trial, $plan, $monthlyPrice, $data) {
            // 1. 施設を本契約施設に昇格
            $facility = $trial->facility;
            $facility->update([
                'name' => $this->upgradeFacilityName($facility->name, $trial->id),
                'invoice_registration_number' => $data['invoice_registration_number'],
                'bank' => $data['bank'],
                'notes' => '本契約施設（トライアルID: ' . $trial->id . ' から移行）',
            ]);

            // 2. サブスクリプション作成（電子契約の同意記録付き）
            $subscription = Subscription::create([
                'trial_id' => $trial->id,
                'facility_id' => $facility->id,
                'plan' => $plan,
                'status' => 'active',
                'monthly_price' => $monthlyPrice,
                'started_at' => now(),
                'ends_at' => now()->addMonth(),
                'contract_accepted_at' => $data['contract_accepted'] ? now() : null,
                'contract_accepted_ip' => $data['contract_accepted_ip'] ?? null,
                'contract_accepted_user_agent' => $data['contract_accepted_user_agent'] ?? null,
            ]);

            // 3. トライアルステータス更新
            $trial->update(['status' => 'converted']);

            // 4. 本契約完了メール
            $this->mailService->send(
                new ContractCompletedMail($trial, $plan, $monthlyPrice),
                $trial->email,
            );

            Log::info("Trial {$trial->id} converted to {$plan} contract (subscription {$subscription->id})");

            return $subscription;
        });
    }

    private function resolvePrice(string $plan, ?int $quotedPrice): int
    {
        if ($plan === 'enterprise') {
            if ($quotedPrice === null || $quotedPrice <= 0) {
                throw new \RuntimeException('エンタープライズプランは見積金額（quoted_price）が必須です');
            }
            return $quotedPrice;
        }

        return self::PLAN_PRICES[$plan];
    }

    private function upgradeFacilityName(string $currentName, int $trialId): string
    {
        // 「トライアル XXX (12)」→「XXX」
        $pattern = '/^トライアル\s+(.+)\s+\(' . $trialId . '\)$/u';
        if (preg_match($pattern, $currentName, $matches)) {
            return $matches[1];
        }

        return $currentName;
    }
}
