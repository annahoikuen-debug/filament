<?php

namespace App\Services\Banking;

use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Models\Facility;
use App\Models\MonthlyInvoice;
use App\Models\ResidentBankAccount;
use Illuminate\Support\Collection;

/**
 * Step 3: 月次請求データからの口座振替依頼ファイル出力サービス
 * payment_method = direct_debit かつ status = unbilled の請求を抽出して全銀ファイル生成
 */
class DirectDebitRequestService
{
    public function __construct(
        protected ZenginFormatService $formatService,
    ) {
    }

    /**
     * 指定年月の口座振替対象請求を取得する
     *
     * @return Collection<int, array{invoice: MonthlyInvoice, account: ResidentBankAccount}>
     */
    public function collectEntries(string $yearMonth, ?int $facilityId = null): Collection
    {
        $query = MonthlyInvoice::query()
            ->forYearMonth($yearMonth)
            ->where('payment_method', PaymentMethod::DirectDebit)
            ->where('status', InvoiceStatus::Unbilled)
            ->with(['resident']);

        if ($facilityId) {
            $query->forFacility($facilityId);
        }

        $invoices = $query->get();

        // 有効な銀行口座を持つ入居者のみ
        $residentIds = $invoices->pluck('resident_id')->unique();
        $accounts = ResidentBankAccount::query()
            ->whereIn('resident_id', $residentIds)
            ->where('is_active', true)
            ->get()
            ->groupBy('resident_id')
            ->map(fn ($group) => $group->first());

        return $invoices
            ->map(fn (MonthlyInvoice $invoice) => $accounts->has($invoice->resident_id)
                ? ['invoice' => $invoice, 'account' => $accounts->get($invoice->resident_id)]
                : null)
            ->filter()
            ->values();
    }

    /**
     * 口座振替依頼ファイルを生成する
     */
    public function generate(string $yearMonth, Facility $facility, ?string $transferDate = null): string
    {
        $entries = $this->collectEntries($yearMonth, $facility->id);

        $facilityBank = $facility->bank ?? [];
        $delegateCode = $facilityBank['delegate_code'] ?? config('services.zengin.delegate_code', '');

        $header = [
            'delegate_code' => $delegateCode,
            'delegate_name_kana' => $facilityBank['delegate_name_kana'] ?? mb_convert_kana($facility->name ?? '', 'khs', 'UTF-8'),
            'transfer_date' => $transferDate ?? (now()->day < 27
                ? now()->setDay(27)->format('Ymd')
                : now()->addMonthNoOverflow()->setDay(27)->format('Ymd')),
            'bank_code' => $facilityBank['bank_code'] ?? '',
            'bank_name_kana' => $facilityBank['bank_name_kana'] ?? '',
            'branch_code' => $facilityBank['branch_code'] ?? '',
            'branch_name_kana' => $facilityBank['branch_name_kana'] ?? '',
            'account_type' => $facilityBank['account_type'] ?? '1',
            'account_number' => $facilityBank['account_number'] ?? '',
        ];

        return $this->formatService->generate($header, $entries->all());
    }
}
