<?php

namespace App\Services\Chatbot;

class ChatActionLinkGenerator
{
    /**
     * 月次請求書の編集画面URL
     */
    public function invoiceEdit(int $invoiceId): array
    {
        return [
            'label' => '👉 請求書編集画面を開く',
            'url' => url("/admin/monthly-invoices/{$invoiceId}/edit"),
            'color' => 'primary',
        ];
    }

    /**
     * 入居者情報の編集・詳細画面URL
     */
    public function residentEdit(int $residentId): array
    {
        return [
            'label' => '👉 入居者台帳を開く',
            'url' => url("/admin/residents/{$residentId}/edit"),
            'color' => 'secondary',
        ];
    }

    /**
     * 月次請求一覧URL
     */
    public function invoiceList(): array
    {
        return [
            'label' => '👉 請求データ一覧',
            'url' => url('/admin/monthly-invoices'),
            'color' => 'gray',
        ];
    }
}
