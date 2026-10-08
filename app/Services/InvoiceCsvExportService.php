<?php

namespace App\Services;

use App\Enums\InvoiceStatus;
use App\Models\AccountingExportProfile;
use App\Models\ChartOfAccount;
use App\Models\MonthlyInvoice;
use App\Models\Facility;
use Carbon\Carbon;

class InvoiceCsvExportService
{
    /**
     * 指定年月の請求一覧CSVを生成する (Excel対応 UTF-8 BOM付き)
     *
     * @param  string  $yearMonth  'YYYY-MM'
     * @param  int|null  $facilityId  施設ID（指定時は該当施設のみ）
     * @return string CSV文字列
     */
    public function exportMonthlyListCsv(string $yearMonth, ?int $facilityId = null): string
    {
        $query = MonthlyInvoice::with('resident.facility')
            ->forYearMonth($yearMonth)
            ->join('residents', 'monthly_invoices.resident_id', '=', 'residents.id')
            ->orderBy('residents.room_number')
            ->select('monthly_invoices.*');

        if ($facilityId) {
            $query->where('monthly_invoices.facility_id', $facilityId);
        }

        $invoices = $query->get();

        $headers = [
            '請求年月',
            '部屋番号',
            '入居者氏名',
            'フリガナ',
            '基本家賃(非課税)',
            '基本管理費(課税)',
            '自費サービス小計(課税)',
            '課税対象額',
            '消費税率',
            '消費税額',
            '請求合計金額',
            '請求ステータス',
            '入金日',
            '入金方法',
            '領収書番号',
        ];

        $output = fopen('php://temp', 'r+');
        fwrite($output, "\xEF\xBB\xBF");
        fputcsv($output, $headers);

        foreach ($invoices as $inv) {
            fputcsv($output, [
                $inv->billing_year_month,
                $inv->resident->room_number,
                $inv->resident->name,
                $inv->resident->name_kana,
                $inv->rent_subtotal,
                $inv->management_fee_subtotal,
                $inv->service_subtotal,
                $inv->taxable_amount,
                rtrim(rtrim($inv->tax_rate, '0'), '.') . '%',
                $inv->tax_amount,
                $inv->total_amount,
                $inv->status?->getLabel() ?? $inv->status,
                $inv->paid_at ? Carbon::parse($inv->paid_at)->format('Y/m/d') : '',
                $inv->payment_method?->getLabel() ?? '',
                $inv->receipt_number ?? '',
            ]);
        }

        rewind($output);
        $csvContent = stream_get_contents($output);
        fclose($output);

        return $csvContent;
    }

    /**
     * 会計仕訳連携用CSVを生成する（プロファイル対応版）
     *
     * @param  string  $yearMonth  'YYYY-MM'
     * @param  int|null  $facilityId  施設ID（未指定時は全施設）
     * @param  string|null  $softwareType  会計ソフト種類 (freee, mf, yayoi, kanjobugyo, custom)
     * @param  int|null  $profileId  プロファイルID（指定時はそれを使用、未指定時はデフォルト）
     * @return string CSV文字列
     */
    public function exportAccountingJournalCsv(
        string $yearMonth,
        ?int $facilityId = null,
        ?string $softwareType = null,
        ?int $profileId = null
    ): string {
        // プロファイル取得
        $profile = $this->resolveProfile($facilityId, $softwareType, $profileId);

        // 請求データ取得
        $query = MonthlyInvoice::with('resident.facility')
            ->forYearMonth($yearMonth)
            ->whereIn('status', [InvoiceStatus::Billed, InvoiceStatus::Paid])
            ->orderBy('resident_id');

        if ($facilityId) {
            $query->where('facility_id', $facilityId);
        }

        $invoices = $query->get();

        // 勘定科目マスタ取得（施設ごと）
        $chartOfAccounts = $this->loadChartOfAccounts($invoices->pluck('facility_id')->unique()->toArray());

        // 仕訳データ生成
        $journalEntries = $this->buildJournalEntries($invoices, $chartOfAccounts, $profile);

        // CSV出力（データがなくてもヘッダーは出力）
        return $this->renderCsv($journalEntries, $profile);
    }

