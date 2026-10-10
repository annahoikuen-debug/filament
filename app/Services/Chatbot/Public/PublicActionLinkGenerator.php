<?php

namespace App\Services\Chatbot\Public;

/**
 * 公開サイト用アクションリンク生成器（内部 ChatActionLinkGenerator のパターン踏襲）
 *
 * 静的サイトの各リクエストページ・商品ページへの誘導リンクを生成する。
 */
class PublicActionLinkGenerator
{
    public function catalog(): array
    {
        return [
            'label' => '📄 資料請求はこちら',
            'url' => url('/request/catalog.html'),
            'color' => 'primary',
        ];
    }

    public function demo(): array
    {
        return [
            'label' => '🎥 デモ申込はこちら',
            'url' => url('/request/demo.html'),
            'color' => 'primary',
        ];
    }

    public function inquiry(): array
    {
        return [
            'label' => '✉️ お問い合わせフォームへ',
            'url' => url('/request/inquiry.html'),
            'color' => 'primary',
        ];
    }

    public function pricing(): array
    {
        return [
            'label' => '💰 料金ページを見る',
            'url' => url('/product/pricing.html'),
            'color' => 'secondary',
        ];
    }
}
