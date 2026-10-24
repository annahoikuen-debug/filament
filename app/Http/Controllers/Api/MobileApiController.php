<?php

namespace App\Http\Controllers\Api;

use App\Enums\ServiceInvoiceStatus;
use App\Enums\ServiceType;
use App\Http\Controllers\Controller;
use App\Models\ApiToken;
use App\Models\ChargeItem;
use App\Models\DailyCharge;
use App\Models\Resident;
use App\Models\ServiceInvoice;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class MobileApiController extends Controller
{
    /**
     * Bearer トークンによるリクエスト認証
     */
    private function authenticateRequest(Request $request, string $ability): ?ApiToken
    {
        $plainToken = $request->bearerToken();

        if (! $plainToken) {
            return null;
        }

        $token = ApiToken::findValidToken($plainToken, $ability);

        if ($token) {
            try {
                $token->forceFill(['last_used_at' => now()])->save();
            } catch (\Throwable $e) {
                // ログに残さず継続
            }
        }

        return $token;
    }

    /**
     * トークンが指定施設に対して認可されているか確認
     */
    private function authorizeFacility(ApiToken $token, int $facilityId): bool
    {
        return (int) $token->facility_id === $facilityId;
    }

    /**
     * 認証レスポンス（401）
     */
    private function unauthorizedResponse(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'Unauthorized: Invalid or missing API token',
        ], 401);
    }

    /**
     * 認可レスポンス（403）
     */
    private function forbiddenResponse(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'Forbidden: Token not authorized for this facility',
        ], 403);
    }

    /**
     * 施設の入居者一覧を取得（スマホ用・軽量版）
     */
    public function residents(Request $request): JsonResponse
    {
        $token = $this->authenticateRequest($request, 'mobile.read');
        if (! $token) {
            return $this->unauthorizedResponse();
        }

        $facilityId = (int) $request->query('facility_id');
        if (! $this->authorizeFacility($token, $facilityId)) {
            return $this->forbiddenResponse();
        }

        $residents = Resident::where('facility_id', $facilityId)
            ->where('status', 'active')
            ->select('id', 'room_number', 'name', 'name_kana')
            ->orderBy('room_number')
            ->get()
            ->map(fn ($r) => [
                'id' => $r->id,
                'room_number' => $r->room_number,
                'name' => $r->name,
                'name_kana' => $r->name_kana,
                'display_name' => "[{$r->room_number}] {$r->name}",
            ]);

        return response()->json([
            'success' => true,
            'data' => $residents,
        ]);
    }

    /**
     * 施設の消耗品品目一覧を取得（スマホ用・軽量版）
     */
    public function chargeItems(Request $request): JsonResponse
    {
        $token = $this->authenticateRequest($request, 'mobile.read');
        if (! $token) {
            return $this->unauthorizedResponse();
        }

        $facilityId = (int) $request->query('facility_id');
        if (! $this->authorizeFacility($token, $facilityId)) {
            return $this->forbiddenResponse();
        }

        $items = ChargeItem::where('facility_id', $facilityId)
            ->where('is_active', true)
            ->select('id', 'name', 'display_name', 'default_price', 'tax_type', 'category')
            ->orderBy('category')
            ->orderBy('name')
            ->get()
            ->map(fn ($item) => [
                'id' => $item->id,
                'name' => $item->name,
                'display_name' => $item->display_name,
                'default_price' => $item->default_price,
                'tax_type' => $item->tax_type?->value,
                'category' => $item->category,
            ]);

        return response()->json([
            'success' => true,
            'data' => $items,
        ]);
    }

    /**
     * 日々の消耗品利用を記録（作成・更新）
     */
    public function dailyCharges(Request $request): JsonResponse
    {
        $token = $this->authenticateRequest($request, 'mobile.write');
        if (! $token) {
            return $this->unauthorizedResponse();
        }

        $validated = $request->validate([
            'facility_id' => ['required', 'integer', 'exists:facilities,id'],
            'charges' => ['required', 'array', 'min:1'],
            'charges.*.resident_id' => ['required', 'integer', 'exists:residents,id'],
            'charges.*.charge_item_id' => ['required', 'integer', 'exists:charge_items,id'],
            'charges.*.date' => ['required', 'date', 'date_format:Y-m-d'],
            'charges.*.quantity' => ['required', 'integer', 'min:0'],
            'charges.*.note' => ['nullable', 'string', 'max:500'],
        ]);

        $facilityId = $validated['facility_id'];
        if (! $this->authorizeFacility($token, (int) $facilityId)) {
            return $this->forbiddenResponse();
        }

        $results = [
            'created' => 0,
            'updated' => 0,
            'errors' => [],
        ];

        DB::transaction(function () use ($facilityId, $validated, &$results) {
            foreach ($validated['charges'] as $index => $data) {
                try {
                    $resident = Resident::where('id', $data['resident_id'])
                        ->where('facility_id', $facilityId)
                        ->first();

                    if (! $resident) {
                        $results['errors'][] = [
                            'index' => $index,
                            'resident_id' => $data['resident_id'],
                            'error' => '入居者が見つからないか、施設が一致しません',
                        ];

                        continue;
                    }

                    $chargeItem = ChargeItem::where('id', $data['charge_item_id'])
                        ->where('facility_id', $facilityId)
                        ->first();

                    if (! $chargeItem) {
                        $results['errors'][] = [
                            'index' => $index,
                            'charge_item_id' => $data['charge_item_id'],
                            'error' => '品目が見つからないか、施設が一致しません',
                        ];

                        continue;
                    }

                    // 利用日が入居期間内かチェック
                    if (! $resident->isLivingAt($data['date'])) {
                        $results['errors'][] = [
                            'index' => $index,
                            'resident_id' => $data['resident_id'],
                            'error' => '利用日が入居者の在籍期間外です',
                        ];

                        continue;
                    }

                    // 単価を品目から取得（スナップショット）
                    $date = Carbon::parse($data['date']);
                    $unitPrice = $chargeItem->getPriceForDate($date) ?? $chargeItem->default_price;

                    // 既存レコード確認（同一入居者・品目・日付）
                    $existing = DailyCharge::where('resident_id', $data['resident_id'])
                        ->where('charge_item_id', $data['charge_item_id'])
                        ->whereDate('date', $data['date'])
                        ->first();

                    if ($existing) {
                        $existing->update([
                            'quantity' => $data['quantity'],
                            'unit_price' => $unitPrice,
                            'note' => $data['note'] ?? null,
                        ]);
                        $results['updated']++;
                    } else {
                        DailyCharge::create([
                            'facility_id' => $facilityId,
                            'resident_id' => $data['resident_id'],
                            'charge_item_id' => $data['charge_item_id'],
                            'date' => $data['date'],
                            'unit_price' => $unitPrice,
                            'quantity' => $data['quantity'],
                            'note' => $data['note'] ?? null,
                        ]);
                        $results['created']++;
                    }
                } catch (\Throwable $e) {
                    Log::error('Mobile daily charge error', [
                        'index' => $index,
                        'data' => $data,
                        'error' => $e->getMessage(),
                    ]);
                    $results['errors'][] = [
                        'index' => $index,
                        'error' => $e->getMessage(),
                    ];
                }
            }
        });

        return response()->json([
            'success' => true,
            'message' => sprintf('記録完了: 作成 %d件, 更新 %d件, エラー %d件', $results['created'], $results['updated'], count($results['errors'])),
            'data' => $results,
        ]);
    }

    /**
     * 介護保険請求データを記録（作成・更新・PDF添付）
     */
    public function serviceInvoices(Request $request): JsonResponse
    {
        $token = $this->authenticateRequest($request, 'mobile.write');
        if (! $token) {
            return $this->unauthorizedResponse();
        }

        $validated = $request->validate([
            'facility_id' => ['required', 'integer', 'exists:facilities,id'],
            'invoices' => ['required', 'array', 'min:1'],
            'invoices.*.resident_id' => ['required', 'integer', 'exists:residents,id'],
            'invoices.*.billing_year_month' => ['required', 'string', 'regex:/^\d{4}-\d{2}$/'],
            'invoices.*.service_type' => ['required', 'string', Rule::in(array_column(ServiceType::cases(), 'value'))],
            'invoices.*.amount' => ['required', 'integer', 'min:0'],
            'invoices.*.tax_amount' => ['required', 'integer', 'min:0'],
            'invoices.*.tax_rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'invoices.*.pdf_base64' => ['nullable', 'string'],
            'invoices.*.pdf_filename' => ['nullable', 'string', 'max:255'],
            'invoices.*.status' => ['nullable', 'string', Rule::in(array_column(ServiceInvoiceStatus::cases(), 'value'))],
            'invoices.*.notes' => ['nullable', 'string'],
            'invoices.*.external_invoice_number' => ['nullable', 'string', 'max:50'],
            'invoices.*.external_system_name' => ['nullable', 'string', 'max:100'],
        ]);

        $facilityId = $validated['facility_id'];
        if (! $this->authorizeFacility($token, (int) $facilityId)) {
            return $this->forbiddenResponse();
        }

        $results = [
            'created' => 0,
            'updated' => 0,
            'errors' => [],
        ];

        DB::transaction(function () use ($facilityId, $validated, &$results) {
            foreach ($validated['invoices'] as $index => $data) {
                try {
                    $resident = Resident::where('id', $data['resident_id'])
                        ->where('facility_id', $facilityId)
                        ->first();

                    if (! $resident) {
                        $results['errors'][] = [
                            'index' => $index,
                            'resident_id' => $data['resident_id'],
                            'error' => '入居者が見つからないか、施設が一致しません',
                        ];

                        continue;
                    }

                    // PDF保存処理
                    $pdfPath = null;
                    $pdfOriginalName = null;
                    if (! empty($data['pdf_base64'])) {
                        $pdfContent = base64_decode($data['pdf_base64'], true);
                        if ($pdfContent === false) {
                            throw new \InvalidArgumentException('Invalid base64 PDF data');
                        }

                        $filename = $this->sanitizePdfFilename($data['pdf_filename'] ?? null) ?? sprintf(
                            '%s_%s_%s.pdf',
                            $data['service_type'],
                            $data['billing_year_month'],
                            $resident->id
                        );
                        // 施設・入居者ごとのディレクトリに分離（他入居者のPDFを上書きできないようにする）
                        $pdfPath = "service-invoices/{$facilityId}/{$resident->id}/{$filename}";
                        Storage::disk('local')->put($pdfPath, $pdfContent);
                        $pdfOriginalName = $filename;
                    }

                    $externalInvoiceNumber = $data['external_invoice_number'] ?? null;
                    $status = $data['status'] ?? ServiceInvoiceStatus::Draft->value;

                    // 既存レコード確認
                    $existing = ServiceInvoice::where('resident_id', $data['resident_id'])
                        ->where('billing_year_month', $data['billing_year_month'])
                        ->where('service_type', $data['service_type'])
                        ->when($externalInvoiceNumber, fn ($q) => $q->where('external_invoice_number', $externalInvoiceNumber))
                        ->first();

                    $serviceTypeLabel = ServiceType::tryFrom($data['service_type'])?->getLabel() ?? $data['service_type'];
                    $externalSystemName = $data['external_system_name'] ?? 'Mobile App';

                    if ($existing) {
                        // 更新（PDFが新規送信された場合のみ差し替え）
                        $existing->update([
                            'facility_id' => $facilityId,
                            'service_type_label' => $serviceTypeLabel,
                            'external_system_name' => $externalSystemName,
                            'external_invoice_number' => $externalInvoiceNumber ?? $existing->external_invoice_number,
                            'amount' => $data['amount'],
                            'tax_amount' => $data['tax_amount'],
                            'tax_rate' => $data['tax_rate'],
                            'pdf_path' => $pdfPath ?? $existing->pdf_path,
                            'pdf_original_name' => $pdfOriginalName ?? $existing->pdf_original_name,
                            'status' => $status,
                            'notes' => $data['notes'] ?? $existing->notes,
                        ]);
                        $results['updated']++;
                    } else {
                        // 新規作成
                        ServiceInvoice::create([
                            'facility_id' => $facilityId,
                            'resident_id' => $data['resident_id'],
                            'billing_year_month' => $data['billing_year_month'],
                            'service_type' => $data['service_type'],
                            'service_type_label' => $serviceTypeLabel,
                            'external_system_name' => $externalSystemName,
                            'external_invoice_number' => $externalInvoiceNumber,
                            'amount' => $data['amount'],
                            'tax_amount' => $data['tax_amount'],
                            'tax_rate' => $data['tax_rate'],
                            'pdf_path' => $pdfPath,
                            'pdf_original_name' => $pdfOriginalName,
                            'status' => $status,
                            'notes' => $data['notes'] ?? null,
                        ]);
                        $results['created']++;
                    }
                } catch (\Throwable $e) {
                    Log::error('Mobile service invoice error', [
                        'index' => $index,
                        'data' => $data,
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString(),
                    ]);
                    $results['errors'][] = [
                        'index' => $index,
                        'resident_id' => $data['resident_id'] ?? null,
                        'error' => $e->getMessage(),
                    ];
                }
            }
        });

        return response()->json([
            'success' => true,
            'message' => sprintf('記録完了: 作成 %d件, 更新 %d件, エラー %d件', $results['created'], $results['updated'], count($results['errors'])),
            'data' => $results,
        ]);
    }

    /**
     * 記録済みの日々の消耗品一覧を取得（確認用）
     */
    /**
     * 外部から渡されたPDFファイル名をサニタイズする
     *
     * パス区切り文字と ".." を除去し、service-invoices 配下からの脱出や
     * 他入居者のPDFの上書き（例: "../../1/2/他入居者.pdf"）を防止する。
     */
    private function sanitizePdfFilename(?string $filename): ?string
    {
        if ($filename === null || trim($filename) === '') {
            return null;
        }

        // パスコンポーネントを除去（\ も区切りとして扱う）
        $safe = basename(str_replace('\\', '/', $filename));
        // 許可: 文字・数字・ピリオド・ハイフン・アンダースコアのみ（空白・制御文字は置換）
        $safe = preg_replace('/[^\p{L}\p{N}._\-]/u', '_', $safe) ?? '';
        // 先頭の "." で隠しファイル化・ ".." 化を防ぐ
        $safe = ltrim($safe, '.');
        $safe = mb_substr($safe, 0, 200);

        if ($safe === '') {
            return null;
        }

        if (strtolower(pathinfo($safe, PATHINFO_EXTENSION)) !== 'pdf') {
            $safe .= '.pdf';
        }

        return $safe;
    }

    public function dailyChargesList(Request $request): JsonResponse
    {
        $token = $this->authenticateRequest($request, 'mobile.read');
        if (! $token) {
            return $this->unauthorizedResponse();
        }

        $facilityId = (int) $request->query('facility_id');
        if (! $this->authorizeFacility($token, $facilityId)) {
            return $this->forbiddenResponse();
        }

        $yearMonth = $request->query('billing_year_month');
        if (! $yearMonth || ! preg_match('/^\d{4}-\d{2}$/', $yearMonth)) {
            return response()->json([
                'success' => false,
                'message' => 'billing_year_month (YYYY-MM) is required',
            ], 422);
        }

        $charges = DailyCharge::with(['resident:id,room_number,name', 'chargeItem:id,name,display_name'])
            ->where('facility_id', $facilityId)
            ->forYearMonth($yearMonth)
            ->latest('date')
            ->get()
            ->map(fn ($c) => [
                'id' => $c->id,
                'date' => $c->date->format('Y-m-d'),
                'resident' => $c->resident ? [
                    'id' => $c->resident->id,
                    'room_number' => $c->resident->room_number,
                    'name' => $c->resident->name,
                ] : null,
                'charge_item' => $c->chargeItem ? [
                    'id' => $c->chargeItem->id,
                    'name' => $c->chargeItem->name,
                    'display_name' => $c->chargeItem->display_name,
                ] : null,
                'unit_price' => $c->unit_price,
                'quantity' => $c->quantity,
                'subtotal' => $c->subtotal,
                'note' => $c->note,
            ]);

        return response()->json([
            'success' => true,
            'data' => $charges,
        ]);
    }

    /**
     * 記録済みの介護保険請求一覧を取得（確認用）
     */
    public function serviceInvoicesList(Request $request): JsonResponse
    {
        $token = $this->authenticateRequest($request, 'mobile.read');
        if (! $token) {
            return $this->unauthorizedResponse();
        }

        $facilityId = (int) $request->query('facility_id');
        if (! $this->authorizeFacility($token, $facilityId)) {
            return $this->forbiddenResponse();
        }

        $query = ServiceInvoice::with(['resident:id,room_number,name'])
            ->where('facility_id', $facilityId)
            ->when($request->billing_year_month, fn ($q) => $q->where('billing_year_month', $request->billing_year_month))
            ->when($request->resident_id, fn ($q) => $q->where('resident_id', $request->resident_id))
            ->when($request->service_type, fn ($q) => $q->where('service_type', $request->service_type))
            ->when($request->status, fn ($q) => $q->where('status', $request->status))
            ->latest('billing_year_month')
            ->latest('created_at');

        $perPage = min($request->integer('per_page', 50), 200);
        $paginated = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => collect($paginated->items())->map(fn ($i) => [
                'id' => $i->id,
                'billing_year_month' => $i->billing_year_month,
                'resident' => $i->resident ? [
                    'id' => $i->resident->id,
                    'room_number' => $i->resident->room_number,
                    'name' => $i->resident->name,
                ] : null,
                'service_type' => $i->service_type,
                'service_type_label' => $i->service_type_label,
                'amount' => $i->amount,
                'tax_amount' => $i->tax_amount,
                'tax_rate' => $i->tax_rate,
                'total_with_tax' => $i->total_with_tax,
                'has_pdf' => $i->hasPdf(),
                'pdf_original_name' => $i->pdf_original_name,
                'status' => $i->status->value,
                'external_invoice_number' => $i->external_invoice_number,
                'notes' => $i->notes,
            ]),
            'meta' => [
                'current_page' => $paginated->currentPage(),
                'per_page' => $paginated->perPage(),
                'total' => $paginated->total(),
                'last_page' => $paginated->lastPage(),
            ],
        ]);
    }
}
