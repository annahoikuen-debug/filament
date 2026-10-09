<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Trial;
use App\Services\TrialConversionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class TrialConversionController extends Controller
{
    public function __construct(
        private readonly TrialConversionService $conversionService,
    ) {
    }

    /**
     * トライアルを本契約に移行
     */
    public function convert(Request $request, Trial $trial)
    {
        // 認可: トライアル所有者のみが移行可能（プロビジョニングメールで通知されるトークン）
        if (!$trial->isValidConversionToken($request->input('conversion_token'))) {
            return response()->json([
                'success' => false,
                'message' => '移行トークンが不正です。',
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'plan' => ['required', Rule::in(['starter', 'standard', 'enterprise'])],
            'invoice_registration_number' => ['required', 'regex:/^T\d{13}$/'],
            'bank' => ['required', 'array'],
            'bank.name' => ['required', 'string'],
            'bank.branch_name' => ['required', 'string'],
            'bank.account_type' => ['required', Rule::in(['普通', '当座'])],
            'bank.account_number' => ['required', 'string'],
            'bank.account_holder' => ['required', 'string'],
            'contract_accepted' => ['accepted'],
            'quoted_price' => ['nullable', 'integer', 'min:1'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $alreadyConverted = $trial->status === 'converted';

            $subscription = $this->conversionService->convert($trial, array_merge(
                $validator->validated(),
                [
                    'contract_accepted_ip' => $request->ip(),
                    'contract_accepted_user_agent' => $request->userAgent(),
                ]
            ));

            return response()->json([
                'success' => true,
                'message' => $alreadyConverted ? '既に本契約に移行済みです。' : '本契約に移行しました。',
                'subscription_id' => $subscription->id,
                'plan' => $subscription->plan,
                'monthly_price' => $subscription->monthly_price,
            ], 200);
        } catch (\RuntimeException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        } catch (\Exception $e) {
            \Log::error("Trial conversion failed for trial {$trial->id}", [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => '本契約移行に失敗しました。しばらく経ってからお試しください。',
            ], 500);
        }
    }
}
