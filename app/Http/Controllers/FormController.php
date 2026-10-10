<?php

namespace App\Http\Controllers;

use App\Mail\FormConfirmationMail;
use App\Models\FormSubmission;
use App\Services\MailService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Validator;

class FormController extends Controller
{
    /**
     * コーポレートサイトの各種フォームを受け付ける
     *
     * 対応タイプ: catalog(資料請求) / demo(無料デモ) / diagnosis(診断書) /
     * prospect(見込み客) / inquiry(お問い合わせ・見積依頼)
     *
     * スパム対策: ハニーポット（websiteフィールドが空であること）+
     * 最短送信時間チェック（フォーム表示から2秒以内の送信はボットと判定）
     */
    public function __construct(
        private readonly MailService $mailService,
    ) {}

    public function store(Request $request, string $type)
    {
        $requiredFields = [
            'catalog' => ['company', 'name', 'email'],
            'demo' => ['company', 'name', 'email'],
            'diagnosis' => ['name', 'email'],
            'prospect' => ['name', 'email'],
            'inquiry' => ['company', 'name', 'email', 'message'],
        ];

        if (! array_key_exists($type, $requiredFields)) {
            return response()->json([
                'success' => false,
                'message' => '不正なフォームタイプです。',
            ], 404);
        }

        // ハニーポット: 隠しフィールドが埋まっていればボットと判定（成功を返して黙って破棄）
        if ($request->filled('website')) {
            Log::info('[FormController] Honeypot caught spam submission', [
                'type' => $type,
                'ip' => $request->ip(),
            ]);

            return response()->json([
                'success' => true,
                'message' => '受け付けました。',
            ], 201);
        }

        // 最短送信時間チェック: フォーム表示から2秒以内の送信はボットと判定
        $loadedAt = (int) $request->input('form_loaded_at', 0);
        if ($loadedAt > 0 && (now()->getTimestamp() - $loadedAt) < 2) {
            Log::info('[FormController] Submission too fast, rejected as bot', [
                'type' => $type,
                'ip' => $request->ip(),
                'elapsed' => now()->getTimestamp() - $loadedAt,
            ]);

            return response()->json([
                'success' => true,
                'message' => '受け付けました。',
            ], 201);
        }

        $rules = [
            'company' => ['nullable', 'string', 'max:255'],
            'name' => ['nullable', 'string', 'max:100'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:20'],
        ];

        foreach ($requiredFields[$type] as $field) {
            if ($field === 'email') {
                $rules['email'] = ['required', 'email', 'max:255'];
            } else {
                $rules[$field] = ['required', 'string', 'max:'.($field === 'message' ? '2000' : '255')];
            }
        }

        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $validated = $validator->validated();

        $submission = FormSubmission::create([
            'type' => $type,
            'company' => $validated['company'] ?? null,
            'name' => $validated['name'] ?? null,
            'email' => $validated['email'] ?? null,
            'phone' => $validated['phone'] ?? null,
            'payload' => collect($request->except(['company', 'name', 'email', 'phone', 'website', 'form_loaded_at']))->all(),
        ]);

        // 資料ダウンロード対象（catalog・diagnosis）は署名付きURLを確認メールに添付
        $downloadableTypes = ['catalog', 'diagnosis'];
        $downloadUrl = null;
        if (in_array($type, $downloadableTypes, true)) {
            $document = $type === 'catalog' ? 'catalog' : 'diagnosis';
            $downloadUrl = URL::temporarySignedRoute(
                'forms.download',
                now()->addDays(7),
                ['submission' => $submission->id, 'document' => $document],
            );
        }

        // 確認メール送信（メール未設定・失敗時は受付自体は成功として継続）
        if ($validated['email'] ?? null) {
            $this->mailService->send(
                new FormConfirmationMail($submission, $downloadUrl),
                $validated['email'],
            );
        }

        return response()->json([
            'success' => true,
            'message' => '受け付けました。担当者よりご連絡いたします。',
        ], 201);
    }
}