    /**
     * 仕訳プレビュー用データを生成（ダウンロード前の確認用）
     *
     * @return array<string, mixed> ['entries' => array, 'headers' => array, 'totals' => array, 'profile' => array]
     */
    public function previewAccountingJournal(
        string $yearMonth,
        ?int $facilityId = null,
        ?string $softwareType = null,
        ?int $profileId = null
    ): array {
        $profile = $this->resolveProfile($facilityId, $softwareType, $profileId);

        $query = MonthlyInvoice::with('resident.facility')
            ->forYearMonth($yearMonth)
            ->whereIn('status', [InvoiceStatus::Billed, InvoiceStatus::Paid])
            ->orderBy('resident_id');

        if ($facilityId) {
            $query->where('facility_id', $facilityId);
        }

        $invoices = $query->get();

        if ($invoices->isEmpty()) {
            return [
                'entries' => [],
                'headers' => $profile->buildHeader(),
                'totals' => ['debit' => 0, 'credit' => 0, 'count' => 0],
                'profile' => $this->profileToArray($profile),
            ];
        }

        $chartOfAccounts = $this->loadChartOfAccounts($invoices->pluck('facility_id')->unique()->toArray());
        $journalEntries = $this->buildJournalEntries($invoices, $chartOfAccounts, $profile);

        $totals = [
            'debit' => array_sum(array_column($journalEntries, 'amount')),
            'credit' => array_sum(array_column($journalEntries, 'amount')),
            'count' => count($journalEntries),
        ];

        return [
            'entries' => $journalEntries,
            'headers' => $profile->buildHeader(),
            'totals' => $totals,
            'profile' => $this->profileToArray($profile),
        ];
    }

    /**
     * 利用可能なプロファイル一覧を取得
     */
    public function getAvailableProfiles(int $facilityId): array
    {
        $profiles = AccountingExportProfile::getActiveForFacility($facilityId);
        return $profiles->map(fn ($p) => $this->profileToArray($p))->toArray();
    }

    /**
     * プロファイル解決
     */
    private function resolveProfile(?int $facilityId, ?string $softwareType, ?int $profileId): AccountingExportProfile
    {
        if ($profileId) {
            $profile = AccountingExportProfile::findOrFail($profileId);
            if ($facilityId && $profile->facility_id !== $facilityId) {
                throw new \InvalidArgumentException('指定されたプロファイルは選択施設のものではありません。');
            }
            return $profile;
        }

        $softwareType = $softwareType ?? AccountingExportProfile::SOFTWARE_FREEE;

        if ($facilityId) {
            $profile = AccountingExportProfile::getDefault($facilityId, $softwareType);
            if ($profile) {
                return $profile;
            }
        }

        // フォールバック: デフォルトプロファイルを動的生成
        return $this->createFallbackProfile($softwareType);
    }

    /**
     * フォールバックプロファイル生成
     */
    private function createFallbackProfile(string $softwareType): AccountingExportProfile
    {
        return new AccountingExportProfile([
            'software_type' => $softwareType,
            'header_mapping' => $this->getDefaultHeaderMapping($softwareType),
            'tax_code_mapping' => $this->getDefaultTaxCodeMapping($softwareType),
            'date_format' => 'Y/m/d',
            'encoding' => 'UTF-8',
            'include_header' => true,
            'bom' => true,
            'line_ending' => 'CRLF',
        ]);
    }

