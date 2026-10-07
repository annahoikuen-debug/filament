<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>請求書 - {{ $invoice->billing_year_month }} - {{ $resident->name }} 様</title>
    <style>
        @page {
            margin: 12mm 15mm 15mm 15mm;
            size: a4 portrait;
        }
        body {
            /* 日本語フォントフォールバック設定 */
            font-family: 'ipaexg', 'ipag', 'Noto Sans JP', 'Hiragino Kaku Gothic ProN', 'Meiryo', sans-serif;
            font-size: 9.5pt;
            color: #333333;
            line-height: 1.4;
        }
        .header-title {
            text-align: center;
            font-size: 18pt;
            letter-spacing: 6px;
            font-weight: bold;
            margin-bottom: 15px;
            border-bottom: 2px solid #1f2937;
            padding-bottom: 4px;
        }
        .meta-table {
            width: 100%;
            margin-bottom: 15px;
        }
        .meta-table td {
            vertical-align: top;
        }
        .recipient-box {
            font-size: 11pt;
            line-height: 1.5;
        }
        .recipient-name {
            font-size: 15pt;
            font-weight: bold;
            text-decoration: underline;
        }
        .issuer-box {
            text-align: right;
            font-size: 8.5pt;
            line-height: 1.35;
        }
        .total-box {
            background-color: #f3f4f6;
            border: 2px solid #374151;
            padding: 8px 12px;
            margin-bottom: 15px;
            text-align: center;
        }
        .total-label {
            font-size: 11pt;
            font-weight: bold;
        }
        .total-amount {
            font-size: 18pt;
            font-weight: bold;
            color: #111827;
        }
        .section-title {
            font-size: 10.5pt;
            font-weight: bold;
            border-left: 4px solid #2563eb;
            padding-left: 8px;
            margin-top: 12px;
            margin-bottom: 6px;
        }
        table.data-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 12px;
        }
        table.data-table th,
        table.data-table td {
            border: 1px solid #d1d5db;
            padding: 5px 8px;
            font-size: 8.5pt;
        }
        table.data-table th {
            background-color: #f9fafb;
            font-weight: bold;
            text-align: center;
        }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .footer-note {
            margin-top: 15px;
            font-size: 8pt;
            color: #4b5563;
            border: 1px dashed #9ca3af;
            padding: 8px;
            line-height: 1.45;
        }
    </style>
