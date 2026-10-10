<?php

namespace App\Http\Controllers\Api;

use App\Enums\ServiceInvoiceStatus;
use App\Enums\ServiceType;
use App\Http\Controllers\Controller;
use App\Models\Resident;
use App\Models\ServiceInvoice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class ExternalInvoiceController extends Controller
{
    /**
     * 外部システムから介護サービス請求データを一括受信
     *
     * @return JsonResponse
     *
     * Request body (JSON):
     * {
     *   "facility_id": 1,
     *   "billing_year_month": "2026-10",
     *   "invoices": [
     *     {
     *       "resident_id": 5,
     *       "service_type": "visiting_care",
     *       "external_invoice_number": "VC-202610-001",
     *       "amount": 45000,
     *       "tax_amount": 4500,
     *       "tax_rate": 10.00,
     *       "pdf_base64": "JVBERi0xLjQKJcfsj6IKNSAwIG9iago...", // optional
     *       "pdf_filename": "訪問介護請求書.pdf", // optional
     *       "status": "confirmed",
     *       "notes": "備考"
     *     }
     *   ]
     * }
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'facility_id' => ['required', 'integer', 'exists:facilities,id'],
            'billing_year_month' => ['required', 'string', 'regex:/^\d{4}-\d{2}$/'],
            'external_system_name' => ['nullable', 'string', 'max:100'],
            'invoices' => ['required', 'array', 'min:1'],
            'invoices.*.resident_id' => ['required', 'integer', 'exists:residents,id'],
            'invoices.*.service_type' => ['required', 'string', Rule::in(array_column(ServiceType::cases(), 'value'))],
            'invoices.*.external_invoice_number' => ['nullable', 'string', 'max:50'],
            'invoices.*.amount' => ['required', 'integer', 'min:0'],
            'invoices.*.tax_amount' => ['required', 'integer', 'min:0'],
            'invoices.*.tax_rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'invoices.*.pdf_base64' => ['nullable', 'string'],
            'invoices.*.pdf_filename' => ['nullable', 'string', 'max:255'],
            'invoices.*.status' => ['nullable', 'string', Rule::in(array_column(ServiceInvoiceStatus::cases(), 'value'))],
            'invoices.*.notes' => ['nullable', 'string'],
        ]);

        $facilityId = $validated['facility_id'];
        $yearMonth = $validated['billing_year_month'];
        $externalSystemName = $validated['external_system_name'] ?? 'External System';
        $invoicesData = $validated['invoices'];

        $results = [
            'created' => 0,
            'updated' => 0,
            'skipped' => 0,
            'errors' => [],
        ];

        DB::transaction(function () use ($facilityId, $yearMonth, $externalSystemName, $invoicesData, &$results) {
            foreach ($invoicesData as $index => $data) {
                try {
                    // 入居者が該当施設に属するか確認
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

                        $filename = $data['pdf_filename'] ?? sprintf(
                            '%s_%s_%s.pdf',
                            $data['service_type'],
                            $yearMonth,
                            $resident->id
                        );
                        $pdfPath = "service-invoices/{$filename}";
                        Storage::disk('local')->put($pdfPath, $pdfContent);
                        $pdfOriginalName = $filename;
                    }

                    // 既存レコード確認（ユニーク制約: resident_id + year_month + service_type + external_invoice_number）
                    $externalInvoiceNumber = $data['external_invoice_number'] ?? null;
                    $existing = ServiceInvoice::where('resident_id', $data['resident_id'])
                        ->where('billing_year_month', $yearMonth)
                        ->where('service_type', $data['service_type'])
                        ->when($externalInvoiceNumber, function ($q) use ($externalInvoiceNumber) {
                            $q->where('external_invoice_number', $externalInvoiceNumber);
                        })
                        ->first();

                    $status = $data['status'] ?? ServiceInvoiceStatus::Confirmed->value;

                    if ($existing) {
                        // 更新
                        $existing->update([
                            'facility_id' => $facilityId,
                            'service_type_label' => ServiceType::tryFrom($data['service_type'])?->getLabel() ?? $data['service_type'],
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
                            'billing_year_month' => $yearMonth,
                            'service_type' => $data['service_type'],
                            'service_type_label' => ServiceType::tryFrom($data['service_type'])?->getLabel() ?? $data['service_type'],
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
                    Log::error('External invoice import error', [
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

        Log::info('External invoices imported', $results);

        return response()->json([
            'success' => true,
            'message' => sprintf('取り込み完了: 作成 %d件, 更新 %d件, エラー %d件', $results['created'], $results['updated'], count($results['errors'])),
            'data' => $results,
        ]);
    }

    /**
     * 単一請求書の受信（代替エンドポイント）
     */
    public function storeSingle(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'facility_id' => ['required', 'integer', 'exists:facilities,id'],
            'resident_id' => ['required', 'integer', 'exists:residents,id'],
            'billing_year_month' => ['required', 'string', 'regex:/^\d{4}-\d{2}$/'],
            'service_type' => ['required', 'string', Rule::in(array_column(ServiceType::cases(), 'value'))],
            'service_type_label' => ['nullable', 'string', 'max:50'],
            'external_system_name' => ['nullable', 'string', 'max:100'],
            'external_invoice_number' => ['nullable', 'string', 'max:50'],
            'amount' => ['required', 'integer', 'min:0'],
            'tax_amount' => ['required', 'integer', 'min:0'],
            'tax_rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'pdf_base64' => ['nullable', 'string'],
            'pdf_filename' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'string', Rule::in(array_column(ServiceInvoiceStatus::cases(), 'value'))],
            'notes' => ['nullable', 'string'],
        ]);

        // 入居者施設確認
        $resident = Resident::where('id', $validated['resident_id'])
            ->where('facility_id', $validated['facility_id'])
            ->first();

        if (! $resident) {
            return response()->json([
                'success' => false,
                'message' => '入居者が見つからないか、施設が一致しません',
            ], 422);
        }

        // PDF保存
        $pdfPath = null;
        $pdfOriginalName = null;
        if (! empty($validated['pdf_base64'])) {
            $pdfContent = base64_decode($validated['pdf_base64'], true);
            if ($pdfContent === false) {
                return response()->json([
                    'success' => false,
                    'message' => '無効なPDFデータです',
                ], 422);
            }

            $filename = $validated['pdf_filename'] ?? sprintf(
                '%s_%s_%s.pdf',
                $validated['service_type'],
                $validated['billing_year_month'],
                $resident->id
            );
            $pdfPath = "service-invoices/{$filename}";
            Storage::disk('local')->put($pdfPath, $pdfContent);
            $pdfOriginalName = $filename;
        }

        $status = $validated['status'] ?? ServiceInvoiceStatus::Confirmed->value;

        $externalInvoiceNumber = $validated['external_invoice_number'] ?? null;

        $invoice = ServiceInvoice::updateOrCreate(
            [
                'resident_id' => $validated['resident_id'],
                'billing_year_month' => $validated['billing_year_month'],
                'service_type' => $validated['service_type'],
                'external_invoice_number' => $externalInvoiceNumber ?? '',
            ],
            [
                'facility_id' => $validated['facility_id'],
                'service_type_label' => $validated['service_type_label'] ?? ServiceType::tryFrom($validated['service_type'])?->getLabel() ?? $validated['service_type'],
                'external_system_name' => $validated['external_system_name'] ?? 'External System',
                'amount' => $validated['amount'],
                'tax_amount' => $validated['tax_amount'],
                'tax_rate' => $validated['tax_rate'],
                'pdf_path' => $pdfPath,
                'pdf_original_name' => $pdfOriginalName,
                'status' => $status,
                'notes' => $validated['notes'] ?? null,
            ]
        );

        return response()->json([
            'success' => true,
            'message' => $invoice->wasRecentlyCreated ? '作成しました' : '更新しました',
            'data' => [
                'id' => $invoice->id,
                'status' => $invoice->status->value,
            ],
        ]);
    }

    /**
     * 取り込み履歴・ステータス確認
     */
    public function index(Request $request): JsonResponse
    {
        $query = ServiceInvoice::with(['resident', 'facility'])
            ->when($request->facility_id, fn ($q) => $q->where('facility_id', $request->facility_id))
            ->when($request->resident_id, fn ($q) => $q->where('resident_id', $request->resident_id))
            ->when($request->billing_year_month, fn ($q) => $q->where('billing_year_month', $request->billing_year_month))
            ->when($request->service_type, fn ($q) => $q->where('service_type', $request->service_type))
            ->when($request->status, fn ($q) => $q->where('status', $request->status))
            ->when($request->external_system_name, fn ($q) => $q->where('external_system_name', $request->external_system_name))
            ->latest('created_at');

        $perPage = min($request->integer('per_page', 50), 200);
        $paginated = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $paginated->items(),
            'meta' => [
                'current_page' => $paginated->currentPage(),
                'per_page' => $paginated->perPage(),
                'total' => $paginated->total(),
                'last_page' => $paginated->lastPage(),
            ],
        ]);
    }
}
