<?php

namespace App\Services\Banking;

use App\Models\MonthlyInvoice;
use App\Models\ResidentBankAccount;

/**
 * 全銀協固定長フォーマット（120バイト/行、CP932、CRLF）生成・解析エンジン
 *
 * Step 2: フォーマット生成（ヘッダー/データ/トレーラー/エンド）
 * Step 4: 結果ファイル（返却データ）のパーサー
 */
class ZenginFormatService
{
    public const LINE_LENGTH = 120;

    // レコード種別
    public const RECORD_HEADER = '1';
    public const RECORD_DATA = '2';
    public const RECORD_TRAILER = '8';
    public const RECORD_END = '9';

    public function __construct(
        protected ZenginResultParser $parser = new ZenginResultParser,
    ) {
    }

    /**
     * 口座振替依頼ファイル（総合振込依頼/口座振替依頼）を生成する
     *
     * @param  array  $header  委託者情報 ['delegate_code', 'delegate_name_kana', 'transfer_date' (Ymd), 'bank_code', 'bank_name_kana', 'branch_code', 'branch_name_kana', 'account_type', 'account_number']
     * @param  array<int, array{invoice: MonthlyInvoice, account: ResidentBankAccount}>  $entries
     */
    public function generate(array $header, array $entries): string
    {
        $lines = [];
        $totalAmount = 0;
        $count = 0;

        // ヘッダーレコード
        $lines[] = $this->buildHeaderRecord($header);

        // データレコード
        foreach ($entries as $entry) {
            $lines[] = $this->buildDataRecord($entry['invoice'], $entry['account']);
            $totalAmount += (int) $entry['invoice']->total_with_tax;
            $count++;
        }

        // トレーラーレコード
        $lines[] = $this->buildTrailerRecord($count, $totalAmount);

        // エンドレコード
        $lines[] = str_repeat('9', self::LINE_LENGTH);

        // padKana()でカナは既にCP932化済み。他フィールドは半角ASCIIのためここでは再変換しない（二重変換防止）
        return implode("\r\n", $lines)."\r\n";
    }

    protected function buildHeaderRecord(array $header): string
    {
        return $this->pad($this::RECORD_HEADER, 1, 'N')
            .$this->pad('91', 2, 'N') // 種別コード 91: 口座振替依頼
            .$this->pad('0', 1, 'N')  // 作成依頼コード
            .$this->pad($header['delegate_code'] ?? '', 10, 'AN')
            .$this->padKana($header['delegate_name_kana'] ?? '', 40)
            .$this->pad($header['transfer_date'] ?? '', 8, 'N') // 引落日 Ymd
            .$this->pad($header['bank_code'] ?? '', 4, 'N')     // 引落銀行
            .$this->padKana($header['bank_name_kana'] ?? '', 15)
            .$this->pad($header['branch_code'] ?? '', 3, 'N')   // 引落支店
            .$this->padKana($header['branch_name_kana'] ?? '', 15)
            .$this->pad($header['account_type'] ?? '1', 1, 'N')
            .$this->pad($header['account_number'] ?? '', 7, 'N')
            .$this->pad('', 13, 'AN'); // ダミー
    }

    protected function buildDataRecord(MonthlyInvoice $invoice, ResidentBankAccount $account): string
    {
        return $this->pad(self::RECORD_DATA, 1, 'N')
            .$this->pad($account->bank_code, 4, 'N')
            .$this->padKana('', 15) // 銀行名（略称任意）
            .$this->pad($account->branch_code, 3, 'N')
            .$this->padKana('', 15) // 支店名（略称任意）
            .$this->pad($account->getZenginAccountTypeCode(), 1, 'N')
            .$this->pad($account->account_number, 7, 'N')
            .$this->pad('', 1, 'N') // 預金者コードダミー
            .$this->padKana($account->account_holder_kana, 30)
            .$this->pad((int) $invoice->total_with_tax, 10, 'N')
            .$this->pad($invoice->resident_id, 10, 'N') // 顧客コードに入居者ID
            .$this->pad('', 23, 'AN'); // ダミー
    }

    protected function buildTrailerRecord(int $count, int $totalAmount): string
    {
        return $this->pad(self::RECORD_TRAILER, 1, 'N')
            .$this->pad($count, 6, 'N')
            .$this->pad($totalAmount, 12, 'N')
            .$this->pad('', 101, 'AN');
    }

    /**
     * 結果ファイル（返却全銀データ）を解析する（Step 4）
     *
     * @return array{success: array, failed: array, total_count: int, total_amount: int}
     */
    public function parseResult(string $content): array
    {
        return $this->parser->parse($content);
    }

    // ------------------------------------------------------------------
    // パディングユーティリティ
    // ------------------------------------------------------------------

    /**
     * 数値/半角文字: 右詰めゼロ埋め（N: 数値, AN: 半角英数）
     */
    protected function pad(int|string $value, int $length, string $type = 'N'): string
    {
        if ($type === 'N') {
            return str_pad((string) $value, $length, '0', STR_PAD_LEFT);
        }

        return str_pad((string) $value, $length, ' ', STR_PAD_RIGHT);
    }

    /**
     * カナ文字列: 左詰めスペース埋め（バイト長基準・CP932）
     */
    protected function padKana(string $value, int $byteLength): string
    {
        $encoded = mb_convert_encoding($value, 'CP932', 'UTF-8');
        $current = strlen($encoded);

        if ($current > $byteLength) {
            // バイト境界で切り詰める
            $encoded = substr($encoded, 0, $byteLength);
            // マルチバイト切断防止：末尾が不正な場合は1バイト削る
            while (strlen($encoded) > 0 && ! mb_check_encoding($encoded, 'CP932')) {
                $encoded = substr($encoded, 0, -1);
            }
        }

        return str_pad($encoded, $byteLength, ' ', STR_PAD_RIGHT);
    }
}
