<?php

namespace App\Http\Controllers;

use App\Services\Chatbot\Public\DTO\PublicChatRequest;
use App\Services\Chatbot\Public\PublicChatbotService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

/**
 * 公開チャットボットAPIコントローラ（認証不要）
 *
 * スパム対策: ハニーポット + 最短時間チェック + IPレート制限（FormController パターン踏襲）
 * 内部API /api/chatbot/* とは完全独立。
 */
class PublicChatbotController extends Controller
{
    public function __construct(
        private readonly PublicChatbotService $service,
    ) {}

    public function message(Request $request): JsonResponse
    {
        // ハニーポット: 隠しフィールドが埋まっていればボットと判定（201を返して黙って破棄）
        if ($request->filled('website')) {
            Log::info('[PublicChatbotController] Honeypot caught spam message', [
                'ip' => $request->ip(),
            ]);

            return response()->json([
                'success' => true,
                'message' => '受け付けました。',
            ], 201);
        }

        // 最短時間チェック: ウィジェット読み込みから2秒以内の送信はボットと判定
        $loadedAt = (int) $request->input('form_loaded_at', 0);
        $minSeconds = (int) config('chatbot.public.min_submit_seconds', 2);
        if ($loadedAt > 0 && (now()->getTimestamp() - $loadedAt) < $minSeconds) {
            Log::info('[PublicChatbotController] Message too fast, rejected as bot', [
                'ip' => $request->ip(),
                'elapsed' => now()->getTimestamp() - $loadedAt,
            ]);

            return response()->json([
                'success' => true,
                'message' => '受け付けました。',
            ], 201);
        }

        $validator = Validator::make($request->all(), [
            'message' => ['required', 'string', 'min:1', 'max:500'],
            'visitor_id' => ['nullable', 'uuid'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        // visitor_id: Cookie から取得、無ければレスポンスで新規発行
        $visitorId = $request->cookie('chatbot_visitor_id')
            ?? $request->input('visitor_id')
            ?? (string) Str::uuid();
        $isNewVisitor = ! $request->cookie('chatbot_visitor_id') && ! $request->input('visitor_id');

        $chatRequest = new PublicChatRequest(
            message: $validator->validated()['message'],
            channel: 'public',
            visitorId: $visitorId,
        );

        $response = $this->service->handle($chatRequest);

        $jsonResponse = response()->json([
            'success' => true,
            'reply' => $response->reply,
            'intent' => $response->intent,
            'data' => $response->data,
            'action_links' => $response->actionLinks,
            'quick_replies' => $response->quickReplies,
            'faq_matched' => $response->faqMatched,
            'form' => $response->form,
            'visitor_id' => $visitorId,
        ]);

        if ($isNewVisitor) {
            $jsonResponse->cookie('chatbot_visitor_id', $visitorId, 60 * 24 * 365, '/', null, false, true);
        }

        return $jsonResponse;
    }
}