    /**
     * 勘定科目マスタ一括読み込み
     */
    private function loadChartOfAccounts(array $facilityIds): array
    {
        $accounts = ChartOfAccount::whereIn('facility_id', $facilityIds)
            ->where('is_active', true)
            ->get()
            ->groupBy('facility_id')
            ->map(fn ($group) => $group->groupBy(fn ($item) => "{$item->item_type}.{$item->account_side}"))
            ->toArray();

        return $accounts;
    }

    /**
     * 仕訳エントリ構築
     *
     * @return array<int, array<string, mixed>>
     */
    private function buildJournalEntries(
        \Illuminate\Database\Eloquent\Collection $invoices,
        array $chartOfAccounts,
        AccountingExportProfile $profile
    ): array {
        if ($invoices->isEmpty()) {
            return [];
        }

        $entries = [];
        $endOfMonth = Carbon::createFromFormat('Y-m', $invoices->first()->billing_year_month)->endOfMonth();
        $formattedDate = $profile->formatDate($endOfMonth);

        // 品目タイプ定義
        $itemTypes = [
            'rent' => [
                'label' => '家賃分',
                'amount_field' => 'rent_subtotal',
                'tax_code' => 'tax_exempt',
            ],
            'management_fee' => [
                'label' => '管理費分',
                'amount_field' => 'management_fee_subtotal',
                'tax_code' => 'taxable_10',
            ],
            'service' => [
                'label' => '自費立替分',
                'amount_field' => 'service_subtotal',
                'tax_code' => 'taxable_10',
            ],
        ];

        foreach ($invoices as $inv) {
            $facilityId = $inv->facility_id;
            $facilityAccounts = $chartOfAccounts[$facilityId] ?? [];
            $summaryBase = "{$inv->billing_year_month}請求 [{$inv->resident->room_number}] {$inv->resident->name}";

            foreach ($itemTypes as $itemType => $config) {
                $amount = $inv->{$config['amount_field']};
                if ($amount <= 0) {
                    continue;
                }

                $debitAccount = $facilityAccounts["{$itemType}.debit"][0] ?? null;
                $creditAccount = $facilityAccounts["{$itemType}.credit"][0] ?? null;

                // 勘定科目が未設定の場合はデフォルト値を使用
                $debitAccount = $debitAccount ?? [
                    'account_code' => '1100',
                    'account_name' => '売掛金',
                    'sub_account_code' => null,
                    'sub_account_name' => null,
                    'tax_code' => $config['tax_code'],
                    'department_code' => null,
                    'department_name' => null,
                    'tag_codes' => [],
                ];

                $creditAccount = $creditAccount ?? [
                    'account_code' => match ($itemType) {
                        'rent' => '4110',
                        'management_fee' => '4120',
                        'service' => '4130',
                        default => '4190',
                    },
                    'account_name' => match ($itemType) {
                        'rent' => '賃貸料収入',
                        'management_fee' => '施設管理費収入',
                        'service' => '自費・立替金収入',
                        default => '雑収入',
                    },
                    'sub_account_code' => null,
                    'sub_account_name' => null,
                    'tax_code' => $config['tax_code'],
                    'department_code' => null,
                    'department_name' => null,
                    'tag_codes' => [],
                ];

                $mappedTaxCode = $profile->mapTaxCode($creditAccount['tax_code'] ?? $config['tax_code']);

                $entries[] = [
                    'date' => $formattedDate,
                    'debit_account_code' => $debitAccount['account_code'],
                    'debit_account_name' => $debitAccount['account_name'],
                    'debit_sub_account_code' => $debitAccount['sub_account_code'] ?? '',
                    'debit_sub_account_name' => $debitAccount['sub_account_name'] ?? '',
                    'debit_department_code' => $debitAccount['department_code'] ?? '',
                    'debit_department_name' => $debitAccount['department_name'] ?? '',
                    'debit_tag_codes' => implode(',', $debitAccount['tag_codes'] ?? []),
                    'credit_account_code' => $creditAccount['account_code'],
                    'credit_account_name' => $creditAccount['account_name'],
                    'credit_sub_account_code' => $creditAccount['sub_account_code'] ?? '',
                    'credit_sub_account_name' => $creditAccount['sub_account_name'] ?? '',
                    'credit_department_code' => $creditAccount['department_code'] ?? '',
                    'credit_department_name' => $creditAccount['department_name'] ?? '',
                    'credit_tag_codes' => implode(',', $creditAccount['tag_codes'] ?? []),
                    'amount' => $amount,
                    'tax_code' => $mappedTaxCode,
                    'description' => "{$summaryBase} {$config['label']}",
                ];
            }
        }

        return $entries;
    }

