<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>請求書 - {{ $invoice->billing_year_month }} - {{ $resident->name }} 様</title>
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
        
        .page-break {
            page-break-after: always;
        }
    </style>
</head>
<body>
    @include('invoices.partials.header', ['type' => 'invoice', 'template' => $template, 'invoice' => $invoice, 'resident' => $resident, 'facility' => $facility])
    
    <div class="page-content">
        <!-- 受取人・発行者セクション -->
        <div style="overflow: hidden; margin-bottom: 6mm;">
            <!-- 受取人情報 -->
            <div style="float: left; width: 47%; border: 1px solid #e5e7eb; padding: 4mm; background-color: #fafafa;">
                <div style="font-weight: 600; color: #1e3a8a; margin-bottom: 2mm;">ご請求先</div>
                <div style="margin-bottom: 2mm;">
                    <div style="margin-bottom: 2mm;">
                        <span>[居室] </span>
                        <strong style="font-weight: 500;">{{ $resident->room_number }} 号室</strong>
                    </div>
                    <div style="font-weight: 700; font-size: 12pt; text-decoration: underline;">{{ $resident->name }} 様</div>
                    @if($resident->name_kana)
                        <div style="font-size: 9pt; color: #6b7280;">({{ $resident->name_kana }})</div>
                    @endif
                    <div style="font-size: 9pt; color: #6b7280; margin-top: 2mm;">
                        平素は格別のご愛顧を賜り、厚く御礼申し上げます。<br>
                        {{ substr($invoice->billing_year_month, 0, 4) }}年{{ (int)substr($invoice->billing_year_month, 5, 2) }}月度のご利用料金を下記の通りご請求申し上げます。
                    </div>
                </div>
            </div>
            
            <!-- 発行者情報 -->
            <div style="float: right; width: 47%; border: 1px solid #e5e7eb; padding: 4mm; background-color: #fafafa;">
                <div style="font-weight: 600; color: #1e3a8a; margin-bottom: 2mm;">発行者情報</div>
                <div style="font-size: 9pt;">
                    <div style="margin-bottom: 2mm;">請求番号: INV-{{ str_replace('-', '', $invoice->billing_year_month) }}-{{ str_pad($resident->id, 3, '0', STR_PAD_LEFT) }}</div>
                    <div style="margin-bottom: 2mm;">発行日: {{ now()->format('Y年m月d日') }}</div>
                    <div style="margin-bottom: 2mm; font-weight: 500;">{{ $facility['name'] ?? config('facility.name') }}</div>
                    <div style="margin-bottom: 2mm;">{{ $facility['operator'] ?? config('facility.operator') }}</div>
                    <div style="margin-bottom: 2mm;">〒{{ $facility['postal_code'] ?? config('facility.postal_code') }} {{ $facility['address'] ?? config('facility.address') }}</div>
                    <div style="margin-bottom: 2mm;">
                        <span>TEL:</span>
                        <span style="font-weight: 500;">{{ $facility['phone'] ?? config('facility.phone') }}</span>
                        <span style="margin-left: 2mm; margin-right: 2mm;">/</span>
                        <span>FAX:</span>
                        <span style="font-weight: 500;">{{ $facility['fax'] ?? config('facility.fax') }}</span>
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

        <!-- 合計金額エリア -->
        <div style="margin-bottom: 6mm; padding: 6mm; border-radius: 0.5rem; background-color: #1e3a8a; color: #ffffff; text-align: center;">
            <div style="font-size: 12pt; font-weight: 500; margin-bottom: 2mm;">ご請求金額 (税込)：</div>
            <div style="font-size: 24pt; font-weight: 700;" class="tabular-nums">¥{{ number_format($tax_info['total_with_tax']) }}</div>
            <div style="font-size: 9pt; margin-top: 2mm;">（内訳: 基本料金 ¥{{ number_format($tax_info['non_taxable'] + $tax_info['taxable']) }} + 消費税 ¥{{ number_format($tax_info['tax_amount']) }}）</div>
        </div>

        <!-- 請求内訳サマリー -->
        <div style="margin-bottom: 6mm;">
            <div style="font-weight: 600; color: #1e3a8a; font-size: 11pt; margin-bottom: 3mm; border-bottom: 2px solid #1e3a8a; padding-bottom: 1mm;">【ご請求サマリー】</div>
            <table>
                <thead>
                    <tr style="background-color: #3b82f6; color: #ffffff;">
                        <th style="padding: 4mm; text-align: left;">項目</th>
                        <th style="padding: 4mm; text-align: right; width: 32mm;">金額 (税抜)</th>
                        <th style="padding: 4mm; text-align: center; width: 24mm;">税率</th>
                        <th style="padding: 4mm; text-align: right; width: 32mm;">消費税額</th>
                    </tr>
                </thead>
                <tbody>
                    <tr style="border-bottom: 1px solid #e5e7eb;">
                        <td style="padding: 4mm;">基本家賃</td>
                        <td style="padding: 4mm; text-align: right;" class="tabular-nums">¥{{ number_format($tax_info['non_taxable']) }}</td>
                        <td style="padding: 4mm; text-align: center;">非課税</td>
                        <td style="padding: 4mm; text-align: center;">¥0</td>
                    </tr>
                    <tr style="background-color: #fafafa; border-bottom: 1px solid #e5e7eb;">
                        <td style="padding: 4mm;">基本管理費＋自費サービス</td>
                        <td style="padding: 4mm; text-align: right;" class="tabular-nums">¥{{ number_format($tax_info['taxable']) }}</td>
                        <td style="padding: 4mm; text-align: center;">{{ $tax_info['tax_rate'] }}%</td>
                        <td style="padding: 4mm; text-align: right;" class="tabular-nums">¥{{ number_format($tax_info['tax_amount']) }}</td>
                    </tr>
                    <tr style="font-weight: 700; background-color: #3b82f6; color: #ffffff;">
                        <td style="padding: 4mm; text-align: center;">合計</td>
                        <td style="padding: 4mm; text-align: right;" class="tabular-nums">¥{{ number_format($tax_info['non_taxable'] + $tax_info['taxable']) }}</td>
                        <td style="padding: 4mm;"></td>
                        <td style="padding: 4mm; text-align: right;" class="tabular-nums">¥{{ number_format($tax_info['tax_amount']) }}</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- ページブレーク：明細は次のページから開始 -->
        @if(isset($dailyCharges) && $dailyCharges->count() > 0)
        <div class="page-break"></div>
        @endif

        <!-- 自費サービス利用明細 -->
        @if(isset($dailyCharges) && $dailyCharges->count() > 0)
        <div style="margin-bottom: 6mm;">
            <div style="font-weight: 600; color: #1e3a8a; font-size: 11pt; margin-bottom: 3mm; border-bottom: 2px solid #1e3a8a; padding-bottom: 1mm;">【日々の自費サービス利用明細】</div>
            <table>
                <thead>
                    <tr style="background-color: #3b82f6; color: #ffffff;">
                        <th style="padding: 4mm; text-align: center; width: 16mm;">利用日</th>
                        <th style="padding: 4mm; text-align: left; width: 48mm;">品目名</th>
                        <th style="padding: 4mm; text-align: center; width: 20mm;">単価</th>
                        <th style="padding: 4mm; text-align: center; width: 12mm;">数量</th>
                        <th style="padding: 4mm; text-align: right; width: 20mm;">小計</th>
                        <th style="padding: 4mm; text-align: left;">備考</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($dailyCharges as $charge)
                    <tr style="{{ $loop->even ? 'background-color: #ffffff;' : 'background-color: #fafafa;' }} border-bottom: 1px solid #e5e7eb;">
                        <td style="padding: 4mm; text-align: center;">{{ \Carbon\Carbon::parse($charge->date)->format('m/d') }}</td>
                        <td style="padding: 4mm;">{{ $charge->chargeItem->name ?? '自費サービス' }}</td>
                        <td style="padding: 4mm; text-align: right;" class="tabular-nums">¥{{ number_format($charge->unit_price) }}</td>
                        <td style="padding: 4mm; text-align: center;">{{ $charge->quantity }}</td>
                        <td style="padding: 4mm; text-align: right;" class="tabular-nums">¥{{ number_format($charge->subtotal) }}</td>
                        <td style="padding: 4mm; font-size: 9pt; color: #6b7280;">{{ $charge->note }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @endif

        <!-- フッター情報 -->
        @include('invoices.partials.footer', ['type' => 'invoice', 'template' => $template, 'invoice' => $invoice, 'resident' => $resident, 'facility' => $facility])
    </div>
</body>
</html>