<?php

return [
    'rate_limit' => '30,1',

    'retention_days' => 90,

    'mask_names' => true,

    'intents' => [
        'resident_lookup' => [
            'patterns' => [
                '/(?<room>\d+)号室/u',
                '/(?<name>.{2,}?)の情報/u',
                '/(?<name>.{2,}?)の入居者/u',
            ],
        ],
        'invoice_amount' => [
            'patterns' => [
                '/(?<name>.{2,}?)の?(今月の)?請求額/u',
                '/請求額/u',
            ],
        ],
        'invoice_status' => [
            'patterns' => [
                '/(?<name>.{2,}?)の?支払い状況/u',
                '/支払い状況/u',
                '/未払い/u',
                '/入金済/u',
            ],
        ],
        'daily_charge_total' => [
            'patterns' => [
                '/(?<name>.{2,}?)の?(月額利用料|利用料|自費)/u',
                '/利用料/u',
                '/自費/u',
            ],
        ],
    ],
];
