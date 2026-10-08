<?php

namespace App\Http\Controllers;

use App\Models\FormSubmission;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class FormController extends Controller
{
    /**
     * コーポレートサイトの各種フォームを受け付ける
     *
     * 対応タイプ: catalog(資料請求) / demo(無料デモ) / diagnosis(診断書) /
     * prospect(見込み客) / inquiry(お問い合わせ・見積依頼)
     */
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
                $rules[$field] = ['required', 'string', 'max:' . ($field === 'message' ? '2000' : '255')];
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

        FormSubmission::create([
            'type' => $type,
            'company' => $validated['company'] ?? null,
            'name' => $validated['name'] ?? null,
            'email' => $validated['email'] ?? null,
            'phone' => $validated['phone'] ?? null,
            'payload' => collect($request->except(['company', 'name', 'email', 'phone']))->all(),
        ]);

        return response()->json([
            'success' => true,
            'message' => '受け付けました。担当者よりご連絡いたします。',
        ], 201);
    }
}
