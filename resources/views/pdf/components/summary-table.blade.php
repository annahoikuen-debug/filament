@props([
    'taxInfo',
])

<div style="margin-bottom: 6mm;">
    <div class="section-title">【ご請求サマリー】</div>
    <table>
        <thead>
            <tr>
                <th style="text-align: left;">項目</th>
                <th style="text-align: right; width: 32mm;">金額 (税抜)</th>
                <th style="text-align: center; width: 24mm;">税率</th>
                <th style="text-align: right; width: 32mm;">消費税額</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>基本家賃</td>
                <td style="text-align: right;" class="tabular-nums">¥{{ number_format($taxInfo['non_taxable']) }}</td>
                <td style="text-align: center;">非課税</td>
                <td style="text-align: center;">¥0</td>
            </tr>
            <tr>
                <td>基本管理費＋自費サービス</td>
                <td style="text-align: right;" class="tabular-nums">¥{{ number_format($taxInfo['taxable']) }}</td>
                <td style="text-align: center;">{{ $taxInfo['tax_rate'] }}%</td>
                <td style="text-align: right;" class="tabular-nums">¥{{ number_format($taxInfo['tax_amount']) }}</td>
            </tr>
            <tr class="total-row">
                <td style="text-align: center;">合計</td>
                <td style="text-align: right;" class="tabular-nums">¥{{ number_format($taxInfo['non_taxable'] + $taxInfo['taxable']) }}</td>
                <td></td>
                <td style="text-align: right;" class="tabular-nums">¥{{ number_format($taxInfo['tax_amount']) }}</td>
            </tr>
        </tbody>
    </table>
</div>