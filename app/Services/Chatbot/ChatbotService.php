<?php

namespace App\Services\Chatbot;

use App\Models\ChatEscalation;
use App\Models\ChatLog;
use App\Models\Resident;
use App\Models\User;
use App\Services\Chatbot\DTO\ChatRequest;
use App\Services\Chatbot\DTO\ChatResponse;
use App\Services\Chatbot\DTO\IntentDTO;
use Illuminate\Support\Collection;
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

    private const INQUIRY_INTENTS = [
        'invoice_amount',
        'invoice_status',
        'daily_charge_total',
        'unpaid_list',
        'billing_summary',
        'invoice_pdf',
        'daily_charge_detail',
        'service_invoice',
    ];

    public function handle(ChatRequest $request, User $user): ChatResponse
    {
        $intent = $this->intentRecognizer->recognize($request->message);
        $sessionId = $request->sessionId ?? '';

        // 主語省略（「先月は？」「未払いは？」）の文脈補完
        if ($intent->elliptical) {
            $intent = $this->mergeEllipticalContext($intent, $sessionId);
        }

        $response = match ($intent->intent) {
            'resident_lookup' => $this->handleResidentLookup($intent, $user, $sessionId),
            'invoice_amount' => $this->handleInvoiceAmount($intent, $user, $sessionId),
            'invoice_status' => $this->handleInvoiceStatus($intent, $user, $sessionId),
            'daily_charge_total' => $this->handleDailyChargeTotal($intent, $user, $sessionId),
            'unpaid_list' => $this->handleUnpaidList($intent, $user, $sessionId),
            'billing_summary' => $this->handleBillingSummary($intent, $user, $sessionId),
            'invoice_pdf' => $this->handleInvoicePdf($intent, $user, $sessionId),
            'daily_charge_detail' => $this->handleDailyChargeDetail($intent, $user, $sessionId),
            'service_invoice' => $this->handleServiceInvoice($intent, $user, $sessionId),
            'escalate' => $this->handleEscalate($request, $user),
            default => $this->handleFaq($request->message, $user),
        };

        // 成功した照会の文脈を保存（失敗時は last_intent を上書きしない）
        $this->saveInquiryContext($intent, $sessionId, $response);

        $log = $this->logConversation($request, $intent, $response, $user);

        return $response->withChatLogId($log?->id);
    }

    /**
     * 省略形質問に直前文脈（入居者・intent・年月）を補完する
     */
    private function mergeEllipticalContext(IntentDTO $intent, string $sessionId): IntentDTO
    {
        $entities = [];

        // 年月のみの質問（「先月は？」）→ 直前 intent を引き継ぐ
        if (! in_array($intent->intent, self::INQUIRY_INTENTS, true)) {
            $lastIntent = ChatbotSessionContext::get($sessionId, 'last_intent');
            if (is_string($lastIntent) && in_array($lastIntent, self::INQUIRY_INTENTS, true)) {
                $intent = new IntentDTO(intent: $lastIntent, entities: $intent->entities, elliptical: true);
            } else {
                // 文脈なし → FAQ にフォールバック（現行動作）
                return new IntentDTO(intent: 'faq', entities: $intent->entities);
            }
        }

        // 年月が無ければ直前の年月を補完
        if (! isset($intent->entities['year_month'])) {
            $lastYearMonth = ChatbotSessionContext::get($sessionId, 'last_year_month');
            if (is_string($lastYearMonth) && $lastYearMonth !== '') {
                $entities['year_month'] = $lastYearMonth;
            }
        }

        return $intent->withEntities($entities);
    }

    /**
     * 照会成功時に文脈を保存する
     */
    private function saveInquiryContext(IntentDTO $intent, string $sessionId, ChatResponse $response): void
    {
        if ($sessionId === '' || ! in_array($intent->intent, self::INQUIRY_INTENTS, true)) {
            return;
        }

        // データが取れた（resident_id が設定された）場合のみ成功とみなす
        if (! isset($response->data['resident_id'])) {
            return;
        }

        ChatbotSessionContext::put($sessionId, 'last_intent', $intent->intent);
        if (isset($intent->entities['year_month'])) {
            ChatbotSessionContext::put($sessionId, 'last_year_month', $intent->entities['year_month']);
        }
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
            // 候補確認質問: 在籍者上位5名をクイックリプライで提示
            $suggestions = $this->activeResidentsForSuggestions($user, $keyword);
            $quickReplies = [];
            foreach ($suggestions as $resident) {
                $label = sprintf('%s（%s号室）', $resident->name, $resident->room_number);
                $quickReplies[$label] = sprintf('%s の情報を教えてください', $resident->name);
            }

            $reply = "「{$keyword}」に一致する入居者は見つかりませんでした。";
            if ($quickReplies !== []) {
                $reply .= "\nこちらの中にお探しの方はいますか？";
            }

            return new ChatResponse(
                reply: $reply,
                intent: 'resident_lookup',
                quickReplies: $quickReplies,
                categoryQuickReplies: config('chatbot.quick_reply_categories', []),
            );
        }

        // 複数候補があり、最上位のスコアが1.0未満（完全一致でない）で複数件ある場合は確認応答
        if ($candidates->count() > 1 && $candidates->first()['score'] < 1.0) {
            $topCandidates = $candidates->take(3);
            $names = $topCandidates->map(fn ($c) => sprintf('%s（%s号室）', $c['resident']->name, $c['resident']->room_number))->join('、');

            $candidateRows = $topCandidates->map(fn ($c) => [
                $c['resident']->name,
                $c['resident']->room_number,
            ])->values()->all();

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
                displayType: 'candidates',
                columns: ['氏名', '部屋番号'],
                rows: $candidateRows,
            );
        }

        $resident = $candidates->first()['resident'];

        if ($sessionId !== null) {
            ChatbotSessionContext::put($sessionId, 'resident_id', $resident->id);
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

    /**
     * 未払い入居者一覧
     */
    private function handleUnpaidList(IntentDTO $intent, User $user, ?string $sessionId = null): ChatResponse
    {
        $unpaid = $this->residentQueryService->getUnpaidResidents($user);

        if ($unpaid->isEmpty()) {
            return new ChatResponse(
                reply: '未払いの入居者はいません。',
                intent: 'unpaid_list',
                quickReplies: config('chatbot.quick_replies', []),
                categoryQuickReplies: config('chatbot.quick_reply_categories', []),
            );
        }

        $lines = ['未払い入居者一覧:'];
        $totalAmount = 0;
        foreach ($unpaid as $item) {
            $resident = $item['resident'];
            $invoice = $item['invoice'];
            $ym = $item['billing_year_month'];
            $amount = $item['amount'];
            $totalAmount += $amount;
            $lines[] = sprintf(
                '・%s（%s号室） %s年%s月: %s円',
                $resident->name,
                $resident->room_number,
                substr($ym, 0, 4),
                (int) substr($ym, 5, 2),
                number_format($amount)
            );
        }
        $lines[] = sprintf('\n合計未回収額: %s円', number_format($totalAmount));
        $lines[] = sprintf('対象: %d名', $unpaid->count());

        $rows = $unpaid->map(fn ($item) => [
            $item['resident']->name,
            $item['resident']->room_number,
            $item['billing_year_month'],
            number_format($item['amount']).'円',
        ])->values()->all();

        return new ChatResponse(
            reply: implode("\n", $lines),
            intent: 'unpaid_list',
            data: [
                'unpaid_list' => $unpaid->map(fn ($item) => [
                    'resident_id' => $item['resident']->id,
                    'resident_name' => $item['resident']->name,
                    'room_number' => $item['resident']->room_number,
                    'billing_year_month' => $item['billing_year_month'],
                    'amount' => $item['amount'],
                ])->values()->all(),
                'total_amount' => $totalAmount,
                'count' => $unpaid->count(),
            ],
            actionLinks: [
                $this->linkGenerator->invoiceList(),
            ],
            displayType: 'table',
            columns: ['氏名', '部屋番号', '請求月', '未払い額'],
            rows: $rows,
        );
    }

    /**
     * 月次請求集計（売上・未回収・入金済み）
     */
    private function handleBillingSummary(IntentDTO $intent, User $user, ?string $sessionId = null): ChatResponse
    {
        $yearMonth = $intent->entities['year_month'] ?? now()->format('Y-m');

        $summary = $this->residentQueryService->getBillingSummary($user, $yearMonth);

        $reply = sprintf(
            "%s年%s月の請求集計:\n"
            ."総請求額: %s円\n"
            ."入金済み: %s円 (%d件)\n"
            ."未回収: %s円 (%d件)\n"
            .'請求件数: %d件',
            substr($summary['year_month'], 0, 4),
            (int) substr($summary['year_month'], 5, 2),
            number_format($summary['total_billed']),
            number_format($summary['total_billed'] - $summary['total_unpaid']),
            $summary['paid_count'],
            number_format($summary['total_unpaid']),
            $summary['unpaid_count'],
            $summary['total_count']
        );

        return new ChatResponse(
            reply: $reply,
            intent: 'billing_summary',
            data: $summary,
            actionLinks: [
                $this->linkGenerator->invoiceList(),
            ],
        );
    }

    /**
     * 請求書PDF（指定入居者・指定月）
     */
    private function handleInvoicePdf(IntentDTO $intent, User $user, ?string $sessionId = null): ChatResponse
    {
        $resident = $this->resolveResident($intent, $user, $sessionId);

        if ($resident === null) {
            return new ChatResponse(
                reply: '入居者を特定できませんでした。名前または部屋番号を教えてください。',
                intent: 'invoice_pdf',
                quickReplies: $this->activeResidentsForSuggestions($user, '')->mapWithKeys(fn ($r) => [
                    sprintf('%s（%s号室）', $r->name, $r->room_number) => sprintf('%s の請求書PDFを教えてください', $r->name),
                ])->toArray(),
                categoryQuickReplies: config('chatbot.quick_reply_categories', []),
            );
        }

        $yearMonth = $intent->entities['year_month'] ?? null;

        if ($yearMonth !== null && $yearMonth > now()->format('Y-m')) {
            return new ChatResponse(
                reply: sprintf('%s さんの未来月（%s年%d月）の請求書はまだ作成されていません。',
                    $resident->name,
                    substr($yearMonth, 0, 4),
                    (int) substr($yearMonth, 5, 2)
                ),
                intent: 'invoice_pdf',
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
                intent: 'invoice_pdf',
                data: ['resident_id' => $resident->id],
            );
        }

        $billingYm = $invoice->billing_year_month;

        // PDF生成URL（実際のPDF生成エンドポイントへのリンク・認証必須ルート）
        $pdfUrl = route('invoices.pdf.download', ['invoice' => $invoice->id]);

        return new ChatResponse(
            reply: sprintf(
                '%s さんの %s 年 %s 月の請求書PDFを生成できます。',
                $resident->name,
                substr($billingYm, 0, 4),
                (int) substr($billingYm, 5, 2)
            ),
            intent: 'invoice_pdf',
            data: [
                'resident_id' => $resident->id,
                'billing_year_month' => $billingYm,
                'pdf_url' => $pdfUrl,
            ],
            actionLinks: [
                [
                    'label' => '📄 請求書PDFをダウンロード',
                    'url' => $pdfUrl,
                    'color' => 'success',
                ],
                $this->linkGenerator->invoiceEdit($invoice->id),
                $this->linkGenerator->residentEdit($resident->id),
            ],
        );
    }

    /**
     * 自費利用料明細（指定入居者・指定月）
     */
    private function handleDailyChargeDetail(IntentDTO $intent, User $user, ?string $sessionId = null): ChatResponse
    {
        $resident = $this->resolveResident($intent, $user, $sessionId);

        if ($resident === null) {
            return new ChatResponse(
                reply: '入居者を特定できませんでした。名前または部屋番号を教えてください。',
                intent: 'daily_charge_detail',
                quickReplies: $this->activeResidentsForSuggestions($user, '')->mapWithKeys(fn ($r) => [
                    sprintf('%s（%s号室）', $r->name, $r->room_number) => sprintf('%s の自費明細を教えてください', $r->name),
                ])->toArray(),
                categoryQuickReplies: config('chatbot.quick_reply_categories', []),
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
                intent: 'daily_charge_detail',
                data: ['resident_id' => $resident->id],
            );
        }

        $details = $this->residentQueryService->getDailyChargeDetails($resident, $yearMonth);

        if ($details->isEmpty()) {
            return new ChatResponse(
                reply: sprintf('%s さんの %s 年 %s 月の自費利用料明細はありません。',
                    $resident->name,
                    substr($yearMonth, 0, 4),
                    (int) substr($yearMonth, 5, 2)
                ),
                intent: 'daily_charge_detail',
                data: ['resident_id' => $resident->id, 'year_month' => $yearMonth],
            );
        }

        $lines = [sprintf('%s さんの %s 年 %s 月の自費利用料明細:', $resident->name, substr($yearMonth, 0, 4), (int) substr($yearMonth, 5, 2))];
        $total = 0;
        foreach ($details as $d) {
            $lines[] = sprintf('・%s %s: %s円 × %d = %s円', $d['date'], $d['item_name'], number_format($d['unit_price']), $d['quantity'], number_format($d['subtotal']));
            $total += $d['subtotal'];
        }
        $lines[] = sprintf('\n合計: %s円', number_format($total));

        $chargeRows = $details->map(fn ($d) => [
            $d['date'],
            $d['item_name'],
            number_format($d['unit_price']).'円',
            (string) $d['quantity'],
            number_format($d['subtotal']).'円',
        ])->values()->all();

        return new ChatResponse(
            reply: implode("\n", $lines),
            intent: 'daily_charge_detail',
            data: [
                'resident_id' => $resident->id,
                'year_month' => $yearMonth,
                'details' => $details->values()->all(),
                'total' => $total,
            ],
            displayType: 'table',
            columns: ['日付', '品目', '単価', '数量', '小計'],
            rows: $chargeRows,
        );
    }

    /**
     * 介護保険請求（サービス請求）状況
     */
    private function handleServiceInvoice(IntentDTO $intent, User $user, ?string $sessionId = null): ChatResponse
    {
        $resident = $this->resolveResident($intent, $user, $sessionId);

        if ($resident === null) {
            return new ChatResponse(
                reply: '入居者を特定できませんでした。名前または部屋番号を教えてください。',
                intent: 'service_invoice',
                quickReplies: $this->activeResidentsForSuggestions($user, '')->mapWithKeys(fn ($r) => [
                    sprintf('%s（%s号室）', $r->name, $r->room_number) => sprintf('%s の介護請求を教えてください', $r->name),
                ])->toArray(),
                categoryQuickReplies: config('chatbot.quick_reply_categories', []),
            );
        }

        $yearMonth = $intent->entities['year_month'] ?? null;

        $invoices = $this->residentQueryService->getServiceInvoices($resident, $yearMonth);

        if ($invoices->isEmpty()) {
            $monthNotice = $yearMonth !== null
                ? sprintf('%s年%d月の', substr($yearMonth, 0, 4), (int) substr($yearMonth, 5, 2))
                : '';

            return new ChatResponse(
                reply: "{$resident->name} さんの{$monthNotice}介護保険請求データはありません。",
                intent: 'service_invoice',
                data: ['resident_id' => $resident->id],
            );
        }

        $lines = [sprintf('%s さんの介護保険請求一覧:', $resident->name)];
        $totalAmount = 0;
        foreach ($invoices as $inv) {
            $ym = $inv['billing_year_month'];
            $lines[] = sprintf(
                '・%s年%s月 %s: %s円（税込 %s円）[%s]',
                substr($ym, 0, 4),
                (int) substr($ym, 5, 2),
                $inv['service_type_label'],
                number_format($inv['amount']),
                number_format($inv['total_with_tax']),
                $inv['status_label']
            );
            $totalAmount += $inv['amount'];
        }
        $lines[] = sprintf('\n合計請求額: %s円', number_format($totalAmount));

        return new ChatResponse(
            reply: implode("\n", $lines),
            intent: 'service_invoice',
            data: [
                'resident_id' => $resident->id,
                'invoices' => $invoices->values()->all(),
                'total_amount' => $totalAmount,
            ],
            actionLinks: [
                $this->linkGenerator->residentEdit($resident->id),
            ],
        );
    }

    private function handleFaq(string $message, User $user): ChatResponse
    {
        $faqs = $this->faqResponder->search($message, $user->facility_id);

        if ($faqs->isEmpty()) {
            // 「もしかして」: 低閾値で類似 FAQ を再検索し上位2件を提示
            $maybe = $this->faqResponder->searchLoose($message, $user->facility_id, 2);
            $quickReplies = [];
            foreach ($maybe as $faq) {
                $quickReplies["もしかして: {$faq->question}"] = $faq->question;
            }

            $maybeRows = $maybe->map(fn ($f) => [$f->question])->values()->all();

            return new ChatResponse(
                reply: $maybe->isNotEmpty()
                    ? '申し訳ありません。回答が見つかりませんでした。こちらの質問はいかがでしょうか？'
                    : '申し訳ありません。回答が見つかりませんでした。担当者にお問い合わせください。',
                intent: 'faq',
                data: $maybe->isNotEmpty() ? ['maybe_faqs' => $maybe->map(fn ($f) => $f->id)->values()->all()] : [],
                quickReplies: $quickReplies ?: config('chatbot.quick_replies', []),
                categoryQuickReplies: config('chatbot.quick_reply_categories', []),
                displayType: $maybe->isNotEmpty() ? 'list' : 'text',
                columns: $maybe->isNotEmpty() ? ['候補'] : [],
                rows: $maybeRows,
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

    /**
     * 候補確認質問用: 同 facility の在籍者上位 N 名（キーワードを名前に含まない者を優先）
     *
     * @return Collection<int, Resident>
     */
    private function activeResidentsForSuggestions(User $user, string $excludeKeyword): Collection
    {
        $excludeKeyword = trim($excludeKeyword);

        return Resident::query()
            ->where('status', 'active')
            ->when($user->facility_id !== null, fn ($q) => $q->where('facility_id', $user->facility_id))
            ->when($excludeKeyword !== '', fn ($q) => $q->where(fn ($qq) => $qq
                ->where('name', 'not like', "%{$excludeKeyword}%")
                ->orWhereNull('name')))
            ->orderBy('name_kana')
            ->limit(5)
            ->get();
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
                $cachedId = ChatbotSessionContext::get($sessionId, 'resident_id');
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
            ChatbotSessionContext::put($sessionId, 'resident_id', $resident->id);
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
