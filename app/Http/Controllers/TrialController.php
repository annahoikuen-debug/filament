<?php

namespace App\Http\Controllers;

use App\Models\Trial;
use App\Services\LeadScoringService;
use App\Services\TrialProvisioningService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class TrialController extends Controller
{
    protected $provisioningService;

    protected $scoringService;

    public function __construct(
        TrialProvisioningService $provisioningService,
        LeadScoringService $scoringService,
    ) {
        $this->provisioningService = $provisioningService;
        $this->scoringService = $scoringService;
    }

    /**
     * Store a newly created trial in storage.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'company_name' => 'required|string|max:255',
            'contact_name' => 'required|string|max:100',
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('trials', 'email')->whereNull('deleted_at'),
            ],
            'phone' => 'nullable|string|max:20',
            'facility_type' => [
                'required',
                Rule::in(['special_nursing', 'nursing_health', 'medical_care',
                    'paid_elderly', 'group_home', 'home_care', 'other']),
            ],
            'resident_capacity' => [
                'required',
                Rule::in(['under_30', '30_50', '50_100', '100_200', 'over_200', 'unknown']),
            ],
            'seed_sample_data' => 'nullable|boolean',
            'challenges' => 'nullable|array',
            'challenges.*' => 'string',
            'budget' => ['nullable', Rule::in(['under_10k', '10k_30k', '30k_50k', 'over_50k', 'undecided'])],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        // リードスコアリング
        $score = $this->scoringService->score(array_merge(
            $validator->validated(),
            ['challenges' => $request->input('challenges', [])]
        ));

        $trial = Trial::create(array_merge(
            $validator->validated(),
            [
                'score' => $score,
                'trial_config' => [
                    'seed_sample_data' => $request->boolean('seed_sample_data'),
                    'lead_tier' => $this->scoringService->tier($score),
                ],
            ]
        ));

        // 非同期でプロビジョニング開始（ここでは同期で実装）
        try {
            $this->provisioningService->provisionTrial($trial);

            return response()->json([
                'success' => true,
                'message' => 'トライアル環境の準備が開始されました。メールにてログイン情報をお送りします。',
                'trial_id' => $trial->id,
            ], 201);

        } catch (\Exception $e) {
            \Log::error("Failed to provision trial {$trial->id}", [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'トライアル環境の準備中にエラーが発生しました。しばらく経ってからお試しください。',
            ], 500);
        }
    }
}
