<?php

namespace App\Services\Denchoho;

use App\Models\ElectronicRecord;
use App\Models\MonthlyInvoice;
use Illuminate\Support\Facades\File;

class ElectronicRecordService
{
    /**
     * 確定・出力されたPDF請求書を電帳法レコードとして登録
     */
    public function recordInvoicePdf(MonthlyInvoice $invoice, string $pdfFilePath, ?string $reason = null): ElectronicRecord
    {
        $hash = File::exists($pdfFilePath) ? hash_file('sha256', $pdfFilePath) : str_repeat('0', 64);
        $size = File::exists($pdfFilePath) ? File::size($pdfFilePath) : 0;

        $transactionDate = $invoice->invoice_date
            ?? ($invoice->custom_invoice_date ? $invoice->custom_invoice_date->format('Y-m-d') : now()->format('Y-m-d'));

        return ElectronicRecord::create([
            'facility_id' => $invoice->facility_id,
            'recordable_type' => MonthlyInvoice::class,
            'recordable_id' => $invoice->id,
            'document_type' => 'invoice',
            'transaction_date' => $transactionDate,
            'amount' => (int) $invoice->total_with_tax,
            'partner_name' => $invoice->resident?->name ?? '未設定',
            'file_path' => $pdfFilePath,
            'file_name' => basename($pdfFilePath),
            'sha256_hash' => $hash,
            'file_size' => $size,
            'correction_reason' => $reason,
            'version' => ($invoice->version ?? 0) + 1,
        ]);
    }

    /**
     * 電帳法 3大要件に基づく複合検索
     */
    public function search(
        int $facilityId,
        ?string $startDate = null,
        ?string $endDate = null,
        ?int $minAmount = null,
        ?int $maxAmount = null,
        ?string $partnerName = null
    ) {
        $query = ElectronicRecord::where('facility_id', $facilityId);

        if ($startDate) {
            $query->whereDate('transaction_date', '>=', $startDate);
        }
        if ($endDate) {
            $query->whereDate('transaction_date', '<=', $endDate);
        }
        if ($minAmount !== null) {
            $query->where('amount', '>=', $minAmount);
        }
        if ($maxAmount !== null) {
            $query->where('amount', '<=', $maxAmount);
        }
        if ($partnerName) {
            $query->where('partner_name', 'like', "%{$partnerName}%");
        }

        return $query->orderByDesc('transaction_date')->get();
    }
}
