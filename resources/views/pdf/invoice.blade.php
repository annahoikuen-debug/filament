<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>請求書 - {{ $data['billing_year_month'] }} - {{ $data['resident']['name'] }} 様</title>
    <style>
        {{ $css }}
    </style>
</head>
<body>
    @include('pdf.components.header', [
        'facility' => $data['facility'],
        'template' => $template,
        'type' => 'invoice',
        'billingYearMonth' => $data['billing_year_month'],
    ])

    <div class="page-content">
        @include('pdf.components.parties', [
            'resident' => $data['resident'],
            'facility' => $data['facility'],
            'type' => 'invoice',
            'invoiceNumber' => $data['invoice_number'],
            'issuedAt' => $data['issued_at'],
            'billingYearMonth' => $data['billing_year_month'],
        ])

        @include('pdf.components.amount-box', [
            'taxInfo' => $data['tax_info'],
            'type' => 'invoice',
        ])

        @include('pdf.components.summary-table', [
            'taxInfo' => $data['tax_info'],
        ])

        @if(!empty($data['daily_charges']))
        <div class="page-break"></div>
        @endif

        @include('pdf.components.daily-charges-table', [
            'dailyCharges' => $data['daily_charges'],
        ])

        @include('pdf.components.footer', [
            'facility' => $data['facility'],
            'template' => $template,
            'type' => 'invoice',
            'billingYearMonth' => $data['billing_year_month'],
        ])
    </div>
</body>
</html>