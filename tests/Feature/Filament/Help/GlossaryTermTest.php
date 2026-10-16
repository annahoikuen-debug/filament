<?php

use App\Models\User;
use Illuminate\Support\Facades\Blade;

beforeEach(function () {
    $this->user = User::factory()->create(['is_admin' => true]);
    $this->actingAs($this->user);
});

test('用語集configが正しく読み込めること', function () {
    expect(config('glossary.日割り'))->not->toBeNull()
        ->and(config('glossary.確定保護'))->not->toBeNull()
        ->and(config('glossary.Unbilled'))->not->toBeNull();
});

test('x-help.termコンポーネントが定義済み用語をabbrタグ付きでレンダリングすること', function () {
    $html = Blade::render('<x-help.term word="日割り" />');

    expect($html)->toContain('<abbr')
        ->and($html)->toContain('title=')
        ->and($html)->toContain('日割り');
});

test('x-help.termコンポーネントが未定義用語でもエラーなく通常描画されること', function () {
    $html = Blade::render('<x-help.term word="存在しない用語" />');

    expect($html)->toContain('存在しない用語')
        ->and($html)->not->toContain('<abbr');
});
