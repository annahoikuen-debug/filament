<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Facility extends Model
{
    use HasFactory, LogsActivity;

    public const SOFTWARE_FREEE = 'freee';

    public const SOFTWARE_MF = 'mf';

    protected $table = 'facilities';

    protected $fillable = [
        'name',
        'operator',
        'postal_code',
        'address',
        'phone',
        'fax',
        'email',
        'invoice_registration_number',
        'bank',
        'billing',
        'is_active',
        'notes',
        'seal_path',
        'freee_client_id',
        'freee_client_secret',
        'freee_access_token',
        'freee_refresh_token',
        'freee_token_expires_at',
        'freee_company_id',
        'mf_client_id',
        'mf_client_secret',
        'mf_access_token',
        'mf_refresh_token',
        'mf_token_expires_at',
        'mf_office_id',
        'accounting_api_settings',
    ];

    protected $casts = [
        'bank' => 'array',
        'billing' => 'array',
        'is_active' => 'boolean',
        'freee_client_secret' => 'encrypted',
        'freee_access_token' => 'encrypted',
        'freee_refresh_token' => 'encrypted',
        'freee_token_expires_at' => 'datetime',
        'freee_company_id' => 'integer',
        'mf_client_secret' => 'encrypted',
        'mf_access_token' => 'encrypted',
        'mf_refresh_token' => 'encrypted',
        'mf_token_expires_at' => 'datetime',
        'mf_office_id' => 'integer',
        'accounting_api_settings' => 'array',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([
                'name',
                'operator',
                'postal_code',
                'address',
                'phone',
                'fax',
                'email',
                'invoice_registration_number',
                'bank',
                'billing',
                'is_active',
                'notes',
                'seal_path',
            ])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->setDescriptionForEvent(fn (string $eventName) => "Facility {$eventName}")
            ->useLogName('facility');
    }

    /**
     * 入居者リレーション
     */
    public function residents(): HasMany
    {
        return $this->hasMany(Resident::class);
    }

    /**
     * 月次請求リレーション
     */
    public function monthlyInvoices(): HasMany
    {
        return $this->hasMany(MonthlyInvoice::class);
    }

    /**
     * 日々の自費利用明細リレーション
     */
    public function dailyCharges(): HasMany
    {
        return $this->hasMany(DailyCharge::class);
    }

    /**
     * 現在有効な施設情報を取得（施設ID指定時はその施設、未指定時は最初の有効施設）
     */
    public static function current(?int $facilityId = null): ?self
    {
        $query = self::where('is_active', true);

        if ($facilityId) {
            return $query->where('id', $facilityId)->first();
        }

        return $query->first();
    }

    /**
     * config/facility.php 互換の配列を返す
     */
    public function toConfigArray(): array
    {
        return [
            'name' => $this->name,
            'operator' => $this->operator,
            'postal_code' => $this->postal_code,
            'address' => $this->address,
            'phone' => $this->phone,
            'fax' => $this->fax,
            'email' => $this->email,
            'invoice_registration_number' => $this->invoice_registration_number,
            'bank' => $this->bank ?? [],
            'billing' => $this->billing ?? [],
            'seal_path' => $this->seal_path,
        ];
    }

    /**
     * 銀行口座情報のアクセサ（配列として扱う）
     */
    // protected function bank(): Attribute
    // {
    //     return Attribute::make(
    //         get: fn ($value) => $value ?? [
    //             'name' => '',
    //             'branch_name' => '',
    //             'account_type' => '普通',
    //             'account_number' => '',
    //             'account_holder' => '',
    //         ],
    //         set: fn ($value) => $value,
    //     );
    // }

    /**
     * 請求サイクル設定のアクセサ
     */
    // protected function billing(): Attribute
    // {
    //     return Attribute::make(
    //         get: fn ($value) => $value ?? [
    //             'direct_debit_day' => 27,
    //             'bank_transfer_due_days' => 30,
    //         ],
    //         set: fn ($value) => $value,
    //     );
    // }

    /**
     * 適格請求書登録番号のバリデーション（T + 13桁数字）
     */
    public static function validateInvoiceNumber(string $number): bool
    {
        return (bool) preg_match('/^T\d{13}$/', $number);
    }

    /**
     * freeeの認証情報が設定されているか
     */
    public function hasFreeeCredentials(): bool
    {
        return ! empty($this->freee_client_id) && ! empty($this->freee_access_token);
    }

    /**
     * MFクラウド会計の認証情報が設定されているか
     */
    public function hasMfCredentials(): bool
    {
        return ! empty($this->mf_client_id) && ! empty($this->mf_access_token);
    }

    /**
     * freeeのトークンが有効期限内か
     */
    public function isFreeeTokenValid(): bool
    {
        return $this->freee_token_expires_at !== null && $this->freee_token_expires_at->isFuture();
    }

    /**
     * MFクラウド会計のトークンが有効期限内か
     */
    public function isMfTokenValid(): bool
    {
        return $this->mf_token_expires_at !== null && $this->mf_token_expires_at->isFuture();
    }
}
