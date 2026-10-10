<?php

namespace App\Services\Chatbot\Public;

use App\Models\ChatLog;
use App\Services\Chatbot\Public\DTO\PublicChatRequest;
use App\Services\Chatbot\Public\DTO\PublicChatResponse;
use Illuminate\Support\Str;

/**
 * 公開チャットボット・オーケストレータ
 *
 * 認証なし・PII参照なし（Resident / MonthlyInvoice / DailyCharge は import しない）。
 * 公開FAQ（is_public=true）とリード獲得フローのみを扱う。
 */
class PublicChatbotService
{
    public function __construct(
        private readonly PublicIntentRecognizer $intentRecognizer,
        private readonly PublicFaqResponder $faqResponder,
        private readonly PublicActionLinkGenerator $linkGenerator,
    ) {}

    public function handle(PublicChatRequest $request): PublicChatResponse
    {
        $intent = $this->intentRecognizer->recognize($request->message);

        $response = match ($intent->intent) {
            'pricing', 'features', 'hours' => $this->handleFaqLookup($request->message, $intent->intent),
            'demo_request', 'catalog_request', 'contact', 'lead' => $this->handleLeadGuide($intent->intent),
            'faq' => $this->handleFallback($request->message),
            default => $this->handleFallback($request->message),
        };

        $log = $this->logConversation($request, $intent->intent, $response);

        return $response->withChatLogId($log?->id);
    }

    private function handleFaqLookup(string $message, string $intent): PublicChatResponse
    {
        $results = $this->faqResponder->searchPublic($message);

        if ($results->isNotEmpty()) {
            $topFaq = $results->first();

            $actionLinks = match ($intent) {
                'pricing' => [$this->linkGenerator->pricing()],
                default => [],
            };

            return new PublicChatResponse(
                reply: $topFaq->answer,
                intent: $intent,
                data: ['faq_id' => $topFaq->id, 'faq_question' => $topFaq->question],
                actionLinks: $actionLinks,
                quickReplies: config('chatbot.public.quick_replies', []),
                faqMatched: true,
            );
        }

        return new PublicChatResponse(
            reply: '申し訳ありません、該当する情報が見つかりませんでした。詳細は以下のページをご覧いただくか、お問い合わせください。',
            intent: $intent,
            actionLinks: [
                $intent === 'pricing'
                    ? $this->linkGenerator->pricing()
                    : $this->linkGenerator->inquiry(),
                $this->linkGenerator->catalog(),
            ],
        );
    }

    private function handleLeadGuide(string $intent): PublicChatResponse
    {
        // リード獲得フロー: チャット内ミニフォーム + 通常フォームへの誘導
        return match ($intent) {
            'demo_request' => new PublicChatResponse(
                reply: "デモをご希望いただきありがとうございます。\n下のフォームからお申し込みいただけます。",
                intent: 'lead_capture',
                form: $this->buildMiniForm('demo'),
                actionLinks: [$this->linkGenerator->demo()],
            ),
            'catalog_request' => new PublicChatResponse(
                reply: "資料をお送りします。\n下のフォームからお申し込みください。",
                intent: 'lead_capture',
                form: $this->buildMiniForm('catalog'),
                actionLinks: [$this->linkGenerator->catalog()],
            ),
            'contact', 'lead' => new PublicChatResponse(
                reply: "お問い合わせありがとうございます。\n下のフォームからご連絡いただけます。",
                intent: 'lead_capture',
                form: $this->buildMiniForm('inquiry'),
                actionLinks: [$this->linkGenerator->inquiry()],
            ),
            default => new PublicChatResponse(
                reply: 'ご案内できることがあります。以下からお選びください。',
                intent: $intent,
                actionLinks: [$this->linkGenerator->inquiry()],
            ),
        };
    }

    /**
     * リード獲得ミニフォーム定義（既存 POST /api/site-forms/{type} へ送信）
     *
     * @return array<string, mixed>
     */
    private function buildMiniForm(string $type): array
    {
        return [
            'fields' => [
                ['name' => 'name', 'label' => '氏名', 'type' => 'text', 'required' => true],
                ['name' => 'email', 'label' => 'メールアドレス', 'type' => 'email', 'required' => true],
                ['name' => 'facility_name', 'label' => '施設名', 'type' => 'text', 'required' => true],
            ],
            'endpoint' => "/api/site-forms/{$type}",
            'complete_url' => '/complete.html?type='.$type,
        ];
    }

    /**
     * フォールバック: 公開FAQ検索 → 該当なき場合はクイックリプライ案内
     */
    private function handleFallback(string $message): PublicChatResponse
    {
        $results = $this->faqResponder->searchPublic($message);

        if ($results->isNotEmpty()) {
            $topFaq = $results->first();

            return new PublicChatResponse(
                reply: $topFaq->answer,
                intent: 'faq',
                data: ['faq_id' => $topFaq->id],
                quickReplies: config('chatbot.public.quick_replies', []),
                faqMatched: true,
            );
        }

        return new PublicChatResponse(
            reply: "すみません、よくわかりませんでした。\n以下からお選びください。",
            intent: 'faq',
            quickReplies: config('chatbot.public.quick_replies', []),
            actionLinks: [$this->linkGenerator->inquiry()],
        );
    }

    /**
     * 会話ログ記録（channel='public' + visitor_id）
     *
     * 内部 ChatEscalation には記録しない。
     */
    private function logConversation(
        PublicChatRequest $request,
        string $intent,
        PublicChatResponse $response,
    ): ?ChatLog {
        try {
            return ChatLog::create([
                'session_id' => $request->sessionId !== ''
                    ? $request->sessionId
                    : (string) Str::uuid(),
                'channel' => 'public',
                'visitor_id' => $request->visitorId,
                'user_message' => $request->message,
                'intent' => $intent,
                'bot_reply' => $response->reply,
                'faq_matched' => $response->faqMatched,
            ]);
        } catch (\Throwable) {
            // ログ記録失敗時もチャット応答は継続
            return null;
        }
    }
}