</head>
<body>

    <div class="header-title">御 請 求 書</div>

    <table class="meta-table">
        <tr>
            <td style="width: 55%;" class="recipient-box">
                <div>居室: <strong>{{ $resident->room_number }} 号室</strong></div>
                <div class="recipient-name">{{ $resident->name }} 様</div>
                @if($resident->name_kana)
                    <div style="font-size: 8.5pt; color: #6b7280;">({{ $resident->name_kana }})</div>
                @endif
                <div style="margin-top: 8px; font-size: 9pt;">
                    平素は格別のご愛顧を賜り、厚く御礼申し上げます。<br>
                    {{ substr($invoice->billing_year_month, 0, 4) }}年{{ (int)substr($invoice->billing_year_month, 5, 2) }}月度のご利用料金を下記の通りご請求申し上げます。
                </div>
            </td>
            <td style="width: 45%;" class="issuer-box">
                <div>請求番号: INV-{{ str_replace('-', '', $invoice->billing_year_month) }}-{{ str_pad($resident->id, 3, '0', STR_PAD_LEFT) }}</div>
                <div>発行日: {{ now()->format('Y年m月d日') }}</div>
                <div style="font-weight: bold; margin-top: 4px; font-size: 10pt;">{{ $facility['name'] ?? config('facility.name') }}</div>
                <div>{{ $facility['operator'] ?? config('facility.operator') }}</div>
                <div>〒{{ $facility['postal_code'] ?? config('facility.postal_code') }} {{ $facility['address'] ?? config('facility.address') }}</div>
                <div>TEL: {{ $facility['phone'] ?? config('facility.phone') }} / FAX: {{ $facility['fax'] ?? config('facility.fax') }}</div>
                @if(!empty($facility['invoice_registration_number'] ?? config('facility.invoice_registration_number')))
                    <div style="margin-top: 2px; color: #1e40af; font-weight: bold;">
                        登録番号: {{ $facility['invoice_registration_number'] ?? config('facility.invoice_registration_number') }}
                    </div>
                @endif
            </td>
        </tr>
    </table>

    <div class="total-box">
        <span class="total-label">ご請求金額 (税込)：</span>
        <span class="total-amount">¥{{ number_format($tax_info['total_with_tax']) }} -</span>
    </div>

    <!-- 請求内訳サマリー -->
    <div class="section-title">【ご請求サマリー】</div>
    <table class="data-table">
        <thead>
            <tr>
                <th>項目</th>
                <th style="width: 30%;">金額 (税抜)</th>
                <th style="width: 30%;">税率</th>
                <th style="width: 30%;">消費税額</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>基本家賃</td>
                <td class="text-right">¥{{ number_format($tax_info['non_taxable']) }}</td>
                <td class="text-center">非課税</td>
                <td class="text-center">¥0</td>
            </tr>
            <tr>
                <td>基本管理費＋自費サービス</td>
                <td class="text-right">¥{{ number_format($tax_info['taxable']) }}</td>
                <td class="text-center">{{ $tax_info['tax_rate'] }}%</td>
                <td class="text-right">¥{{ number_format($tax_info['tax_amount']) }}</td>
            </tr>
            <tr style="background-color: #f3f4f6; font-weight: bold;">
                <td class="text-center">合計</td>
                <td class="text-right">¥{{ number_format($tax_info['non_taxable'] + $tax_info['taxable']) }}</td>
                <td class="text-center"></td>
                <td class="text-right">¥{{ number_format($tax_info['tax_amount']) }}</td>
            </tr>
        </tbody>
    </table>

    <!-- 自費サービス利用明細 -->
    @if(isset($dailyCharges) && $dailyCharges->count() > 0)
    <div class="section-title">【日々の自費サービス利用明細】</div>
    <table class="data-table">
        <thead>
            <tr>
                <th style="width: 12%;">利用日</th>
                <th style="width: 38%;">品目名</th>
                <th style="width: 14%;">単価</th>
                <th style="width: 8%;">数量</th>
                <th style="width: 14%;">小計</th>
                <th>備考</th>
            </tr>
        </thead>
        <tbody>
            @foreach($dailyCharges as $charge)
            <tr>
                <td class="text-center">{{ \Carbon\Carbon::parse($charge->date)->format('m/d') }}</td>
                <td>{{ $charge->chargeItem->name ?? '自費サービス' }}</td>
                <td class="text-right">¥{{ number_format($charge->unit_price) }}</td>
                <td class="text-center">{{ $charge->quantity }}</td>
                <td class="text-right">¥{{ number_format($charge->subtotal) }}</td>
                <td style="font-size: 7.5pt; color: #6b7280;">{{ $charge->note }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
    @endif

    <div class="footer-note">
        <strong>【お支払いについてのご案内】</strong><br>
        お支払期限：<strong>{{ \Carbon\Carbon::createFromFormat('Y-m', $invoice->billing_year_month)->addMonth()->endOfMonth()->format('Y年m月d日') }}</strong><br>
        お振込先：{{ $facility['bank']['name'] ?? config('facility.bank.name') }} {{ $facility['bank']['branch_name'] ?? config('facility.bank.branch_name') }} {{ $facility['bank']['account_type'] ?? config('facility.bank.account_type') }} {{ $facility['bank']['account_number'] ?? config('facility.bank.account_number') }}<br>
        口座名義：{{ $facility['bank']['account_holder'] ?? config('facility.bank.account_holder') }}<br>
        ※口座振替をご利用の方は、翌月{{ $facility['billing']['direct_debit_day'] ?? config('facility.billing.direct_debit_day') }}日にお引き落としとなります。
    </div>

</body>
</html>
