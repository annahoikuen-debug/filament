<?php

namespace App\Services\Chatbot;

use App\Models\ChatEscalation;
use App\Models\ChatLog;
use App\Models\Resident;
use App\Models\User;
use App\Services\Chatbot\DTO\ChatRequest;
use App\Services\Chatbot\DTO\ChatResponse;
use App\Services\Chatbot\DTO\IntentDTO;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class ChatbotService
{
    private ChatActionLinkGenerator $linkGenerator;

    public function __construct(
        private readonly IntentRecognizer $intentRecognizer,
        private readonly FaqResponder $faqResponder,
        private readonly ResidentQueryService $residentQueryService,
        ?ChatActionLinkGenerator $linkGenerator = null,
    ) {
        $this->linkGenerator = $linkGenerator ?? new ChatActionLinkGenerator;
    }

    public function handle(ChatRequest $request, User $user): ChatResponse
    {
        $intent = $this->intentRecognizer->recognize($request->message);

        $response = match ($intent->intent) {
            'resident_lookup' => $this->handleResidentLookup($intent, $user, $request->sessionId),
            'invoice_amount' => $this->handleInvoiceAmount($intent, $user, $request->sessionId),
            'invoice_status' => $this->handleInvoiceStatus($intent, $user, $request->sessionId),
            'daily_charge_total' => $this->handleDailyChargeTotal($intent, $user, $request->sessionId),
            'escalate' => $this->handleEscalate($request, $user),
            default => $this->handleFaq($request->message, $user),
        };

        $log = $this->logConversation($request, $intent, $response, $user);

        return $response->withChatLogId($log?->id);
    }

    private function handleResidentLookup(IntentDTO $intent, User $user, ?string $sessionId = null): ChatResponse
    {
        $keyword = $intent->entities['name'] ?? $intent->entities['room'] ?? '';

        if ($keyword === '') {
            return new ChatResponse(
                reply: '入居者の名前または部屋番号を教えてください。',
                intent: 'resident_lookup',
            );
        }

        $candidates = $this->residentQueryService->findCandidates($keyword, $user);
        $threshold = (float) config('chatbot.fuzzy.threshold', 0.7);

        if ($candidates->isEmpty() || $candidates->first()['score'] < $threshold) {
            return new ChatResponse(
                reply: "「{$keyword}」に一致する入居者は見つかりませんでした。",
                intent: 'resident_lookup',
            );
        }

        // 複数候補があり、最上位のスコアが1.0未満（完全一致でない）で複数件ある場合は確認応答
        if ($candidates->count() > 1 && $candidates->first()['score'] < 1.0) {
            $topCandidates = $candidates->take(3);
            $names = $topCandidates->map(fn ($c) => sprintf('%s（%s号室）', $c['resident']->name, $c['resident']->room_number))->join('、');
            return new ChatResponse(
                reply: sprintf("該当する候補が複数見つかりました。\nどちらの入居者様ですか？\n%s", $names),
                intent: 'resident_lookup',
                data: [
                    'candidates' => $topCandidates->map(fn ($c) => [
                        'resident_id' => $c['resident']->id,
                        'name' => $c['resident']->name,
                        'room_number' => $c['resident']->room_number,
                    ])->values()->all(),
                ],
            );
        }

        $resident = $candidates->first()['resident'];

        if ($sessionId !== null) {
            Cache::put("chatbot_context:{$sessionId}:resident_id", $resident->id, now()->addMinutes(15));
        }

        $statusLabel = match ($resident->status->value) {
            'active' => '在籍中',
            'waiting' => '待機中',
            'moved_out' => '退去済',
            default => $resident->status->value,
        };

        return new ChatResponse(
            reply: sprintf(
                "%s（%s号室）\nステータス: %s\n入居日: %s\n月額固定計: %s円",
                $resident->name,
                $resident->room_number,
                $statusLabel,
                $resident->move_in_date?->format('Y/m/d') ?? '未設定',
                number_format($resident->base_rent + $resident->base_management_fee)
            ),
            intent: 'resident_lookup',
            data: [
                'resident_id' => $resident->id,
                'room_number' => $resident->room_number,
                'name' => $resident->name,
                'status' => $resident->status->value,
            ],
            actionLinks: [
                $this->linkGenerator->residentEdit($resident->id),
            ],
        );
    }

    private function handleInvoiceAmount(IntentDTO $intent, User $user, ?string $sessionId = null): ChatResponse
    {
        $resident = $this->resolveResident($intent, $user, $sessionId);

        if ($resident === null) {
            return new ChatResponse(
                reply: '入居者を特定できませんでした。名前または部屋番号を教えてください。',
                intent: 'invoice_amount',
            );
        }

        $yearMonth = $intent->entities['year_month'] ?? null;

        if ($yearMonth !== null && $yearMonth > now()->format('Y-m')) {
            return new ChatResponse(
                reply: sprintf('%s さんの未来月（%s年%d月）の請求データはまだ作成されていません。',
                    $resident->name,
                    substr($yearMonth, 0, 4),
                    (int) substr($yearMonth, 5, 2)
                ),
                intent: 'invoice_amount',
                data: ['resident_id' => $resident->id],
            );
        }

        $invoice = $this->residentQueryService->getLatestInvoice($resident, $yearMonth);

        if ($invoice === null) {
            $monthNotice = $yearMonth !== null
                ? sprintf('%s年%d月の', substr($yearMonth, 0, 4), (int) substr($yearMonth, 5, 2))
                : '';
            return new ChatResponse(
                reply: "{$resident->name} さんの{$monthNotice}請求データはまだありません。",
                intent: 'invoice_amount',
                data: ['resident_id' => $resident->id],
            );
        }

        $billingYm = $invoice->billing_year_month;
        $proration = $this->residentQueryService->getProrationBasis($resident, $billingYm);

        $reply = sprintf(
            "%s さんの %s 年 %s 月の請求額: %s円（税込 %s円）\n内訳: 家賃 %s円 ＋ 管理費 %s円 ＋ 自費 %s円",
            $resident->name,
            substr($billingYm, 0, 4),
            (int) substr($billingYm, 5, 2),
            number_format($invoice->total_amount),
            number_format($invoice->total_with_tax),
            number_format($invoice->rent_subtotal),
            number_format($invoice->management_fee_subtotal),
            number_format($invoice->service_subtotal),
        );

        if ($proration !== null) {
            $reply .= sprintf(
                "\n（日割り計算: %d日/%d日 在籍、家賃 %s円→%s円）",
                $proration['active_days'],
                $proration['days_in_month'],
                number_format($proration['base_rent']),
                number_format($proration['prorated_rent'])
            );
        }

        return new ChatResponse(
            reply: $reply,
            intent: 'invoice_amount',
            data: [
                'resident_id' => $resident->id,
                'billing_year_month' => $billingYm,
                'total_amount' => $invoice->total_amount,
                'total_with_tax' => $invoice->total_with_tax,
            ],
            actionLinks: [
                $this->linkGenerator->invoiceEdit($invoice->id),
                $this->linkGenerator->residentEdit($resident->id),
            ],
        );
    }

    private function handleInvoiceStatus(IntentDTO $intent, User $user, ?string $sessionId = null): ChatResponse
    {
        $resident = $this->resolveResident($intent, $user, $sessionId);

        if ($resident === null) {
            return new ChatResponse(
                reply: '入居者を特定できませんでした。名前または部屋番号を教えてください。',
                intent: 'invoice_status',
            );
        }

        $yearMonth = $intent->entities['year_month'] ?? null;

        if ($yearMonth !== null && $yearMonth > now()->format('Y-m')) {
            return new ChatResponse(
                reply: sprintf('%s さんの未来月（%s年%d月）の請求データはまだ作成されていません。',
                    $resident->name,
                    substr($yearMonth, 0, 4),
                    (int) substr($yearMonth, 5, 2)
                ),
                intent: 'invoice_status',
                data: ['resident_id' => $resident->id],
            );
        }

        $status = $this->residentQueryService->getPaymentStatus($resident, $yearMonth);

        if (! $status['has_invoice']) {
            $monthNotice = $yearMonth !== null
                ? sprintf('%s年%d月の', substr($yearMonth, 0, 4), (int) substr($yearMonth, 5, 2))
                : '';
            return new ChatResponse(
                reply: "{$resident->name} さんの{$monthNotice}請求データはまだありません。",
                intent: 'invoice_status',
                data: ['resident_id' => $resident->id],
            );
        }

        $reply = sprintf(
            "%s さんの %s 年 %s 月の支払い状況: %s\n請求額: %s円",
            $resident->name,
            substr((string) $status['billing_year_month'], 0, 4),
            (int) substr((string) $status['billing_year_month'], 5, 2),
            $status['status_label'],
            number_format((int) $status['total_amount'])
        );

        if ($status['paid_at'] !== null) {
            $reply .= sprintf("\n入金日: %s", $status['paid_at']);
        }

        $actionLinks = [];
        if (! empty($status['invoice_id'])) {
            $actionLinks[] = $this->linkGenerator->invoiceEdit($status['invoice_id']);
        }
        $actionLinks[] = $this->linkGenerator->residentEdit($resident->id);

        return new ChatResponse(
            reply: $reply,
            intent: 'invoice_status',
            data: [
                'resident_id' => $resident->id,
                'status' => $status['status'],
            ],
            actionLinks: $actionLinks,
        );
    }

    private function handleDailyChargeTotal(IntentDTO $intent, User $user, ?string $sessionId = null): ChatResponse
    {
        $resident = $this->resolveResident($intent, $user, $sessionId);

        if ($resident === null) {
            return new ChatResponse(
                reply: '入居者を特定できませんでした。名前または部屋番号を教えてください。',
                intent: 'daily_charge_total',
            );
        }

        $yearMonth = $intent->entities['year_month'] ?? now()->format('Y-m');

        if ($yearMonth > now()->format('Y-m')) {
            return new ChatResponse(
                reply: sprintf('%s さんの未来月（%s年%d月）の利用料データはまだありません。',
                    $resident->name,
                    substr($yearMonth, 0, 4),
                    (int) substr($yearMonth, 5, 2)
                ),
                intent: 'daily_charge_total',
                data: ['resident_id' => $resident->id],
            );
        }

        $total = $this->residentQueryService->getMonthlyDailyChargeTotal($resident, $yearMonth);

        return new ChatResponse(
            reply: sprintf(
                '%s さんの %s 年 %s 月の自費利用料合計: %s円',
                $resident->name,
                substr($yearMonth, 0, 4),
                (int) substr($yearMonth, 5, 2),
                number_format($total)
            ),
            intent: 'daily_charge_total',
            data: [
                'resident_id' => $resident->id,
                'year_month' => $yearMonth,
                'total' => $total,
            ],
        );
    }

    private function handleFaq(string $message, User $user): ChatResponse
    {
        $faqs = $this->faqResponder->search($message, $user->facility_id);

        if ($faqs->isEmpty()) {
            return new ChatResponse(
                reply: '申し訳ありません。回答が見つかりませんでした。担当者にお問い合わせください。',
                intent: 'faq',
                quickReplies: config('chatbot.quick_replies', []),
                categoryQuickReplies: config('chatbot.quick_reply_categories', []),
            );
        }

        $faq = $faqs->first();

        return new ChatResponse(
            reply: $faq->answer,
            intent: 'faq',
            data: ['faq_id' => $faq->id, 'category' => $faq->category],
            sources: $faqs->take(3)->map(fn ($f) => $f->question)->values()->all(),
        );
    }

    private function handleEscalate(ChatRequest $request, User $user): ChatResponse
    {
        // 1日最大5件のレート制限（悪用防止）
        $todayEscalationsCount = ChatEscalation::query()
            ->where('user_id', $user->id)
            ->whereDate('created_at', now()->toDateString())
            ->count();

        if ($todayEscalationsCount >= 5) {
            return new ChatResponse(
                reply: '本日の担当者引き継ぎリクエスト上限（5件）に達しました。緊急の場合は施設管理者またはサポート窓口へ直接お電話ください。',
                intent: 'escalate',
            );
        }

        $sessionId = $request->sessionId ?? (string) Str::uuid();

        // 直近の会話ログ（session_id 単位）を要約コンテキストとして取得
        $recentLogs = ChatLog::query()
            ->where('session_id', $sessionId)
            ->orderBy('id', 'desc')
            ->limit(5)
            ->get()
            ->reverse();

        $summaryLines = [];
        foreach ($recentLogs as $log) {
            $summaryLines[] = "ユーザー: {$log->user_message}";
            $summaryLines[] = "Bot: {$log->bot_reply}";
        }
        $summaryLines[] = "ユーザー: {$request->message}";
        $summary = implode("\n", $summaryLines);

        $escalation = ChatEscalation::create([
            'user_id' => $user->id,
            'session_id' => $sessionId,
            'summary' => $summary,
            'facility_id' => $user->facility_id,
            'status' => 'pending',
        ]);

        if (function_exists('activity')) {
            activity('chatbot')
                ->performedOn($escalation)
                ->causedBy($user)
                ->log('チャットボットから担当者への引き継ぎが発生しました');
        }

        return new ChatResponse(
            reply: '担当者に引き継ぎました。折り返しご連絡します。',
            intent: 'escalate',
            data: [
                'escalation_id' => $escalation->id,
                'status' => 'pending',
            ],
        );
    }

    private function resolveResident(IntentDTO $intent, User $user, ?string $sessionId = null): ?Resident
    {
        $keyword = $intent->entities['name'] ?? '';

        if (in_array($keyword, ['先月', '先々月', '今月', '当月'], true) || preg_match('/^\d{4}[\/\-年]\d{1,2}月?$|^\d{1,2}月$/u', $keyword) === 1) {
            $keyword = '';
        }

        if ($keyword === '') {
            if ($sessionId !== null) {
                $cachedId = Cache::get("chatbot_context:{$sessionId}:resident_id");
                if ($cachedId) {
                    return Resident::query()
                        ->where('id', $cachedId)
                        ->when($user->facility_id !== null, fn ($q) => $q->where('facility_id', $user->facility_id))
                        ->first();
                }
            }
            return null;
        }

        $candidates = $this->residentQueryService->findCandidates($keyword, $user);

        if ($candidates->isEmpty()) {
            return null;
        }

        // 請求や支払等の個人照会では、スコア0.85以上（完全一致・部分一致・カナ一致など）のみを特定とみなす
        $best = $candidates->first();
        if ($best['score'] < 0.85) {
            return null;
        }

        $resident = $best['resident'];
        if ($sessionId !== null) {
            Cache::put("chatbot_context:{$sessionId}:resident_id", $resident->id, now()->addMinutes(15));
        }

        return $resident;
    }

    private function logConversation(ChatRequest $request, IntentDTO $intent, ChatResponse $response, User $user): ?ChatLog
    {
        if (! config('chatbot.mask_names', true)) {
            $userMessage = $request->message;
            $botReply = $response->reply;
        } else {
            $userMessage = $this->maskNames($request->message, $user);
            $botReply = $this->maskNames($response->reply, $user);
        }

        $faqMatched = null;
        if ($intent->intent === 'faq') {
            $faqMatched = ! empty($response->data['faq_id']);
        }

        return ChatLog::create([
            'user_id' => $user->id,
            'session_id' => $request->sessionId ?? (string) Str::uuid(),
            'user_message' => $userMessage,
            'intent' => $intent->intent,
            'bot_reply' => $botReply,
            'faq_matched' => $faqMatched,
            'facility_id' => $user->facility_id,
        ]);
    }

    /**
     * ログ保存時に氏名をマスキングする（施設スコープ内の入居者名のみ対象）
     */
    private function maskNames(string $text, User $user): string
    {
        foreach ($this->residentQueryService->residentNames($user) as $name) {
            $text = str_replace($name, '***', $text);
        }

        return $text;
    }
}
