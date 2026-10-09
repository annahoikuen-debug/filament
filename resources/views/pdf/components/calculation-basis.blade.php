@php
    $calculationBasis = $data['calculation_basis'] ?? null;
    $showCalculationBasis = $data['show_calculation_basis'] ?? true;
@endphp

@if($showCalculationBasis && $calculationBasis)
<div class="page-break"></div>

<div class="calculation-basis-section">
    <div class="section-title">【計算根拠】</div>

    @php
        $rent = $calculationBasis['rent'] ?? [];
        $managementFee = $calculationBasis['management_fee'] ?? [];
        $taxBreakdown = $calculationBasis['tax_breakdown'] ?? [];
        $total = $calculationBasis['total'] ?? [];
        $isProrated = ($rent['is_prorated'] ?? false) || ($managementFee['is_prorated'] ?? false);
    @endphp

    @if($isProrated)
    <div class="proration-note" style="margin-bottom: 4mm; padding: 3mm; background: #fef3c7; border: 1px solid #f59e0b; border-radius: 2mm;">
        <strong>月中途入居・退去のため日割り計算を適用</strong>
    </div>
    <div class="proration-table" style="margin-bottom: 6mm;">
        <table style="width: 100%; border-collapse: collapse;">
            <thead>
                <tr>
                    <th style="text-align: left; padding: 3mm; border: 1px solid #e5e7eb; background: #3b82f6; color: white;">項目</th>
                    <th style="text-align: right; padding: 3mm; border: 1px solid #e5e7eb; background: #3b82f6; color: white; width: 30mm;">月額</th>
                    <th style="text-align: center; padding: 3mm; border: 1px solid #e5e7eb; background: #3b82f6; color: white; width: 50mm;">計算式</th>
                    <th style="text-align: right; padding: 3mm; border: 1px solid #e5e7eb; background: #3b82f6; color: white; width: 30mm;">按分額</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td style="text-align: left; padding: 3mm; border: 1px solid #e5e7eb;">基本家賃（月額）</td>
                    <td style="text-align: right; padding: 3mm; border: 1px solid #e5e7eb;" class="tabular-nums">¥{{ number_format($rent['monthly_amount'] ?? 0) }}</td>
                    <td style="text-align: center; padding: 3mm; border: 1px solid #e5e7eb;">月日数：{{ $rent['days_in_month'] ?? 0 }}日 × 在籍日数：{{ $rent['living_days'] ?? 0 }}日</td>
                    <td style="text-align: right; padding: 3mm; border: 1px solid #e5e7eb;" class="tabular-nums">¥{{ number_format($rent['prorated_amount'] ?? 0) }}</td>
                </tr>
                <tr>
                    <td style="text-align: left; padding: 3mm; border: 1px solid #e5e7eb;">基本管理費（月額）</td>
                    <td style="text-align: right; padding: 3mm; border: 1px solid #e5e7eb;" class="tabular-nums">¥{{ number_format($managementFee['monthly_amount'] ?? 0) }}</td>
                    <td style="text-align: center; padding: 3mm; border: 1px solid #e5e7eb;">月日数：{{ $managementFee['days_in_month'] ?? 0 }}日 × 在籍日数：{{ $managementFee['living_days'] ?? 0 }}日</td>
                    <td style="text-align: right; padding: 3mm; border: 1px solid #e5e7eb;" class="tabular-nums">¥{{ number_format($managementFee['prorated_amount'] ?? 0) }}</td>
                </tr>
            </tbody>
        </table>
    </div>
    @else
    <div class="no-proration-note" style="margin-bottom: 6mm; padding: 3mm; background: #d1fae5; border: 1px solid #10b981; border-radius: 2mm;">
        <strong>満月在籍のため月額そのまま適用</strong>
    </div>
    @endif

    <!-- 消費税内訳 -->
    <div class="tax-breakdown" style="margin-bottom: 6mm;">
        <div style="font-weight: 600; color: #1e3a8a; border-bottom: 2px solid #1e3a8a; padding-bottom: 1mm; margin-bottom: 3mm;">消費税内訳</div>
        <table style="width: 100%; border-collapse: collapse;">
            <thead>
                <tr>
                    <th style="text-align: left; padding: 3mm; border: 1px solid #e5e7eb; background: #3b82f6; color: white;">税区分</th>
                    <th style="text-align: right; padding: 3mm; border: 1px solid #e5e7eb; background: #3b82f6; color: white; width: 30mm;">課税対象額</th>
                    <th style="text-align: center; padding: 3mm; border: 1px solid #e5e7eb; background: #3b82f6; color: white; width: 20mm;">税率</th>
                    <th style="text-align: right; padding: 3mm; border: 1px solid #e5e7eb; background: #3b82f6; color: white; width: 30mm;">税額</th>
                </tr>
            </thead>
            <tbody>
                @if(($taxBreakdown['standard']['taxable_amount'] ?? 0) > 0)
                <tr>
                    <td style="text-align: left; padding: 3mm; border: 1px solid #e5e7eb;">標準税率対象</td>
                    <td style="text-align: right; padding: 3mm; border: 1px solid #e5e7eb;" class="tabular-nums">¥{{ number_format($taxBreakdown['standard']['taxable_amount']) }}</td>
                    <td style="text-align: center; padding: 3mm; border: 1px solid #e5e7eb;">{{ $taxBreakdown['standard']['rate'] }}%</td>
                    <td style="text-align: right; padding: 3mm; border: 1px solid #e5e7eb;" class="tabular-nums">¥{{ number_format($taxBreakdown['standard']['tax_amount']) }}</td>
                </tr>
                @endif
                @if(($taxBreakdown['reduced']['taxable_amount'] ?? 0) > 0)
                <tr>
                    <td style="text-align: left; padding: 3mm; border: 1px solid #e5e7eb;">軽減税率対象</td>
                    <td style="text-align: right; padding: 3mm; border: 1px solid #e5e7eb;" class="tabular-nums">¥{{ number_format($taxBreakdown['reduced']['taxable_amount']) }}</td>
                    <td style="text-align: center; padding: 3mm; border: 1px solid #e5e7eb;">{{ $taxBreakdown['reduced']['rate'] }}%</td>
                    <td style="text-align: right; padding: 3mm; border: 1px solid #e5e7eb;" class="tabular-nums">¥{{ number_format($taxBreakdown['reduced']['tax_amount']) }}</td>
                </tr>
                @endif
                <tr>
                    <td style="text-align: left; padding: 3mm; border: 1px solid #e5e7eb;">非課税（家賃等）</td>
                    <td style="text-align: right; padding: 3mm; border: 1px solid #e5e7eb;" class="tabular-nums">¥{{ number_format($taxBreakdown['non_taxable']['amount'] ?? 0) }}</td>
                    <td style="text-align: center; padding: 3mm; border: 1px solid #e5e7eb;">非課税</td>
                    <td style="text-align: right; padding: 3mm; border: 1px solid #e5e7eb;" class="tabular-nums">¥0</td>
                </tr>
            </tbody>
        </table>
    </div>

    <!-- 合計 -->
    <div class="total-section" style="margin-top: 4mm;">
        <table style="width: 100%; border-collapse: collapse;">
            <tbody>
                <tr style="background: #f3f4f6;">
                    <td style="text-align: left; padding: 4mm; border: 1px solid #e5e7eb; font-weight: 600;">小計（税抜）</td>
                    <td style="text-align: right; padding: 4mm; border: 1px solid #e5e7eb; font-weight: 600;" class="tabular-nums">¥{{ number_format($total['subtotal'] ?? 0) }}</td>
                </tr>
                <tr style="background: #f3f4f6;">
                    <td style="text-align: left; padding: 4mm; border: 1px solid #e5e7eb; font-weight: 600;">消費税額</td>
                    <td style="text-align: right; padding: 4mm; border: 1px solid #e5e7eb; font-weight: 600;" class="tabular-nums">¥{{ number_format($total['tax_amount'] ?? 0) }}</td>
                </tr>
                <tr style="background: #1e3a8a; color: white;">
                    <td style="text-align: left; padding: 4mm; border: 1px solid #e5e7eb; font-weight: 700;">合計（税込）</td>
                    <td style="text-align: right; padding: 4mm; border: 1px solid #e5e7eb; font-weight: 700;" class="tabular-nums">¥{{ number_format($total['total_with_tax'] ?? 0) }}</td>
                </tr>
            </tbody>
        </table>
    </div>
</div>
@endif