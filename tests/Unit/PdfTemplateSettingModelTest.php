<?php

use App\Models\PdfTemplateSetting;

test('fillableフィールドのみが一括代入で設定されること', function () {
    $template = PdfTemplateSetting::create([
        'key' => 'invoice',
        'name' => '請求書テンプレート',
        'description' => 'テスト用テンプレート',
        'paper_size' => 'A4',
        'paper_orientation' => 'portrait',
        'margin_top' => 20,
        'margin_right' => 15,
        'margin_bottom' => 20,
        'margin_left' => 15,
        'font_family' => 'Helvetica',
        'font_size' => 10,
        'line_height' => 1.5,
        'primary_color' => '#000000',
        'secondary_color' => '#333333',
        'accent_color' => '#007bff',
        'background_color' => '#ffffff',
        'text_color' => '#000000',
        'border_color' => '#cccccc',
        'header_bg_color' => '#f8f9fa',
        'total_bg_color' => '#e9ecef',
        'tax_table_header_bg' => '#dee2e6',
        'show_facility_logo' => true,
        'facility_logo_path' => '/logos/facility.png',
        'show_facility_info' => true,
        'show_tax_breakdown' => true,
        'show_daily_charges_detail' => true,
        'show_qr_code' => true,
        'qr_code_data' => 'bank_transfer',
        'header_html' => '<header>Header</header>',
        'footer_html' => '<footer>Footer</footer>',
        'show_page_numbers' => true,
        'table_header_bg' => '#f8f9fa',
        'table_row_even_bg' => '#ffffff',
        'table_row_odd_bg' => '#f8f9fa',
        'table_border_color' => '#dee2e6',
        'is_active' => true,
        'is_default' => true,
        'notes' => 'テスト用メモ',
        'version' => 1,
    ]);

    expect($template->exists)->toBeTrue()
        ->and($template->key)->toBe('invoice')
        ->and($template->name)->toBe('請求書テンプレート');
});

test('fillable外のフィールド（id）が一括代入で無視されること', function () {
    $template = PdfTemplateSetting::create([
        'id' => 99999,
        'key' => 'invoice',
        'name' => 'ID無視テスト',
        'paper_size' => 'A4',
    ]);

    expect($template->id)->not->toBe(99999);
});

test('不正なフィールド名での一括代入は無視されること', function () {
    $template = PdfTemplateSetting::create([
        'key' => 'invoice',
        'name' => '不正フィールドテスト',
        'paper_size' => 'A4',
        'unknown_field' => 'テスト',
    ]);

    expect($template->exists)->toBeTrue()
        ->and($template->getAttribute('unknown_field'))->toBeNull();
});

test('キャストが正しく動作すること', function () {
    $template = PdfTemplateSetting::create([
        'key' => 'invoice',
        'name' => 'キャストテスト',
        'paper_size' => 'A4',
        'margin_top' => '20',
        'margin_right' => '15',
        'margin_bottom' => '20',
        'margin_left' => '15',
        'font_size' => '10',
        'line_height' => '1.5',
        'show_facility_logo' => true,
        'show_facility_info' => false,
        'show_tax_breakdown' => true,
        'show_daily_charges_detail' => false,
        'show_qr_code' => true,
        'show_page_numbers' => true,
        'is_active' => true,
        'is_default' => false,
        'version' => 1,
    ]);

    expect($template->margin_top)->toBe(20)
        ->and($template->margin_right)->toBe(15)
        ->and($template->margin_bottom)->toBe(20)
        ->and($template->margin_left)->toBe(15)
        ->and($template->font_size)->toBe(10)
        ->and((float) $template->line_height)->toBe(1.5)
        ->and($template->show_facility_logo)->toBeTrue()
        ->and($template->show_facility_info)->toBeFalse()
        ->and($template->show_tax_breakdown)->toBeTrue()
        ->and($template->show_daily_charges_detail)->toBeFalse()
        ->and($template->show_qr_code)->toBeTrue()
        ->and($template->show_page_numbers)->toBeTrue()
        ->and($template->is_active)->toBeTrue()
        ->and($template->is_default)->toBeFalse()
        ->and($template->version)->toBe(1);
});

