<!DOCTYPE html>
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <style>
        body { font-family: sans-serif; font-size: 14px; line-height: 1.6; color: #333; }
        .header { text-align: right; margin-bottom: 20px; }
        .title { text-align: center; font-size: 20px; font-weight: bold; margin-bottom: 30px; letter-spacing: 2px; }
        .recipient { font-size: 16px; margin-bottom: 20px; }
        .amount-box { border: 2px solid #333; padding: 15px; text-align: center; font-size: 18px; margin: 25px 0; font-weight: bold; }
        .footer { margin-top: 40px; border-top: 1px solid #ccc; padding-top: 15px; font-size: 12px; }
    </style>
</head>
<body>
    <div class="header">
        <p>発行日: {{ $issueDate }}</p>
        <p>{{ $facility?->name ?? '介護施設' }}</p>
    </div>

    <div class="recipient">
        <p>{{ $resident?->name ?? 'ご入居者' }} 様（身元引受人様）</p>
    </div>

    <div class="title">
        @if($stage === 'warning')
            督促状（最終警告）
        @else
            施設利用料 お振込確認のお願い
        @endif
    </div>

    <p>拝啓</p>
    <p>貴社ますますご清栄のこととお慶び申し上げます。日頃より当施設の運営に深いご理解とご協力を賜り、心より御礼申し上げます。</p>
    <p>さて、{{ $yearMonth }}分の施設利用料につきまして、誠に恐縮ではございますが、本日現在ご入金の確認が取れておりません。</p>

    <div class="amount-box">
        ご請求金額: {{ $totalAmount }} 円（税込）
    </div>

    <p>お手数ではございますが、本状ご確認の上、お早めにお振込みまたは施設窓口にてお手続きいただきますようお願い申し上げます。</p>
    <p>※なお、本状と行き違いにご入金いただいております場合は、何卒ご容赦いただけますようお願い申し上げます。</p>

    <div class="footer">
        <p>お問い合わせ先: {{ $facility?->name ?? '当施設' }} 事務室</p>
        <p>電話番号: {{ $facility?->phone ?? '00-0000-0000' }}</p>
    </div>
</body>
</html>
