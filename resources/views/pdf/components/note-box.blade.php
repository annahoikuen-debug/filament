@props([
    'billingYearMonth',
    'paymentMethodLabel',
])

<div class="note-box">
    <div class="label">但し書き</div>
    <p class="text">
        但し、{{ substr($billingYearMonth, 0, 4) }}年{{ (int)substr($billingYearMonth, 5, 2) }}月度 施設利用料金（家賃・管理費・自費実費）として、<br>
        上記金額を正に領収いたしました。
        <span class="sub-text" style="display: block; margin-top: 1mm;">
            (入金区分: {{ $paymentMethodLabel }})
        </span>
    </p>
</div>