test('getForKeyメソッドが有効なテンプレートを取得すること', function () {
    // テーブルをトリンケートしてシーダーデータを削除
    PdfTemplateSetting::truncate();

    // 初期状態: アクティブだがデフォルトでないテンプレートを作成
    $template = PdfTemplateSetting::create([
        'key' => 'invoice',
        'name' => 'アクティブだがデフォルトでない',
        'paper_size' => 'A4',
        'is_active' => true,
        'is_default' => false,
        'version' => 1,
    ]);

    // アクティブなテンプレートが返されること
    $result = PdfTemplateSetting::getForKey('invoice');
    expect($result)->not->toBeNull()
        ->and($result->id)->toBe($template->id)
        ->and($result->name)->toBe('アクティブだがデフォルトでない')
        ->and($result->is_active)->toBeTrue()
        ->and($result->is_default)->toBeFalse();

    // 同じテンプレートを更新: デフォルトかつバージョンアップ
    $template->update([
        'is_default' => true,
        'version' => 2,
        'name' => 'デフォルトかつバージョン2',
    ]);

    // 更新後のテンプレートが返されること（is_default=trueが優先されるため）
    $result = PdfTemplateSetting::getForKey('invoice');
    expect($result)->not->toBeNull()
        ->and($result->id)->toBe($template->id)
        ->and($result->name)->toBe('デフォルトかつバージョン2')
        ->and($result->is_active)->toBeTrue()
        ->and($result->is_default)->toBeTrue()
        ->and($result->version)->toBe(2);
});

test('getForKeyメソッドでデフォルトが優先されること', function () {
    // テーブルをトリンケートしてシーダーデータを削除
    PdfTemplateSetting::truncate();

    // 初期状態: アクティブだがデフォルトでないテンプレートを作成
    $template = PdfTemplateSetting::create([
        'key' => 'receipt',
        'name' => 'アクティブだがデフォルトでない',
        'paper_size' => 'A4',
        'is_active' => true,
        'is_default' => false,
        'version' => 1,
    ]);

    // アクティブなテンプレートが返されること（is_default=falseなので）
    $result = PdfTemplateSetting::getForKey('receipt');
    expect($result)->not->toBeNull()
        ->and($result->id)->toBe($template->id)
        ->and($result->name)->toBe('アクティブだがデフォルトでない')
        ->and($result->is_active)->toBeTrue()
        ->and($result->is_default)->toBeFalse();

    // 同じテンプレートを更新: デフォルトに設定
    $template->update([
        'is_default' => true,
        'name' => 'デフォルトテンプレート',
    ]);

    // 更新後のテンプレートが返されること（is_default=trueが優先されるため）
    $result = PdfTemplateSetting::getForKey('receipt');
    expect($result)->not->toBeNull()
        ->and($result->id)->toBe($template->id)
        ->and($result->name)->toBe('デフォルトテンプレート')
        ->and($result->is_active)->toBeTrue()
        ->and($result->is_default)->toBeTrue();
});

test('getForKeyメソッドでバージョンが新しい方が優先されること', function () {
    // テーブルをトリンケートしてシーダーデータを削除
    PdfTemplateSetting::truncate();

    // 初期状態: アクティブなテンプレートを作成（バージョン1）
    $template = PdfTemplateSetting::create([
        'key' => 'invoice',
        'name' => 'バージョン1',
        'paper_size' => 'A4',
        'is_active' => true,
        'is_default' => false,
        'version' => 1,
    ]);

    // アクティブなテンプレートが返されること
    $result = PdfTemplateSetting::getForKey('invoice');
    expect($result)->not->toBeNull()
        ->and($result->id)->toBe($template->id)
        ->and($result->name)->toBe('バージョン1')
        ->and($result->version)->toBe(1);

    // 同じテンプレートを更新: バージョンアップ
    $template->update([
        'version' => 2,
        'name' => 'バージョン2',
    ]);

    // 更新後のテンプレートが返されること（バージョンが新しい方が優先されるため）
    $result = PdfTemplateSetting::getForKey('invoice');
    expect($result)->not->toBeNull()
        ->and($result->id)->toBe($template->id)
        ->and($result->name)->toBe('バージョン2')
        ->and($result->version)->toBe(2);
});

