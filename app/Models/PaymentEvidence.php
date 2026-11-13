<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 入金消込時の証憑（受領書・明細書・振込明細等）を管理するモデル。
 *
 * config/billing.php の payment_evidence_required が true の場合、
 * 入金消込にこの証憑の添付を必須とする。
 */
class PaymentEvidence extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $casts = [
        'type' => 'string',
    ];

    public const TYPE_RECEIPT = 'receipt';        // 受領書
    public const TYPE_STATEMENT = 'statement';    // 明細書
    public const TYPE_TRANSFER_LOG = 'transfer_log'; // 振込明細

    public const TYPES = [
        self::TYPE_RECEIPT => '受領書',
        self::TYPE_STATEMENT => '明細書',
        self::TYPE_TRANSFER_LOG => '振込明細',
    ];

    public function monthlyInvoice(): BelongsTo
    {
        return $this->belongsTo(MonthlyInvoice::class, 'monthly_invoice_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function getTypeLabel(): string
    {
        return self::TYPES[$this->type] ?? $this->type;
    }
}
