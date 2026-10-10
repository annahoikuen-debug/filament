<?php

namespace App\Services\Pdf;

use App\Models\MonthlyInvoice;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use setasign\Fpdi\Tcpdf\Fpdi;

/**
 * PDFパスワード保護サービス
 *
 * 生成済みPDFバイナリにパスワード保護（暗号化）を付与する。
 * TCPDF + FPDI を利用し、全ページをインポートして SetProtection で暗号化する。
 *
 * config/pdf.php の password セクションで動作モードを設定：
 *  - mode = 'fixed': 全PDF共通の固定パスワード（password.fixed_password）
 *  - mode = 'resident_birthday': 入居者の生年月日8桁（YYYYMMDD）
 */
class PdfPasswordProtector
{
    /**
     * PDFバイナリにパスワード保護を付与する
     *
     * @param  string  $pdfBinary  元のPDFバイナリ
     * @param  string  $userPassword  閲覧用パスワード
     * @param  ?string  $ownerPassword  編集制限用オーナーパスワード（nullならランダム生成）
     * @return string 保護されたPDFバイナリ
     *
     * @throws \RuntimeException 変換に失敗した場合
     */
    public function protect(string $pdfBinary, string $userPassword, ?string $ownerPassword = null): string
    {
        if ($pdfBinary === '') {
            throw new \RuntimeException('PDF binary is empty.');
        }

        $ownerPassword ??= bin2hex(random_bytes(16));

        $tempIn = tempnam(sys_get_temp_dir(), 'pdf_protect_in_');
        if ($tempIn === false) {
            throw new \RuntimeException('Failed to create temporary file.');
        }

        try {
            file_put_contents($tempIn, $pdfBinary);

            $pdf = new Fpdi('P', 'mm', 'A4', true, 'UTF-8', false);

            $pageCount = $pdf->setSourceFile($tempIn);
            for ($pageNo = 1; $pageNo <= $pageCount; $pageNo++) {
                $templateId = $pdf->importPage($pageNo);
                $size = $pdf->getTemplateSize($templateId);

                $pdf->AddPage($size['orientation'], [$size['width'], $size['height']]);
                $pdf->useTemplate($templateId);
            }

            // 権限: 閲覧（print）のみ許可、コピー・編集・変更は禁止
            $pdf->SetProtection(['print'], $userPassword, $ownerPassword, 0);

            return $pdf->Output('protected.pdf', 'S');
        } catch (\Throwable $e) {
            Log::error('PDF password protection failed', [
                'error' => $e->getMessage(),
            ]);
            throw new \RuntimeException('PDF password protection failed: '.$e->getMessage(), 0, $e);
        } finally {
            @unlink($tempIn);
        }
    }

    /**
     * 設定に基づき請求書PDF用のパスワードを解決する
     *
     * @return ?string パスワード（保護無効時はnull）
     */
    public function resolvePasswordForInvoice(MonthlyInvoice $invoice): ?string
    {
        $config = config('pdf.password');

        if (! is_array($config) || ! ($config['enabled'] ?? false)) {
            return null;
        }

        return match ($config['mode'] ?? 'fixed') {
            'resident_birthday' => $this->birthdayPassword($invoice),
            'fixed' => $this->fixedPassword(),
            default => $this->fixedPassword(),
        };
    }

    /**
     * 請求書PDFに設定済みパスワードで保護を適用する
     *
     * @return string 保護されたPDFバイナリ（保護無効時は元のバイナリ）
     */
    public function protectInvoice(string $pdfBinary, MonthlyInvoice $invoice): string
    {
        $password = $this->resolvePasswordForInvoice($invoice);

        if ($password === null || $password === '') {
            return $pdfBinary;
        }

        return $this->protect($pdfBinary, $password);
    }

    private function fixedPassword(): ?string
    {
        $password = config('pdf.password.fixed_password');

        return is_string($password) && $password !== '' ? $password : null;
    }

    private function birthdayPassword(MonthlyInvoice $invoice): ?string
    {
        $birthday = $invoice->resident?->birth_date;

        if ($birthday === null) {
            Log::warning('Password mode is resident_birthday but resident has no birth_date', [
                'invoice_id' => $invoice->id,
            ]);

            return $this->fixedPassword();
        }

        return Carbon::parse($birthday)->format('Ymd');
    }
}
