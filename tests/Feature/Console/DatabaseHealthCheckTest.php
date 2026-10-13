<?php

use Illuminate\Support\Facades\Artisan;

test('db:health-check コマンドが正常終了し整合性を確認できること', function () {
    $exitCode = Artisan::call('db:health-check');
    expect($exitCode)->toBe(0);

    $output = Artisan::output();
    expect($output)->toContain('データベース整合性チェック')
        ->and($output)->toContain('正常 (OK)');
});
