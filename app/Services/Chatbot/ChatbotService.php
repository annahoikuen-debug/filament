<?php

namespace App\Services\Chatbot;

use App\Models\ChatLog;
use App\Models\User;
use App\Services\Chatbot\DTO\ChatRequest;
use App\Services\Chatbot\DTO\ChatResponse;
use App\Services\Chatbot\DTO\IntentDTO;
use Illuminate\Support\Facades\Auth;

class ChatbotService
{
    public function __construct(
        private readonly IntentRecognizer $intentRecognizer,
        private readonly FaqResponder $faqResponder,
        private readonly ResidentQueryService $residentQueryService,
    ) {
    }

    public function handle(ChatRequest $request, User $user): ChatResponse
    {
        $intent = $this->intentRecognizer->recognize($request->message);

        $response = match ($intent->intent) {
            'resident_lookup' => $this->handleResidentLookup($intent, $user),
            'invoice_amount' => $this->handleInvoiceAmount($intent, $user),
            'invoice_status' => $this->handleInvoiceStatus($intent, $user),
            'daily_charge_total' => $this->handleDailyChargeTotal($intent, $user),
            default => $this->handleFaq($request->message, $user),
        };

        $this->logConversation($request, $intent, $response, $user);

        return $response;
    }

    private function handleResidentLookup(IntentDTO $intent, User $user): ChatResponse
    {
        $keyword = $intent->entities['name'] ?? $intent->entities['room'] ?? '';

        if ($keyword === '') {
            return new ChatResponse(
                reply: '入居者の名前または部屋番号を教えてください。',
                intent: 'resident_lookup',
            );
        }

        $resident = $this->residentQueryService->findResident($keyword, $user);

        if ($resident === null) {
            return new ChatResponse(
                reply: "「{$keyword}」に一致する入居者は見つかりませんでした。",
                intent: 'resident_lookup',
            );
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
        );
    }

    private function handleInvoiceAmount(IntentDTO $intent, User $user): ChatResponse
    {
        $resident = $this->resolveResident($intent, $user);

        if ($resident === null) {
            return new ChatResponse(
                reply: '入居者を特定できませんでした。名前または部屋番号を教えてください。',
                intent: 'invoice_amount',
            );
        }

        $invoice = $this->residentQueryService->getLatestInvoice($resident);

        if ($invoice === null) {
            return new ChatResponse(
                reply: "{$resident->name} さんの請求データはまだありません。",
                intent: 'invoice_amount',
                data: ['resident_id' => $resident->id],
            );
        }

        $yearMonth = $invoice->billing_year_month;
        $proration = $this->residentQueryService->getProrationBasis($resident, $yearMonth);

        $reply = sprintf(
            "%s さんの %s 年 %s 月の請求額: %s円（税込 %s円）\n内訳: 家賃 %s円 ＋ 管理費 %s円 ＋ 自費 %s円",
            $resident->name,
            substr($yearMonth, 0, 4),
            (int) substr($yearMonth, 5, 2),
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
                'billing_year_month' => $yearMonth,
                'total_amount' => $invoice->total_amount,
                'total_with_tax' => $invoice->total_with_tax,
            ],
        );
    }

    private function handleInvoiceStatus(IntentDTO $intent, User $user): ChatResponse
    {
        $resident = $this->resolveResident($intent, $user);

        if ($resident === null) {
            return new ChatResponse(
                reply: '入居者を特定できませんでした。名前または部屋番号を教えてください。',
                intent: 'invoice_status',
            );
        }

        $status = $this->residentQueryService->getPaymentStatus($resident);

        if (! $status['has_invoice']) {
            return new ChatResponse(
                reply: "{$resident->name} さんの請求データはまだありません。",
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

        return new ChatResponse(
            reply: $reply,
            intent: 'invoice_status',
            data: [
                'resident_id' => $resident->id,
                'status' => $status['status'],
            ],
        );
    }

    private function handleDailyChargeTotal(IntentDTO $intent, User $user): ChatResponse
    {
        $resident = $this->resolveResident($intent, $user);

        if ($resident === null) {
            return new ChatResponse(
                reply: '入居者を特定できませんでした。名前または部屋番号を教えてください。',
                intent: 'daily_charge_total',
            );
        }

        $yearMonth = now()->format('Y-m');
        $total = $this->residentQueryService->getMonthlyDailyChargeTotal($resident, $yearMonth);

        return new ChatResponse(
            reply: sprintf(
                "%s さんの %s 年 %s 月の自費利用料合計: %s円",
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

    private function resolveResident(IntentDTO $intent, User $user): ?\App\Models\Resident
    {
        $keyword = $intent->entities['name'] ?? '';

        if ($keyword === '') {
            return null;
        }

        return $this->residentQueryService->findResident($keyword, $user);
    }

    private function logConversation(ChatRequest $request, IntentDTO $intent, ChatResponse $response, User $user): void
    {
        if (! config('chatbot.mask_names', true)) {
            $userMessage = $request->message;
            $botReply = $response->reply;
        } else {
            $userMessage = $this->maskNames($request->message, $user);
            $botReply = $this->maskNames($response->reply, $user);
        }

        ChatLog::create([
            'user_id' => $user->id,
            'session_id' => $request->sessionId ?? (string) \Illuminate\Support\Str::uuid(),
            'user_message' => $userMessage,
            'intent' => $intent->intent,
            'bot_reply' => $botReply,
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
