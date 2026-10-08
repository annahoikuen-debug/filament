<!-- フッター情報 -->
<div style="margin-top: 8mm; padding-top: 6mm; border-top: 1px solid #f3f4f6; overflow: hidden;">
    <div style="float: left; width: 58%; padding-right: 4mm;">
        @if($type === 'invoice')
            <div style="margin-bottom: 2mm;">
                <span>[支払期限] </span>
                <span><strong>{{ \Carbon\Carbon::createFromFormat('Y-m', $invoice->billing_year_month)->addMonth()->endOfMonth()->format('Y年m月d日') }}</strong></span>
            </div>
            <div style="margin-bottom: 2mm;">
                <span>[振込先] </span>
                <span>{{ $facility['bank']['name'] ?? config('facility.bank.name') }} {{ $facility['bank']['branch_name'] ?? config('facility.bank.branch_name') }} {{ $facility['bank']['account_type'] ?? config('facility.bank.account_type') }} {{ $facility['bank']['account_number'] ?? config('facility.bank.account_number') }}</span>
            </div>
            <div style="margin-bottom: 2mm;">
                <span>[口座名義] </span>
                <span>{{ $facility['bank']['account_holder'] ?? config('facility.bank.account_holder') }}</span>
            </div>
            <div style="font-size: 8pt; color: #6b7280; margin-top: 2mm;">
                <span>[口座振替] </span>
                <span>※口座振替をご利用の方は、翌月{{ $facility['billing']['direct_debit_day'] ?? config('facility.billing.direct_debit_day') }}日にお引き落としとなります。</span>
            </div>
        @else
            <div style="font-size: 8pt; color: #6b7280;">
                ※電子的に作成された領収証です。税務申告等の証明書類としてご利用いただけます。
            </div>
        @endif
    </div>
    
    @if($template['show_qr_code'] && !empty($template['qr_code_data']))
    <div style="float: right; width: 40%;">
        @if($type === 'invoice')
            <div style="font-size: 9pt; font-weight: 500; margin-bottom: 2mm;">振込用QRコード</div>
            <div style="width: 24mm; height: 24mm;">
                <img src="{{ $template['qr_code_data'] }}" alt="QRコード" style="width: 100%; height: 100%;">
            </div>
            <div style="font-size: 8pt; color: #6b7280;">スマートフォンで読み取って振込ができます</div>
        @else
            <div style="font-size: 9pt; font-weight: 500; margin-bottom: 2mm;">領収書検証用QRコード</div>
            <div style="width: 24mm; height: 24mm;">
                <img src="{{ $template['qr_code_data'] }}" alt="QRコード" style="width: 100%; height: 100%;">
            </div>
            <div style="font-size: 8pt; color: #6b7280;">スマートフォンで読み取って領収書の真偽を確認できます</div>
        @endif
    </div>
    @endif
    <div style="clear: both;"></div>
</div>