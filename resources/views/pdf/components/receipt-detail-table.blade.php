@props([
    'taxInfo',
    'rentSubtotal',
    'managementFeeSubtotal',
    'serviceSubtotal',
])

<div style="margin-bottom: 6mm;">
    <div class="section-title">【内訳明細】</div>
    <table>
        <thead>
            <tr>
                <th style="text-align: left;">内訳科目</th>
                <th style="text-align: right; width: 24mm;">金額</th>
                <th style="text-align: center; width: 16mm;">税区分</th>
                <th style="text-align: center; width: 16mm;">税率</th>
                <th style="text-align: right; width: 24mm;">消費税額</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>基本家賃</td>
                <td style="text-align: right;" class="tabular-nums">¥{{ number_format($rentSubtotal) }}</td>
                <td style="text-align: center;">非課税</td>
                <td style="text-align: center;">-</td>
                <td style="text-align: center;">¥0</td>
            </tr>
            <tr>
                <td>基本管理費</td>
                <td style="text-align: right;" class="tabular-nums">¥{{ number_format($managementFeeSubtotal) }}</td>
                <td style="text-align: center;">課税</td>
                <td style="text-align: center;">{{ $taxInfo['tax_rate'] }}%</td>
                <td style="text-align: right;" class="tabular-nums">¥{{ number_format(round($managementFeeSubtotal * ($taxInfo['tax_rate'] / 100))) }}</td>
            </tr>
            <tr>
                <td>自費サービス・立替金</td>
                <td style="text-align: right;" class="tabular-nums">¥{{ number_format($serviceSubtotal) }}</td>
                <td style="text-align: center;">課税・非課税実費</td>
                <td style="text-align: center;">{{ $taxInfo['tax_rate'] }}%</td>
                <td style="text-align: right;" class="tabular-nums">¥{{ number_format(round($serviceSubtotal * ($taxInfo['tax_rate'] / 100))) }}</td>
            </tr>
            <tr class="total-row">
                <td style="text-align: center;">合計（税抜）</td>
                <td style="text-align: right;" class="tabular-nums">¥{{ number_format($rentSubtotal + $managementFeeSubtotal + $serviceSubtotal) }}</td>
                <td></td>
                <td></td>
                <td style="text-align: right;" class="tabular-nums">¥{{ number_format($taxInfo['tax_amount']) }}</td>
            </tr>
            <tr class="total-tax-row">
                <td style="text-align: center;">合計（税込）</td>
                <td style="text-align: right;" class="tabular-nums">¥{{ number_format($taxInfo['total_with_tax']) }}</td>
                <td></td>
                <td></td>
                <td></td>
            </tr>
        </tbody>
    </table>
</div>