test('getForKeyメソッドで存在しないキーの場合はnullが返ること', function () {
    $template = PdfTemplateSetting::getForKey('nonexistent');
    expect($template)->toBeNull();
});

test('getDefaultメソッドがデフォルトテンプレートを取得すること', function () {
    // テーブルをトリンケートしてシーダーデータを削除
    PdfTemplateSetting::truncate();

    // 初期状態: アクティブだがデフォルトでないテンプレートを作成
    $template = PdfTemplateSetting::create([
        'key' => 'invoice',
        'name' => 'アクティブだがデフォルトでない',
        'paper_size' => 'A4',
        'is_active' => true,
        'is_default' => false,
    ]);

    // デフォルトテンプレートが取得できないこと（is_default=falseなので）
    $result = PdfTemplateSetting::getDefault('invoice');
    expect($result)->toBeNull();

    // 同じテンプレートを更新: デフォルトに設定
    $template->update([
        'is_default' => true,
        'name' => 'デフォルトテンプレート',
    ]);

    // 更新後のテンプレートが返されること
    $result = PdfTemplateSetting::getDefault('invoice');
    expect($result)->not->toBeNull()
        ->and($result->id)->toBe($template->id)
        ->and($result->name)->toBe('デフォルトテンプレート')
        ->and($result->is_active)->toBeTrue()
        ->and($result->is_default)->toBeTrue();
});

test('getDefaultメソッドで非アクティブなデフォルトは除外されること', function () {
    // テーブルをトリンケートしてシーダーデータを削除
    PdfTemplateSetting::truncate();

    // 初期状態: アクティブなテンプレートを作成（デフォルトでない）
    $template = PdfTemplateSetting::create([
        'key' => 'invoice',
        'name' => 'アクティブなテンプレート',
        'paper_size' => 'A4',
        'is_active' => true,
        'is_default' => false,
    ]);

    // デフォルトテンプレートが取得できないこと（is_default=falseなので）
    $result = PdfTemplateSetting::getDefault('invoice');
    expect($result)->toBeNull();

    // 同じテンプレートを更新: デフォルトに設定だがアクティブでない
    $template->update([
        'is_default' => true,
        'is_active' => false,
        'name' => '非アクティブなデフォルト',
    ]);

    // 依然としてデフォルトテンプレートが取得できないこと（is_active=falseのため除外される）
    $result = PdfTemplateSetting::getDefault('invoice');
    expect($result)->toBeNull();

    // 同じテンプレートを更新: アクティブかつデフォルトに設定
    $template->update([
        'is_active' => true,
        'is_default' => true,
        'name' => 'アクティブなデフォルト',
    ]);

    // 更新後のテンプレートが返されること
    $result = PdfTemplateSetting::getDefault('invoice');
    expect($result)->not->toBeNull()
        ->and($result->id)->toBe($template->id)
        ->and($result->name)->toBe('アクティブなデフォルト')
        ->and($result->is_active)->toBeTrue()
        ->and($result->is_default)->toBeTrue();
});

