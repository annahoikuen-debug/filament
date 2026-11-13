<?php

namespace App\Console\Commands;

use App\Models\InvoiceDocument;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class VerifyInvoiceDocuments extends Command
{
    protected $signature = 'invoice-documents:verify
                            {--fix : 不一致レコードをログに出力（修正は行わない）}';

    protected $description = '電子帳簿保存法：帳票ファイルのハッシュ改ざん検証を実行する';

    public function handle(): int
    {
        $fix = (bool) $this->option('fix');

        $this->info('=== 電子帳簿保存法：帳票改ざん検証 ===');

        $documents = InvoiceDocument::all();
        $total = $documents->count();
        $verified = 0;
        $mismatched = 0;
        $missing = 0;

        if ($total === 0) {
            $this->info('検証対象の帳票記録がありません。');

            return self::SUCCESS;
        }

        $progressBar = $this->output->createProgressBar($total);
        $progressBar->start();

        foreach ($documents as $doc) {
            $filePath = storage_path('app/'.$doc->file_path);

            if (! file_exists($filePath)) {
                $missing++;
                Log::warning('Invoice document file missing', [
                    'document_id' => $doc->id,
                    'file_path' => $doc->file_path,
                ]);
                $progressBar->advance();
                continue;
            }

            if ($doc->verifyHash($filePath)) {
                $verified++;
            } else {
                $mismatched++;
                Log::warning('Invoice document hash mismatch (possible tampering)', [
                    'document_id' => $doc->id,
                    'file_path' => $doc->file_path,
                    'expected_hash' => $doc->file_hash,
                    'actual_hash' => \App\Models\InvoiceDocument::hashFile($filePath),
                ]);
            }

            $progressBar->advance();
        }

        $progressBar->finish();
        $this->newLine();

        $this->table(
            ['項目', '件数'],
            [
                ['合計', $total],
                ['ハッシュ一致', $verified],
                ['ハッシュ不一致', $mismatched],
                ['ファイル未検出', $missing],
            ]
        );

        if ($mismatched > 0 || $missing > 0) {
            $this->error('改ざんまたはファイル紛失が検出されました。至急調査してください。');

            return self::FAILURE;
        }

        $this->info('すべての帳票ファイルのハッシュが一致しました。');

        return self::SUCCESS;
    }
}
