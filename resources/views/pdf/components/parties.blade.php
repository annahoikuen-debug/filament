@props([
    'resident',
    'facility',
    'type' => 'invoice', // 'invoice' or 'receipt'
    'invoiceNumber' => null,
    'issuedAt' => null,
    'billingYearMonth' => null,
])

<div class="two-col" style="margin-bottom: 6mm;">
    <!-- 受取人情報 -->
    <div class="col-left card">
        <div class="section-title">{{ $type === 'invoice' ? 'ご請求先' : 'ご入居者様' }}</div>
        <div>
            <div style="margin-bottom: 2mm;">
                <span class="footer-label">[居室] </span>
                <strong style="font-weight: 500;">{{ $resident['room_number'] }} 号室</strong>
            </div>
            <div style="font-weight: 700; font-size: 12pt; text-decoration: underline;">{{ $resident['name'] }} 様</div>
            @if($resident['name_kana'])
                <div style="font-size: 9pt; color: #6b7280;">({{ $resident['name_kana'] }})</div>
            @endif
            @if($type === 'invoice')
                <div style="font-size: 9pt; color: #6b7280; margin-top: 2mm;">
                    平素は格別のご愛顧を賜り、厚く御礼申し上げます。<br>
                    {{ substr($billingYearMonth, 0, 4) }}年{{ (int)substr($billingYearMonth, 5, 2) }}月度のご利用料金を下記の通りご請求申し上げます。
                </div>
            @endif
        </div>
    </div>

    <!-- 発行者情報 -->
    <div class="col-right card">
        <div class="section-title">発行者情報</div>
        <div style="font-size: 9pt;">
            @if($type === 'invoice')
                <div style="margin-bottom: 2mm;">請求番号: {{ $invoiceNumber }}</div>
                <div style="margin-bottom: 2mm;">発行日: {{ $issuedAt }}</div>
            @else
                <div style="margin-bottom: 2mm;">領収番号: {{ $invoiceNumber }}</div>
                <div style="margin-bottom: 2mm;">領収日: {{ $issuedAt }}</div>
            @endif
            <div style="margin-bottom: 2mm; font-weight: 500;">{{ $facility['name'] }}</div>
            <div style="margin-bottom: 2mm;">{{ $facility['operator'] }}</div>
            <div style="margin-bottom: 2mm;">〒{{ $facility['postal_code'] }} {{ $facility['address'] }}</div>
            <div style="margin-bottom: 2mm;">
                <span>TEL:</span>
                <span class="footer-value">{{ $facility['phone'] }}</span>
                @if($type === 'invoice' && $facility['fax'])
                    <span style="margin-left: 2mm; margin-right: 2mm;">/</span>
                    <span>FAX:</span>
                    <span class="footer-value">{{ $facility['fax'] }}</span>
                @endif
            </div>
            @if(!empty($facility['invoice_registration_number']))
                <div style="margin-top: 2mm; color: #1e3a8a; font-weight: 500;">
                    登録番号: {{ $facility['invoice_registration_number'] }}
                </div>
            @endif
        </div>
    </div>
    <div class="clear"></div>
</div>