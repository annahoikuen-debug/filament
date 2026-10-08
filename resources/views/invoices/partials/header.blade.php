@php
    // ヘッダータイプを決定（請求書または領収書）
    $headerTitle = $type === 'invoice' ? '御 請 求 書' : '領　収　証';
    $headerSubtitle = $type === 'invoice' 
        ? $invoice->billing_year_month . '分 請求書' 
        : substr($invoice->billing_year_month, 0, 4) . '年' . (int)substr($invoice->billing_year_month, 5, 2) . '月度 領収証';
@endphp

<!-- ヘッダー部分 -->
<div style="margin-bottom: 6mm; overflow: hidden;">
    <div style="float: left; width: 60%;">
        <div style="overflow: hidden;">
            @if($template['show_facility_logo'] && $template['facility_logo_path'])
                <div style="float: left; padding-right: 4mm;">
                    <img src="{{ $template['facility_logo_path'] }}" alt="施設ロゴ" style="height: 10mm; width: auto;">
                </div>
            @endif
            <div style="overflow: hidden;">
                <div style="font-weight: 600; font-size: 11pt; color: #1e3a8a; margin-bottom: 1mm;">{{ $facility['name'] ?? config('facility.name') }}</div>
                <div style="font-size: 9pt; color: #6b7280;">{{ $facility['operator'] ?? config('facility.operator') }}</div>
                @if(!empty($facility['seal_path']))
                    <div style="margin-top: 2mm; text-align: center;">
                        <img src="{{ $facility['seal_path'] }}" alt="印鑑" style="height: 15mm; width: auto;">
                    </div>
                @endif
                @if(!empty($facility['invoice_registration_number'] ?? config('facility.invoice_registration_number')) && ($template['invoice_compliance']['show_registration_number_prominently'] ?? false))
                    <div style="margin-top: 1mm; font-size: 8pt; color: #1e3a8a;">
                        登録番号: {{ $facility['invoice_registration_number'] ?? config('facility.invoice_registration_number') }}
                    </div>
                @endif
            </div>
        </div>
    </div>
    <div style="float: right; width: 40%; text-align: right;">
        <div style="font-size: 18pt; font-weight: 700; color: #1e3a8a; letter-spacing: 2px;">{{ $headerTitle }}</div>
        <div style="font-size: 9pt; color: #6b7280;">{{ $headerSubtitle }}</div>
        @if(!empty($facility['invoice_registration_number'] ?? config('facility.invoice_registration_number')) && !(($template['invoice_compliance']['show_registration_number_prominently'] ?? false)))
            <div style="margin-top: 1mm; font-size: 8pt; color: #6b7280;">
                登録番号: {{ $facility['invoice_registration_number'] ?? config('facility.invoice_registration_number') }}
            </div>
        @endif
    </div>
    <div style="clear: both;"></div>
</div>