    /**
     * CSVレンダリング
     */
    private function renderCsv(array $entries, AccountingExportProfile $profile): string
    {
        $output = fopen('php://temp', 'r+');

        // BOM
        if ($profile->bom && ($profile->encoding === 'UTF-8' || $profile->encoding === 'UTF-8-BOM')) {
            fwrite($output, "\xEF\xBB\xBF");
        }

        // ヘッダー
        if ($profile->include_header) {
            $headers = $profile->buildHeader();
            // fputcsvはUTF-8前提なので、SJISの場合は手動で書き込み
            if ($profile->encoding === 'SJIS' || $profile->encoding === 'CP932') {
                $line = implode(',', array_map(fn ($h) => $this->escapeCsvField($h), $headers));
                fwrite($output, $line . $profile->getLineEnding());
            } else {
                fputcsv($output, $headers);
            }
        }

        // データ行
        $headerMapping = $profile->header_mapping ?? [];
        $fieldOrder = array_keys($headerMapping);

        foreach ($entries as $entry) {
            $row = [];
            foreach ($fieldOrder as $field) {
                $row[] = $entry[$field] ?? '';
            }

            if ($profile->encoding === 'SJIS' || $profile->encoding === 'CP932') {
                $line = implode(',', array_map(fn ($v) => $this->escapeCsvField($v), $row));
                fwrite($output, $line . $profile->getLineEnding());
            } else {
                fputcsv($output, $row);
            }
        }

        rewind($output);
        $csvContent = stream_get_contents($output);
        fclose($output);

        // エンコーディング変換
        return $profile->convertEncoding($csvContent);
    }

    /**
     * CSVフィールドエスケープ
     */
    private function escapeCsvField(mixed $value): string
    {
        $str = (string) $value;
        if (str_contains($str, ',') || str_contains($str, '"') || str_contains($str, "\n") || str_contains($str, "\r")) {
            return '"' . str_replace('"', '""', $str) . '"';
        }
        return $str;
    }

    /**
     * プロファイルを配列に変換（プレビュー用）
     */
    private function profileToArray(AccountingExportProfile $profile): array
    {
        return [
            'id' => $profile->id,
            'name' => $profile->name,
            'software_type' => $profile->software_type,
            'encoding' => $profile->encoding,
            'date_format' => $profile->date_format,
            'include_header' => $profile->include_header,
            'bom' => $profile->bom,
            'line_ending' => $profile->line_ending,
        ];
    }

