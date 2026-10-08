@props([
    'dailyCharges',
])

@if(!empty($dailyCharges))
<div style="margin-bottom: 6mm;">
    <div class="section-title">【日々の自費サービス利用明細】</div>
    <table>
        <thead>
            <tr>
                <th style="text-align: center; width: 16mm;">利用日</th>
                <th style="text-align: left; width: 48mm;">品目名</th>
                <th style="text-align: center; width: 20mm;">単価</th>
                <th style="text-align: center; width: 12mm;">数量</th>
                <th style="text-align: right; width: 20mm;">小計</th>
                <th style="text-align: left;">備考</th>
            </tr>
        </thead>
        <tbody>
            @foreach($dailyCharges as $charge)
            <tr style="{{ $loop->even ? 'background-color: #ffffff;' : 'background-color: #fafafa;' }} border-bottom: 1px solid #e5e7eb;">
                <td style="text-align: center;">{{ $charge['date'] }}</td>
                <td>{{ $charge['item_name'] }}</td>
                <td style="text-align: right;" class="tabular-nums">¥{{ number_format($charge['unit_price'] ?? 0) }}</td>
                <td style="text-align: center;">{{ $charge['quantity'] ?? 0 }}</td>
                <td style="text-align: right;" class="tabular-nums">¥{{ number_format($charge['subtotal'] ?? 0) }}</td>
                <td style="font-size: 9pt; color: #6b7280;">{{ $charge['note'] }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
</div>
@endif