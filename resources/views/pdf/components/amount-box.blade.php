@props([
    'taxInfo',
    'type' => 'invoice', // 'invoice' or 'receipt'
    'taxRate' => null,
    'rentSubtotal' => null,
    'managementFeeSubtotal' => null,
    'serviceSubtotal' => null,
])

@php
    $isInvoice = $type === 'invoice';
    $bgColor = $isInvoice ? '#1e3a8a' : '#059669';
    $label = $isInvoice ? 'ご請求金額 (税込)：' : '領収金額 (税込)：';
    $breakdown = $isInvoice
        ? '（内訳: 基本料金 ¥' . number_format($taxInfo['non_taxable'] + $taxInfo['taxable']) . ' + 消費税 ¥' . number_format($taxInfo['tax_amount']) . '）'
        : '（消費税率 ' . ($taxRate ?? $taxInfo['tax_rate']) . '% の内税価格です）';
@endphp

<div class="amount-box" style="background: {{ $bgColor }};">
    <div class="label">{{ $label }}</div>
    <div class="amount tabular-nums">¥{{ number_format($taxInfo['total_with_tax']) }}</div>
    <div class="breakdown">{{ $breakdown }}</div>
</div>