test('toConfigArrayメソッドが正しい配列を返すこと', function () {
    $template = PdfTemplateSetting::create([
        'key' => 'invoice',
        'name' => 'テスト',
        'paper_size' => 'A4',
        'paper_orientation' => 'portrait',
        'margin_top' => 20,
        'margin_right' => 15,
        'margin_bottom' => 20,
        'margin_left' => 15,
        'font_family' => 'Helvetica',
        'font_size' => 10,
        'line_height' => 1.5,
        'primary_color' => '#000000',
        'secondary_color' => '#333333',
        'accent_color' => '#007bff',
        'background_color' => '#ffffff',
        'text_color' => '#000000',
        'border_color' => '#cccccc',
        'header_bg_color' => '#f8f9fa',
        'total_bg_color' => '#e9ecef',
        'tax_table_header_bg' => '#dee2e6',
        'show_facility_logo' => true,
        'facility_logo_path' => '/logos/facility.png',
        'show_facility_info' => true,
        'show_tax_breakdown' => true,
        'show_daily_charges_detail' => true,
        'show_qr_code' => true,
        'qr_code_data' => 'bank_transfer',
        'header_html' => '<header>Header</header>',
        'footer_html' => '<footer>Footer</footer>',
        'show_page_numbers' => true,
        'table_header_bg' => '#f8f9fa',
        'table_row_even_bg' => '#ffffff',
        'table_row_odd_bg' => '#f8f9fa',
        'table_border_color' => '#dee2e6',
        'is_active' => true,
        'is_default' => true,
        'version' => 1,
    ]);

    $config = $template->toConfigArray();
    expect($config)->toBeArray()
        ->and($config['paper_size'])->toBe('A4')
        ->and($config['paper_orientation'])->toBe('portrait')
        ->and($config['margin_top'])->toBe(20)
        ->and($config['font_size'])->toBe(10)
        ->and($config['line_height'])->toBe(1.5)
        ->and($config['primary_color'])->toBe('#000000')
        ->and($config['show_facility_logo'])->toBeTrue()
        ->and($config['show_qr_code'])->toBeTrue();
});

test('toCssVariablesメソッドが正しい配列を返すこと', function () {
    $template = PdfTemplateSetting::create([
        'key' => 'invoice',
        'name' => 'テスト',
        'paper_size' => 'A4',
        'primary_color' => '#000000',
        'secondary_color' => '#333333',
        'accent_color' => '#007bff',
        'background_color' => '#ffffff',
        'text_color' => '#000000',
        'border_color' => '#cccccc',
        'header_bg_color' => '#f8f9fa',
        'total_bg_color' => '#e9ecef',
        'tax_table_header_bg' => '#dee2e6',
        'table_header_bg' => '#f8f9fa',
        'table_row_even_bg' => '#ffffff',
        'table_row_odd_bg' => '#f8f9fa',
        'table_border_color' => '#dee2e6',
        'font_family' => 'Helvetica',
        'font_size' => 10,
        'line_height' => 1.5,
    ]);

    $cssVars = $template->toCssVariables();
    expect($cssVars)->toBeArray()
        ->and($cssVars['--pdf-primary-color'])->toBe('#000000')
        ->and($cssVars['--pdf-secondary-color'])->toBe('#333333')
        ->and($cssVars['--pdf-accent-color'])->toBe('#007bff')
        ->and($cssVars['--pdf-font-family'])->toBe('Helvetica')
        ->and($cssVars['--pdf-font-size'])->toBe('10pt')
        ->and($cssVars['--pdf-line-height'])->toBe(1.5);
});

test('toCssVariableStringメソッドが正しい文字列を返すこと', function () {
    $template = PdfTemplateSetting::create([
        'key' => 'invoice',
        'name' => 'テスト',
        'paper_size' => 'A4',
        'primary_color' => '#000000',
        'secondary_color' => '#333333',
        'font_family' => 'Helvetica',
        'font_size' => 10,
        'line_height' => 1.5,
    ]);

    $cssString = $template->toCssVariableString();
    expect($cssString)->toBeString()
        ->and($cssString)->toContain('--pdf-primary-color: #000000')
        ->and($cssString)->toContain('--pdf-font-family: Helvetica')
        ->and($cssString)->toContain('--pdf-font-size: 10pt');
});
