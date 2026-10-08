<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccountingExportProfile extends Model
{
    use HasFactory;

    protected $table = 'accounting_export_profiles';

    protected $fillable = [
        'facility_id',
        'name',
        'software_type',
        'header_mapping',
        'field_mapping',
        'tax_code_mapping',
        'department_mapping',
        'tag_mapping',
        'sub_account_mapping',
        'date_format',
        'encoding',
        'include_header',
        'bom',
        'line_ending',
        'default_values',
        'is_default',
        'is_active',
        'notes',
    ];

    protected $casts = [
        'header_mapping' => 'array',
        'field_mapping' => 'array',
        'tax_code_mapping' => 'array',
        'department_mapping' => 'array',
        'tag_mapping' => 'array',
        'sub_account_mapping' => 'array',
        'default_values' => 'array',
        'include_header' => 'boolean',
        'bom' => 'boolean',
        'is_default' => 'boolean',
        'is_active' => 'boolean',
    ];

    /**
     * 会計ソフト種類
     */
    public const SOFTWARE_FREEE = 'freee';
    public const SOFTWARE_MF = 'mf';
    public const SOFTWARE_YAYOI = 'yayoi';
    public const SOFTWARE_KANJOBUGYO = 'kanjobugyo';
    public const SOFTWARE_CUSTOM = 'custom';

    /**
     * 施設リレーション
     */
    public function facility(): BelongsTo
    {
        return $this->belongsTo(Facility::class);
    }

    /**
     * デフォルトプロファイルを取得
     */
    public static function getDefault(int $facilityId, string $softwareType): ?self
    {
        return self::where('facility_id', $facilityId)
            ->where('software_type', $softwareType)
            ->where('is_default', true)
            ->where('is_active', true)
            ->first();
    }

    /**
     * 施設の有効なプロファイル一覧を取得
     */
    public static function getActiveForFacility(int $facilityId): \Illuminate\Database\Eloquent\Collection
    {
        return self::where('facility_id', $facilityId)
            ->where('is_active', true)
            ->orderBy('software_type')
            ->orderBy('name')
            ->get();
    }

    /**
     * ソフトウェア別のデフォルトプロファイルを作成
     */
    public static function createDefaultsForFacility(int $facilityId): void
    {
        $defaults = [
            // freee
            [
                'name' => 'freee標準',
                'software_type' => self::SOFTWARE_FREEE,
                'header_mapping' => [
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
                'field_mapping' => [
                    'required' => ['date', 'debit_account_code', 'credit_account_code', 'amount', 'tax_code'],
                    'optional' => ['debit_sub_account_code', 'debit_department_code', 'debit_tag_codes', 'credit_sub_account_code', 'credit_department_code', 'credit_tag_codes', 'description'],
                ],
                'tax_code_mapping' => [
                    'tax_exempt' => '0',
                    'taxable_10' => '1',
                    'taxable_8' => '2',
                ],
                'date_format' => 'Y/m/d',
                'encoding' => 'UTF-8',
                'include_header' => true,
                'bom' => true,
                'line_ending' => 'CRLF',
                'is_default' => true,
            ],
            // MFクラウド会計
            [
                'name' => 'MFクラウド会計標準',
                'software_type' => self::SOFTWARE_MF,
                'header_mapping' => [
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
                    'tax_category' => '税区分',
                    'description' => '摘要',
                ],
                'field_mapping' => [
                    'required' => ['date', 'debit_account_code', 'credit_account_code', 'amount', 'tax_category'],
                    'optional' => ['debit_sub_account_code', 'debit_department_code', 'credit_sub_account_code', 'credit_department_code', 'description'],
                ],
                'tax_code_mapping' => [
                    'tax_exempt' => '対象外',
                    'taxable_10' => '課税10%',
                    'taxable_8' => '課税8%',
                ],
                'date_format' => 'Y/m/d',
                'encoding' => 'UTF-8',
                'include_header' => true,
                'bom' => true,
                'line_ending' => 'CRLF',
                'is_default' => true,
            ],
            // 弥生会計
            [
                'name' => '弥生会計標準',
                'software_type' => self::SOFTWARE_YAYOI,
                'header_mapping' => [
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
                    'tax_classification' => '税区分',
                    'description' => '摘要',
                ],
                'field_mapping' => [
                    'required' => ['date', 'debit_account_code', 'credit_account_code', 'amount', 'tax_classification'],
                    'optional' => ['debit_sub_account_code', 'debit_department_code', 'credit_sub_account_code', 'credit_department_code', 'description'],
                ],
                'tax_code_mapping' => [
                    'tax_exempt' => '対象外',
                    'taxable_10' => '課税仕入10%',
                    'taxable_8' => '課税仕入8%',
                ],
                'date_format' => 'Y/m/d',
                'encoding' => 'SJIS',
                'include_header' => true,
                'bom' => false,
                'line_ending' => 'CRLF',
                'is_default' => true,
            ],
            // 勘定奉行
            [
                'name' => '勘定奉行標準',
                'software_type' => self::SOFTWARE_KANJOBUGYO,
                'header_mapping' => [
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
                'field_mapping' => [
                    'required' => ['date', 'debit_account_code', 'credit_account_code', 'amount', 'tax_code'],
                    'optional' => ['debit_sub_account_code', 'debit_department_code', 'credit_sub_account_code', 'credit_department_code', 'description'],
                ],
                'tax_code_mapping' => [
                    'tax_exempt' => '0',
                    'taxable_10' => '1',
                    'taxable_8' => '2',
                ],
                'date_format' => 'Y/m/d',
                'encoding' => 'SJIS',
                'include_header' => true,
                'bom' => false,
                'line_ending' => 'CRLF',
                'is_default' => true,
            ],
        ];

        foreach ($defaults as $default) {
            self::updateOrCreate(
                [
                    'facility_id' => $facilityId,
                    'software_type' => $default['software_type'],
                    'name' => $default['name'],
                ],
                array_merge($default, ['facility_id' => $facilityId, 'is_active' => true])
            );
        }
    }

    /**
     * ヘッダー行を生成
     */
    public function buildHeader(): array
    {
        $mapping = $this->header_mapping ?? [];
        return array_values($mapping);
    }

    /**
     * フィールドマッピングを取得
     */
    public function getFieldMapping(): array
    {
        return $this->field_mapping ?? [];
    }

    /**
     * 税区分コードを変換
     */
    public function mapTaxCode(string $standardTaxCode): string
    {
        $mapping = $this->tax_code_mapping ?? [];
        return $mapping[$standardTaxCode] ?? $standardTaxCode;
    }

    /**
     * 日付フォーマット
     */
    public function formatDate(\DateTimeInterface $date): string
    {
        return $date->format($this->date_format ?? 'Y/m/d');
    }

    /**
     * 改行コードを取得
     */
    public function getLineEnding(): string
    {
        return match ($this->line_ending) {
            'LF' => "\n",
            'CRLF' => "\r\n",
            default => "\r\n",
        };
    }

    /**
     * エンコーディング変換
     */
    public function convertEncoding(string $csv): string
    {
        $encoding = $this->encoding ?? 'UTF-8';
        if ($encoding === 'SJIS' || $encoding === 'CP932') {
            return mb_convert_encoding($csv, 'SJIS-win', 'UTF-8');
        }
        return $csv;
    }
}