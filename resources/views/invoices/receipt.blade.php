<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>領収証 - {{ $invoice->receipt_number }} - {{ $resident->name }} 様</title>
    <style>
        /* Margins and paper size set via InvoicePdfService */
        
        body {
            font-family: {{ $template['font_family'] ?? "'Yu Mincho', 'YuMincho', 'Meiryo', 'MS Gothic', 'Noto Sans JP', sans-serif" }};
            font-size: {{ ($template['font_size'] ?? 10.5) }}pt;
            color: #111827;
            line-height: {{ $template['line_height'] ?? 1.6 }};
        }
        
        .tabular-nums {
            font-family: {{ $template['font_family_numbers'] ?? "'Yu Gothic', 'Meiryo', 'MS Gothic', 'Noto Sans JP', sans-serif" }};
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        th, td {
            padding: 4mm 6mm;
            border: 1px solid #e5e7eb;
            vertical-align: middle;
        }
        
        th {
            background-color: #f3f4f6;
            font-weight: 600;
        }
    </style>
</head>
<body>
    @include('invoices.partials.header', ['type' => 'receipt', 'template' => $template, 'invoice' => $invoice, 'resident' => $resident, 'facility' => $facility])
    
    <div class="page-content">
        <!-- 受取人・発行者セクション -->
        <div style="overflow: hidden; margin-bottom: 6mm;">
            <!-- 受取人情報 -->
            <div style="float: left; width: 47%; border: 1px solid #e5e7eb; padding: 4mm; background-color: #fafafa;">
                <div style="font-weight: 600; color: #1e3a8a; margin-bottom: 2mm;">ご入居者様</div>
                <div style="margin-bottom: 2mm;">
                    <div style="margin-bottom: 2mm;">
                        <span>[居室] </span>
                        <strong style="font-weight: 500;">{{ $resident->room_number }} 号室</strong>
                    </div>
                    <div style="font-weight: 700; font-size: 12pt; text-decoration: underline;">{{ $resident->name }} 様</div>
                    @if($resident->name_kana)
                        <div style="font-size: 9pt; color: #6b7280;">({{ $resident->name_kana }})</div>
                    @endif
                </div>
            </div>
            
            <!-- 発行者情報 -->
            <div style="float: right; width: 47%; border: 1px solid #e5e7eb; padding: 4mm; background-color: #fafafa;">
                <div style="font-weight: 600; color: #1e3a8a; margin-bottom: 2mm;">発行者情報</div>
                <div style="font-size: 9pt;">
                    <div style="margin-bottom: 2mm;">領収番号: {{ $invoice->receipt_number ?? 'REC-' . str_replace('-', '', $invoice->billing_year_month) . '-' . str_pad($resident->id, 3, '0', STR_PAD_LEFT) }}</div>
                    <div style="margin-bottom: 2mm;">領収日: {{ $invoice->paid_at ? \Carbon\Carbon::parse($invoice->paid_at)->format('Y年m月d日') : now()->format('Y年m月d日') }}</div>
                    <div style="margin-bottom: 2mm; font-weight: 500;">{{ $facility['name'] ?? config('facility.name') }}</div>
                    <div style="margin-bottom: 2mm;">{{ $facility['operator'] ?? config('facility.operator') }}</div>
                    <div style="margin-bottom: 2mm;">〒{{ $facility['postal_code'] ?? config('facility.postal_code') }} {{ $facility['address'] ?? config('facility.address') }}</div>
                    <div style="margin-bottom: 2mm;">
                        <span>TEL:</span>
                        <span style="font-weight: 500;">{{ $facility['phone'] ?? config('facility.phone') }}</span>
                    </div>
                    @if(!empty($facility['invoice_registration_number'] ?? config('facility.invoice_registration_number')))
                        <div style="margin-top: 2mm; color: #1e3a8a; font-weight: 500;">
                            登録番号: {{ $facility['invoice_registration_number'] ?? config('facility.invoice_registration_number') }}
                        </div>
                    @endif
                </div>
            </div>
            <div style="clear: both;"></div>
        </div>

        <!-- 領収金額エリア（緑系で安心感） -->
        <div style="margin-bottom: 6mm; padding: 6mm; border-radius: 0.5rem; background-color: #059669; color: #ffffff; text-align: center;">
            <div style="font-size: 12pt; font-weight: 500; margin-bottom: 2mm;">領収金額 (税込)：</div>
            <div style="font-size: 24pt; font-weight: 700;" class="tabular-nums">¥{{ number_format($tax_info['total_with_tax']) }}</div>
            <div style="font-size: 9pt; margin-top: 2mm;">（消費税率 {{ $invoice->tax_rate }}% の内税価格です）</div>
        </div>

        <!-- 但し書き -->
        <div style="margin-bottom: 6mm; padding: 4mm; background-color: #fafafa; border-left: 4px solid #1e3a8a;">
            <div style="font-weight: 600; color: #1e3a8a; margin-bottom: 2mm;">但し書き</div>
            <p style="font-size: 9pt;">
                但し、{{ substr($invoice->billing_year_month, 0, 4) }}年{{ (int)substr($invoice->billing_year_month, 5, 2) }}月度 施設利用料金（家賃・管理費・自費実費）として、
                上記金額を正に領収いたしました。<br>
                <span style="font-size: 8pt; color: #6b7280; display: block; margin-top: 1mm;">
                    (入金区分: {{ $invoice->payment_method?->getLabel() ?? '銀行振込' }})
                </span>
            </p>
        </div>

        <!-- 内訳明細 -->
        <div style="margin-bottom: 6mm;">
            <div style="font-weight: 600; color: #1e3a8a; font-size: 11pt; margin-bottom: 3mm; border-bottom: 2px solid #1e3a8a; padding-bottom: 1mm;">【内訳明細】</div>
            <table>
                <thead>
                    <tr style="background-color: #3b82f6; color: #ffffff;">
                        <th style="padding: 4mm; text-align: left;">内訳科目</th>
                        <th style="padding: 4mm; text-align: right; width: 24mm;">金額</th>
                        <th style="padding: 4mm; text-align: center; width: 16mm;">税区分</th>
                        <th style="padding: 4mm; text-align: center; width: 16mm;">税率</th>
                        <th style="padding: 4mm; text-align: right; width: 24mm;">消費税額</th>
                    </tr>
                </thead>
                <tbody>
                    <tr style="border-bottom: 1px solid #e5e7eb;">
                        <td style="padding: 4mm;">基本家賃</td>
                        <td style="padding: 4mm; text-align: right;" class="tabular-nums">¥{{ number_format($invoice->rent_subtotal) }}</td>
                        <td style="padding: 4mm; text-align: center;">非課税</td>
                        <td style="padding: 4mm; text-align: center;">-</td>
                        <td style="padding: 4mm; text-align: center;">¥0</td>
                    </tr>
                    <tr style="background-color: #fafafa; border-bottom: 1px solid #e5e7eb;">
                        <td style="padding: 4mm;">基本管理費</td>
                        <td style="padding: 4mm; text-align: right;" class="tabular-nums">¥{{ number_format($invoice->management_fee_subtotal) }}</td>
                        <td style="padding: 4mm; text-align: center;">課税</td>
                        <td style="padding: 4mm; text-align: center;">{{ $invoice->tax_rate }}%</td>
                        <td style="padding: 4mm; text-align: right;" class="tabular-nums">¥{{ number_format(round($invoice->management_fee_subtotal * ($invoice->tax_rate / 100))) }}</td>
                    </tr>
                    <tr style="border-bottom: 1px solid #e5e7eb;">
                        <td style="padding: 4mm;">自費サービス・立替金</td>
                        <td style="padding: 4mm; text-align: right;" class="tabular-nums">¥{{ number_format($invoice->service_subtotal) }}</td>
                        <td style="padding: 4mm; text-align: center;">課税・非課税実費</td>
                        <td style="padding: 4mm; text-align: center;">{{ $invoice->tax_rate }}%</td>
                        <td style="padding: 4mm; text-align: right;" class="tabular-nums">¥{{ number_format(round($invoice->service_subtotal * ($invoice->tax_rate / 100))) }}</td>
                    </tr>
                    <tr style="font-weight: 700; background-color: #3b82f6; color: #ffffff;">
                        <td style="padding: 4mm; text-align: center;">合計（税抜）</td>
                        <td style="padding: 4mm; text-align: right;" class="tabular-nums">¥{{ number_format($invoice->rent_subtotal + $invoice->management_fee_subtotal + $invoice->service_subtotal) }}</td>
                        <td style="padding: 4mm;"></td>
                        <td style="padding: 4mm;"></td>
                        <td style="padding: 4mm; text-align: right;" class="tabular-nums">¥{{ number_format($invoice->tax_amount) }}</td>
                    </tr>
                    <tr style="font-weight: 700; background-color: #059669; color: #ffffff;">
                        <td style="padding: 4mm; text-align: center;">合計（税込）</td>
                        <td style="padding: 4mm; text-align: right;" class="tabular-nums">¥{{ number_format($invoice->total_amount + $invoice->tax_amount) }}</td>
                        <td style="padding: 4mm;"></td>
                        <td style="padding: 4mm;"></td>
                        <td style="padding: 4mm;"></td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- フッター注意書き -->
        @include('invoices.partials.footer', ['type' => 'receipt', 'template' => $template, 'invoice' => $invoice, 'resident' => $resident, 'facility' => $facility])
    </div>
</body>
</html>