<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>領収証 - {{ $data['receipt_number'] }} - {{ $data['resident']['name'] }} 様</title>
    <style>
        {{ $css }}
    </style>
</head>
<body>
    @include('pdf.components.header', [
        'facility' => $data['facility'],
        'template' => $template,
        'type' => 'receipt',
        'billingYearMonth' => $data['billing_year_month'],
    ])

    <div class="page-content">
        @include('pdf.components.parties', [
            'resident' => $data['resident'],
            'facility' => $data['facility'],
            'type' => 'receipt',
            'invoiceNumber' => $data['receipt_number'],
            'issuedAt' => $data['received_at'],
            'billingYearMonth' => $data['billing_year_month'],
        ])

        @include('pdf.components.amount-box', [
            'taxInfo' => $data['tax_info'],
            'type' => 'receipt',
            'taxRate' => $data['tax_info']['tax_rate'],
        ])

        @include('pdf.components.note-box', [
            'billingYearMonth' => $data['billing_year_month'],
            'paymentMethodLabel' => $data['payment_method_label'],
        ])

        @include('pdf.components.receipt-detail-table', [
            'taxInfo' => $data['tax_info'],
            'rentSubtotal' => $data['rent_subtotal'],
            'managementFeeSubtotal' => $data['management_fee_subtotal'],
            'serviceSubtotal' => $data['service_subtotal'],
        ])

        @include('pdf.components.footer', [
            'facility' => $data['facility'],
            'template' => $template,
            'type' => 'receipt',
            'billingYearMonth' => $data['billing_year_month'],
        ])
    </div>
</body>
</html>