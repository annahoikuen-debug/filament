<?php

return [
    /*
    |--------------------------------------------------------------------------
    | 高齢者施設 基本情報設定
    |--------------------------------------------------------------------------
    | 請求書・領収書・会計データに出力される施設の発行元情報です。
    | 環境変数(.env)経由で柔軟にオーバーライドできます。
    */

    'name' => env('FACILITY_NAME', 'ケアレジデンス ひまわり'),
    'operator' => env('FACILITY_OPERATOR', '株式会社ひまわりケア'),
    'postal_code' => env('FACILITY_POSTAL_CODE', '123-4567'),
    'address' => env('FACILITY_ADDRESS', '東京都〇〇区〇〇町 1-2-3'),
    'phone' => env('FACILITY_PHONE', '03-1234-5678'),
    'fax' => env('FACILITY_FAX', '03-1234-5679'),
    'email' => env('FACILITY_EMAIL', 'info@care-himawari.example.jp'),

    // インボイス制度: 適格請求書発行事業者登録番号
    'invoice_registration_number' => env('FACILITY_INVOICE_NUMBER', 'T1234567890123'),

    // 振込先銀行口座情報
    'bank' => [
        'name' => env('FACILITY_BANK_NAME', '〇〇銀行'),
        'branch_name' => env('FACILITY_BANK_BRANCH', '〇〇支店'),
        'account_type' => env('FACILITY_BANK_TYPE', '普通'),
        'account_number' => env('FACILITY_BANK_NUMBER', '1234567'),
        'account_holder' => env('FACILITY_BANK_HOLDER', 'カ）ヒマワリケア'),
    ],

    // 請求・支払いサイクル設定
    'billing' => [
        'direct_debit_day' => (int) env('FACILITY_DEBIT_DAY', 27), // 翌月口座振替日
        'bank_transfer_due_days' => (int) env('FACILITY_TRANSFER_DUE_DAYS', 30), // 支払期限日数
    ],
];
