<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>領収証 - {{ $invoice->receipt_number }} - {{ $resident->name }} 様</title>
    <style>
        @page {
            margin: 20mm 20mm 20mm 20mm;
            size: a4 portrait;
        }
        body {
            font-family: 'ipaexg', 'ipag', 'Noto Sans JP', 'Hiragino Kaku Gothic ProN', 'Meiryo', sans-serif;
            font-size: 10pt;
            color: #333333;
            line-height: 1.5;
        }
        .header-title {
            text-align: center;
            font-size: 22pt;
            letter-spacing: 8px;
            font-weight: bold;
            margin-bottom: 25px;
            border-bottom: 2px solid #111827;
            padding-bottom: 6px;
        }
        .meta-table {
            width: 100%;
            margin-bottom: 30px;
        }
        .meta-table td {
            vertical-align: top;
        }
        .recipient-box {
            font-size: 13pt;
        }
        .recipient-name {
            font-size: 17pt;
            font-weight: bold;
            text-decoration: underline;
        }
        .issuer-box {
            text-align: right;
            font-size: 9pt;
            line-height: 1.4;
        }
        .receipt-amount-box {
            border: 2px solid #1f2937;
            background-color: #f9fafb;
            text-align: center;
            padding: 15px;
            margin-bottom: 25px;
        }
        .receipt-amount-label {
            font-size: 12pt;
            font-weight: bold;
        }
        .receipt-amount-value {
            font-size: 24pt;
            font-weight: bold;
            color: #111827;
            margin-left: 10px;
        }
        .proviso {
            font-size: 10.5pt;
            text-align: center;
            margin-bottom: 30px;
            padding-bottom: 10px;
            border-bottom: 1px dotted #9ca3af;
        }
        table.breakdown-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 30px;
        }
        table.breakdown-table th,
        table.breakdown-table td {
            border: 1px solid #d1d5db;
            padding: 8px 12px;
            font-size: 9.5pt;
        }
        table.breakdown-table th {
            background-color: #f3f4f6;
            text-align: center;
            font-weight: bold;
        }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .stamp-box {
            float: right;
            width: 70px;
            height: 70px;
            border: 1px dashed #ef4444;
            color: #ef4444;
            text-align: center;
            line-height: 70px;
            font-size: 9pt;
            margin-top: 10px;
        }
    </style>
</head>
<body>

    <div class="header-title">領　収　証</div>

    <table class="meta-table">
        <tr>
            <td style="width: 55%;" class="recipient-box">
                <div>居室: <strong>{{ $resident->room_number }} 号室</strong></div>
                <div class="recipient-name">{{ $resident->name }} 様</div>
                @if($resident->name_kana)
                    <div style="font-size: 9pt; color: #6b7280;">({{ $resident->name_kana }})</div>
                @endif
            </td>
            <td style="width: 45%;" class="issuer-box">
                <div>領収番号: {{ $invoice->receipt_number ?? 'REC-' . str_replace('-', '', $invoice->billing_year_month) . '-' . str_pad($resident->id, 3, '0', STR_PAD_LEFT) }}</div>
                <div>領収日: {{ $invoice->paid_at ? \Carbon\Carbon::parse($invoice->paid_at)->format('Y年m月d日') : now()->format('Y年m月d日') }}</div>
                <br>
                <div style="font-weight: bold; font-size: 11pt;">{{ $facility['name'] ?? config('facility.name') }}</div>
                <div>{{ $facility['operator'] ?? config('facility.operator') }}</div>
                <div>〒{{ $facility['postal_code'] ?? config('facility.postal_code') }} {{ $facility['address'] ?? config('facility.address') }}</div>
                <div>TEL: {{ $facility['phone'] ?? config('facility.phone') }}</div>
                @if(!empty($facility['invoice_registration_number'] ?? config('facility.invoice_registration_number')))
                    <div style="color: #1e40af; font-weight: bold; margin-top: 2px;">
                        登録番号: {{ $facility['invoice_registration_number'] ?? config('facility.invoice_registration_number') }}
                    </div>
                @endif
            </td>
        </tr>
    </table>

    <div class="receipt-amount-box">
        <span class="receipt-amount-label">領収金額 (税込)：</span>
        <span class="receipt-amount-value">¥{{ number_format($tax_info['total_with_tax']) }} -</span>
    </div>

    <div class="proviso">
        但し、{{ substr($invoice->billing_year_month, 0, 4) }}年{{ (int)substr($invoice->billing_year_month, 5, 2) }}月度 施設利用料金（家賃・管理費・自費実費）として、
        上記金額を正に領収いたしました。<br>
        <span style="font-size: 9pt; color: #4b5563;">
            (入金区分: {{ $invoice->payment_method?->getLabel() ?? '銀行振込' }})
        </span>
    </div>

    <div style="font-weight: bold; margin-bottom: 8px;">【内訳明細】</div>
    <table class="breakdown-table">
        <thead>
            <tr>
                <th>内訳科目</th>
                <th style="width: 25%;">金額</th>
                <th style="width: 25%;">税区分</th>
                <th style="width: 25%;">税率</th>
                <th style="width: 25%;">消費税額</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>基本家賃</td>
                <td class="text-right">¥{{ number_format($invoice->rent_subtotal) }}</td>
                <td>非課税</td>
                <td>-</td>
                <td class="text-center">¥0</td>
            </tr>
            <tr>
                <td>基本管理費</td>
                <td class="text-right">¥{{ number_format($invoice->management_fee_subtotal) }}</td>
                <td>課税</td>
                <td>{{ $invoice->tax_rate }}%</td>
                <td class="text-right">¥{{ number_format(round($invoice->management_fee_subtotal * ($invoice->tax_rate / 100))) }}</td>
            </tr>
            <tr>
                <td>自費サービス・立替金</td>
                <td class="text-right">¥{{ number_format($invoice->service_subtotal) }}</td>
                <td>課税・非課税実費</td>
                <td>{{ $invoice->tax_rate }}%</td>
                <td class="text-right">¥{{ number_format(round($invoice->service_subtotal * ($invoice->tax_rate / 100))) }}</td>
            </tr>
            <tr style="background-color: #f9fafb; font-weight: bold;">
                <td class="text-center">合計（税抜）</td>
                <td class="text-right">¥{{ number_format($invoice->rent_subtotal + $invoice->management_fee_subtotal + $invoice->service_subtotal) }}</td>
                <td></td>
                <td></td>
                <td class="text-right">¥{{ number_format($invoice->tax_amount) }}</td>
            </tr>
            <tr style="background-color: #ef4444; color: white; font-weight: bold;">
                <td class="text-center">合計（税込）</td>
                <td class="text-right">¥{{ number_format($invoice->total_amount + $invoice->tax_amount) }}</td>
                <td></td>
                <td></td>
                <td></td>
            </tr>
        </tbody>
    </table>

    <div style="font-size: 8.5pt; color: #6b7280;">
        ※電子的に作成された領収証です。税務申告等の証明書類としてご利用いただけます。
    </div>

</body>
</html>
