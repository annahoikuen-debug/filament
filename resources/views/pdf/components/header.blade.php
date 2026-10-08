@props([
    'facility',
    'template',
    'type' => 'invoice', // 'invoice' or 'receipt'
    'billingYearMonth' => null,
    'invoice' => null,
])

@php
    $headerTitle = $type === 'invoice' ? '御 請 求 書' : '領　収　証';
    $headerSubtitle = $type === 'invoice'
        ? (substr($billingYearMonth, 0, 4) . '年' . (int)substr($billingYearMonth, 5, 2) . '月分 請求書')
        : (substr($billingYearMonth, 0, 4) . '年' . (int)substr($billingYearMonth, 5, 2) . '月度 領収証');
    
    $showRegistrationNumber = !empty($facility['invoice_registration_number']);
    $showProminently = ($template['invoice_compliance']['show_registration_number_prominently'] ?? false);
@endphp

<div class="header">
    <div class="header-left">
        @if($template['show_facility_logo'] ?? false && $template['facility_logo_path'] ?? false)
            <div style="float: left; padding-right: 4mm;">
                <img src="{{ $template['facility_logo_path'] }}" alt="施設ロゴ" style="height: 10mm; width: auto;">
            </div>
        @endif
        <div style="overflow: hidden;">
            <div style="font-weight: 600; font-size: 11pt; color: #1e3a8a; margin-bottom: 1mm;">{{ $facility['name'] }}</div>
            <div style="font-size: 9pt; color: #6b7280;">{{ $facility['operator'] }}</div>
            @if(!empty($facility['seal_path']))
                <div style="margin-top: 2mm; text-align: center;">
                    <img src="{{ $facility['seal_path'] }}" alt="印鑑" style="height: 15mm; width: auto;">
                </div>
            @endif
            @if($showRegistrationNumber && $showProminently)
                <div style="margin-top: 1mm; font-size: 8pt; color: #1e3a8a;">
                    登録番号: {{ $facility['invoice_registration_number'] }}
                </div>
            @endif
        </div>
    </div>
    <div class="header-right">
        <div class="header-title">{{ $headerTitle }}</div>
        <div class="header-subtitle">{{ $headerSubtitle }}</div>
        @if($showRegistrationNumber && !$showProminently)
            <div style="margin-top: 1mm; font-size: 8pt; color: #6b7280;">
                登録番号: {{ $facility['invoice_registration_number'] }}
            </div>
        @endif
    </div>
    <div class="clear"></div>
</div>