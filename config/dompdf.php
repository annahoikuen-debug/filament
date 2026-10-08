<?php

return [

    /*
    |--------------------------------------------------------------------------
    | DomPDF フォント設定
    |--------------------------------------------------------------------------
    |
    | ここでカスタムフォントを登録し、PDF生成時に使用できるようにします。
    |
    */

    'font_dir' => storage_path('fonts'),

    'font_cache' => storage_path('fonts'),

    'default_font' => 'ipaexg',

    'font_height_ratio' => 1.25,

    'default_font_size' => 12,

    'default_media_type' => 'screen',

    'default_paper_size' => 'a4',

    'default_paper_orientation' => 'portrait',

    'use_flattie' => true,

    'use_gpu' => true,

    'enable_html5_parser' => true,

    'enable_remote' => true,

    'enabled_logging' => false,

    'show_warnings' => true,

    'is_php5_utf8' => true,

    'temp_dir' => sys_get_temp_dir(),

];
