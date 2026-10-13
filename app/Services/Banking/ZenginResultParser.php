<?php

namespace App\Services\Banking;

/**
 * 全銀協結果ファイル（返却データ）パーサー
 * 振替結果コード: 0=振替済, 1=資金不足, 2=取引なし, 3=預金者都合, 8=委託者都合 など
 */
class ZenginResultParser
{
    public const RESULT_CODE_OK = '0';

    /** @var array<string, string> 不能コード => 理由ラベル */
    public const FAILURE_REASONS = [
        '1' => '資金不足',
        '2' => '取引なし',
        '3' => '預金者都合',
        '4' => '口座相違',
        '8' => '委託者都合',
        '9' => 'その他',
    ];

    /**
     * @return array{success: array<int, array{resident_id: int, amount: int}>, failed: array<int, array{resident_id: int, amount: int, code: string, reason: string}>, total_count: int, total_amount: int}
     */
    public function parse(string $content): array
    {
        // CP932 → UTF-8 変換、CRLF正規化
        $utf8 = mb_convert_encoding($content, 'UTF-8', 'CP932, SJIS-win, SJIS');
        $lines = preg_split('/\r\n|\r|\n/', $utf8) ?: [];
        $lines = array_filter($lines, fn ($l) => $l !== '');

        $success = [];
        $failed = [];
        $totalCount = 0;
        $totalAmount = 0;

        foreach ($lines as $line) {
            $type = substr($line, 0, 1);

            if ($type === '2') {
                $totalCount++;
                $amount = (int) ltrim(substr($line, 44, 10), '0') ?: (int) substr($line, 44, 10);
                $residentId = (int) substr($line, 54, 10);
                $resultCode = trim(substr($line, 64, 1)); // 結果コード

                $totalAmount += $amount;

                if ($resultCode === self::RESULT_CODE_OK) {
                    $success[] = ['resident_id' => $residentId, 'amount' => $amount];
                } else {
                    $failed[] = [
                        'resident_id' => $residentId,
                        'amount' => $amount,
                        'code' => $resultCode,
                        'reason' => self::FAILURE_REASONS[$resultCode] ?? '不明なエラーコード',
                    ];
                }
            } elseif ($type === '8') {
                // トレーラー: 件数・金額をオーバーライド（存在する場合）
                $trailerCount = (int) substr($line, 1, 6);
                $trailerAmount = (int) substr($line, 7, 12);
                if ($trailerCount > 0) {
                    $totalCount = $trailerCount;
                    $totalAmount = $trailerAmount;
                }
            }
        }

        return [
            'success' => $success,
            'failed' => $failed,
            'total_count' => $totalCount,
            'total_amount' => $totalAmount,
        ];
    }
}