    /**
     * デフォルトヘッダーマッピング
     */
    private function getDefaultHeaderMapping(string $softwareType): array
    {
        return match ($softwareType) {
            AccountingExportProfile::SOFTWARE_FREEE => [
                'date' => '取引日',
                'debit_account_code' => '借方科目コード',
                'debit_account_name' => '借方科目名',
                'debit_sub_account_code' => '借方補助科目コード',
                'debit_sub_account_name' => '借方補助科目名',
                'debit_department_code' => '借方部門コード',
                'debit_department_name' => '借方部門名',
                'debit_tag_codes' => '借方タグコード',
                'credit_account_code' => '貸方科目コード',
                'credit_account_name' => '貸方科目名',
                'credit_sub_account_code' => '貸方補助科目コード',
                'credit_sub_account_name' => '貸方補助科目名',
                'credit_department_code' => '貸方部門コード',
                'credit_department_name' => '貸方部門名',
                'credit_tag_codes' => '貸方タグコード',
                'amount' => '金額',
                'tax_code' => '税区分コード',
                'description' => '摘要',
            ],
            AccountingExportProfile::SOFTWARE_MF => [
                'date' => '日付',
                'debit_account_code' => '借方勘定科目コード',
                'debit_account_name' => '借方勘定科目名',
                'debit_sub_account_code' => '借方補助科目コード',
                'debit_sub_account_name' => '借方補助科目名',
                'debit_department_code' => '借方部門コード',
                'debit_department_name' => '借方部門名',
                'credit_account_code' => '貸方勘定科目コード',
                'credit_account_name' => '貸方勘定科目名',
                'credit_sub_account_code' => '貸方補助科目コード',
                'credit_sub_account_name' => '貸方補助科目名',
                'credit_department_code' => '貸方部門コード',
                'credit_department_name' => '貸方部門名',
                'amount' => '金額',
                'tax_code' => '税区分',
                'description' => '摘要',
            ],
            AccountingExportProfile::SOFTWARE_YAYOI => [
                'date' => '取引日',
                'debit_account_code' => '借方科目コード',
                'debit_account_name' => '借方科目名',
                'debit_sub_account_code' => '借方補助科目コード',
                'debit_sub_account_name' => '借方補助科目名',
                'debit_department_code' => '借方部門コード',
                'debit_department_name' => '借方部門名',
                'credit_account_code' => '貸方科目コード',
                'credit_account_name' => '貸方科目名',
                'credit_sub_account_code' => '貸方補助科目コード',
                'credit_sub_account_name' => '貸方補助科目名',
                'credit_department_code' => '貸方部門コード',
                'credit_department_name' => '貸方部門名',
                'amount' => '金額',
                'tax_code' => '税区分',
                'description' => '摘要',
            ],
            AccountingExportProfile::SOFTWARE_KANJOBUGYO => [
                'date' => '伝票日付',
                'debit_account_code' => '借方科目コード',
                'debit_sub_account_code' => '借方補助科目コード',
                'debit_department_code' => '借方部門コード',
                'credit_account_code' => '貸方科目コード',
                'credit_sub_account_code' => '貸方補助科目コード',
                'credit_department_code' => '貸方部門コード',
                'amount' => '金額',
                'tax_code' => '税区分',
                'description' => '摘要',
            ],
            default => [
                'date' => '取引日',
                'debit_account_code' => '借方科目コード',
                'debit_account_name' => '借方科目名',
                'credit_account_code' => '貸方科目コード',
                'credit_account_name' => '貸方科目名',
                'amount' => '金額',
                'tax_code' => '税区分',
                'description' => '摘要',
            ],
        };
    }

    /**
     * デフォルト税区分マッピング
     */
    private function getDefaultTaxCodeMapping(string $softwareType): array
    {
        return match ($softwareType) {
            AccountingExportProfile::SOFTWARE_FREEE => [
                'tax_exempt' => '0',
                'taxable_10' => '1',
                'taxable_8' => '2',
            ],
            AccountingExportProfile::SOFTWARE_MF => [
                'tax_exempt' => '対象外',
                'taxable_10' => '課税10%',
                'taxable_8' => '課税8%',
            ],
            AccountingExportProfile::SOFTWARE_YAYOI => [
                'tax_exempt' => '対象外',
                'taxable_10' => '課税仕入10%',
                'taxable_8' => '課税仕入8%',
            ],
            AccountingExportProfile::SOFTWARE_KANJOBUGYO => [
                'tax_exempt' => '0',
                'taxable_10' => '1',
                'taxable_8' => '2',
            ],
            default => [
                'tax_exempt' => '対象外',
                'taxable_10' => '課税10%',
                'taxable_8' => '課税8%',
            ],
        };
    }
}