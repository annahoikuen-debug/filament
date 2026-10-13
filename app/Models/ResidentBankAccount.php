<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class ResidentBankAccount extends Model
{
    use LogsActivity;

    public const ACCOUNT_TYPE_ORDINARY = 1; // 普通
    public const ACCOUNT_TYPE_CURRENT = 2;  // 当座

    protected $guarded = ['id'];

    protected $casts = [
        'account_type' => 'integer',
        'is_active' => 'boolean',
        // 機密情報は暗号化して保存
        'bank_code' => 'encrypted',
        'branch_code' => 'encrypted',
        'account_number' => 'encrypted',
        'account_holder_kana' => 'encrypted',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['resident_id', 'account_type', 'is_active', 'delegate_code'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->setDescriptionForEvent(fn (string $eventName) => "ResidentBankAccount {$eventName}")
            ->useLogName('resident_bank_account');
    }

    public function resident(): BelongsTo
    {
        return $this->belongsTo(Resident::class);
    }

    public function directDebitResults(): HasMany
    {
        return $this->hasMany(DirectDebitResult::class);
    }

    /**
     * 全銀フォーマット用の口座種別コード（1桁）
     */
    public function getZenginAccountTypeCode(): string
    {
        return (string) $this->account_type;
    }
}
