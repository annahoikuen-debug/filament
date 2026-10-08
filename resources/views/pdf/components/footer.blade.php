@props([
    'facility',
    'template',
    'type' => 'invoice', // 'invoice' or 'receipt'
    'billingYearMonth' => null,
    'invoice' => null,
])

<div class="footer">
    <div class="footer-left">
        @if($type === 'invoice')
            <div style="margin-bottom: 2mm;">
                <span class="footer-label">[支払期限] </span>
                <span class="footer-value">{{ \Carbon\Carbon::createFromFormat('Y-m', $billingYearMonth)->addMonth()->endOfMonth()->format('Y年m月d日') }}</span>
            </div>
            <div style="margin-bottom: 2mm;">
                <span class="footer-label">[振込先] </span>
                <span>{{ $facility['bank']['name'] }} {{ $facility['bank']['branch_name'] }} {{ $facility['bank']['account_type'] }} {{ $facility['bank']['account_number'] }}</span>
            </div>
            <div style="margin-bottom: 2mm;">
                <span class="footer-label">[口座名義] </span>
                <span>{{ $facility['bank']['account_holder'] }}</span>
            </div>
            <div class="footer-note">
                <span class="footer-label">[口座振替] </span>
                <span>※口座振替をご利用の方は、翌月{{ $facility['billing']['direct_debit_day'] ?? 27 }}日にお引き落としとなります。</span>
            </div>
        @else
            <div style="font-size: 8pt; color: #6b7280;">
                ※電子的に作成された領収証です。税務申告等の証明書類としてご利用いただけます。
            </div>
        @endif
    </div>

    @if(($template['show_qr_code'] ?? false) && !empty($template['qr_code_data']))
    <div class="footer-right">
        @if($type === 'invoice')
            <div class="qr-code-label">振込用QRコード</div>
            <div class="qr-code">
                <img src="{{ $template['qr_code_data'] }}" alt="QRコード">
            </div>
            <div class="qr-code-note">スマートフォンで読み取って振込ができます</div>
        @else
            <div class="qr-code-label">領収書検証用QRコード</div>
            <div class="qr-code">
                <img src="{{ $template['qr_code_data'] }}" alt="QRコード">
            </div>
            <div class="qr-code-note">スマートフォンで読み取って領収書の真偽を確認できます</div>
        @endif
    </div>
    @endif
    <div class="clear"></div>
</div>