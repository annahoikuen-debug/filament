<?php

namespace App\Services;

use App\Enums\InvoiceStatus;
use App\Models\MonthlyInvoice;
use Carbon\Carbon;

class InvoiceCsvExportService
{
    /**
     * 指定年月の請求一覧CSVを生成する (Excel対応 UTF-8 BOM付き)
     *
     * @param  string  $yearMonth  'YYYY-MM'
     * @return string CSV文字列
     */
    public function exportMonthlyListCsv(string $yearMonth): string
    {
        $invoices = MonthlyInvoice::with('resident')
            ->forYearMonth($yearMonth)
            ->join('residents', 'monthly_invoices.resident_id', '=', 'residents.id')
            ->orderBy('residents.room_number')
            ->select('monthly_invoices.*')
            ->get();

        $headers = [
            '請求年月',
            '部屋番号',
            '入居者氏名',
            'フリガナ',
            '基本家賃(非課税)',
            '基本管理費(課税)',
            '自費サービス小計(課税)',
            '課税対象額',
            '消費税率',
            '消費税額',
            '請求合計金額',
            '請求ステータス',
            '入金日',
            '入金方法',
            '領収書番号',
        ];

        $output = fopen('php://temp', 'r+');

        // Excel用 UTF-8 BOM 出力
        fwrite($output, "\xEF\xBB\xBF");

        fputcsv($output, $headers);

        foreach ($invoices as $inv) {
            fputcsv($output, [
                $inv->billing_year_month,
                $inv->resident->room_number,
                $inv->resident->name,
                $inv->resident->name_kana,
                $inv->rent_subtotal, // 非課税
                $inv->management_fee_subtotal, // 課税
                $inv->service_subtotal, // 課税
                $inv->taxable_amount, // 課税対象額
                $inv->tax_rate.'%', // 消費税率
                $inv->tax_amount, // 消費税額
                $inv->total_amount, // 請求合計金額
                $inv->status?->getLabel() ?? $inv->status,
                $inv->paid_at ? Carbon::parse($inv->paid_at)->format('Y/m/d') : '',
                $inv->payment_method?->getLabel() ?? '',
                $inv->receipt_number ?? '',
            ]);
        }

        rewind($output);
        $csvContent = stream_get_contents($output);
        fclose($output);

        return $csvContent;
    }

    /**
     * 会計仕訳連携用CSVを生成する（弥生会計・freee・MF対応形式）
     * 借方: 売掛金 / 貸方: 賃貸料収入、管理費収入、雑収入(立替)
     */
    public function exportAccountingJournalCsv(string $yearMonth): string
    {
        $invoices = MonthlyInvoice::with('resident')
            ->forYearMonth($yearMonth)
            ->whereIn('status', [InvoiceStatus::Billed, InvoiceStatus::Paid])
            ->get();

        $headers = [
            '取引日',
            '借方科目',
            '借方金額',
            '貸方科目',
            '貸方金額',
            '税区分',
            '摘要',
        ];

        $output = fopen('php://temp', 'r+');
        fwrite($output, "\xEF\xBB\xBF");
        fputcsv($output, $headers);

        $endOfMonth = Carbon::createFromFormat('Y-m', $yearMonth)->endOfMonth()->format('Y/m/d');

        foreach ($invoices as $inv) {
            $summary = "{$yearMonth}請求 [{$inv->resident->room_number}] {$inv->resident->name}";

            // 1. 家賃仕訳 (非課税売上)
            if ($inv->rent_subtotal > 0) {
                fputcsv($output, [
                    $endOfMonth,
                    '売掛金',
                    $inv->rent_subtotal,
                    '賃貸料収入',
                    $inv->rent_subtotal,
                    '対象外(非課税)',
                    "{$summary} 家賃分",
                ]);
            }

            // 2. 管理費仕訳 (課税売上10%)
            if ($inv->management_fee_subtotal > 0) {
                fputcsv($output, [
                    $endOfMonth,
                    '売掛金',
                    $inv->management_fee_subtotal,
                    '施設管理費収入',
                    $inv->management_fee_subtotal,
                    '課税売上10%',
                    "{$summary} 管理費分",
                ]);
            }

            // 3. 自費サービス・立替金仕訳
            if ($inv->service_subtotal > 0) {
                fputcsv($output, [
                    $endOfMonth,
                    '売掛金',
                    $inv->service_subtotal,
                    '自費・立替金収入',
                    $inv->service_subtotal,
                    '課税売上10%',
                    "{$summary} 自費立替分",
                ]);
            }
        }

        rewind($output);
        $csvContent = stream_get_contents($output);
        fclose($output);

        return $csvContent;
    }
}
