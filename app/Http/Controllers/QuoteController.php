<?php

namespace App\Http\Controllers;

use App\Mail\QuoteMail;
use App\Models\Trial;
use App\Services\MailService;
use App\Services\QuoteService;
use Illuminate\Http\Request;

class QuoteController extends Controller
{
    public function __construct(
        private readonly QuoteService $quoteService,
        private readonly MailService $mailService,
    ) {}

    /**
     * 見積り内容を取得
     */
    public function show(Trial $trial)
    {
        return response()->json([
            'success' => true,
            'quote' => $this->quoteService->quote($trial),
        ]);
    }

    /**
     * 見積書をメール送信（トライアル所有者のみ）
     */
    public function send(Request $request, Trial $trial)
    {
        // 認可: 見積書はトライアル所有者のメールアドレスへ送信されるため、
        // プロビジョニングメールで通知されるトークンを要求する
        if (! $trial->isValidConversionToken($request->input('conversion_token'))) {
            return response()->json([
                'success' => false,
                'message' => '見積書送信トークンが不正です。',
            ], 403);
        }

        $quote = $this->quoteService->quote($trial);

        $this->mailService->send(
            new QuoteMail($trial, $quote),
            $trial->email,
        );

        return response()->json([
            'success' => true,
            'message' => '見積書を送信しました。',
            'quote' => $quote,
        ]);
    }
}
