<?php

namespace App\Http\Controllers;

use App\Models\FormSubmission;
use Symfony\Component\HttpFoundation\Response;

class FormDownloadController extends Controller
{
    /**
     * フォーム送信確認メール内の署名付きURLから資料をダウンロードさせる
     *
     * 対応ドキュメント: catalog（製品カタログ） / diagnosis-form（診断書フォーム）
     */
    public function show(FormSubmission $submission, string $document): Response
    {
        $allowed = [
            'catalog' => 'catalog.pdf',
            'diagnosis' => 'diagnosis-form.pdf',
        ];

        if (! isset($allowed[$document])) {
            abort(404);
        }

        // 資料ダウンロード対象のフォーム（catalog・diagnosis）以外からのアクセスを拒否
        $downloadableTypes = ['catalog', 'diagnosis'];
        if (! in_array($submission->type, $downloadableTypes, true)) {
            abort(403);
        }

        $path = storage_path('app/documents'.DIRECTORY_SEPARATOR.$allowed[$document]);

        if (! file_exists($path)) {
            abort(404);
        }

        return response()->download($path, $allowed[$document]);
    }
}
