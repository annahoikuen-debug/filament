<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Hash;

/**
 * 電子帳簿保存法対応：発行済み請求書・領収書の記録。
 *
 * 電子取引（メール送信したPDF等）の保存要件を満たすため、
 * 発行時にファイルハッシュ（SHA-256）を記録し、改ざん検知・検索を可能にする。
 */
class InvoiceDocument extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $casts = [
        'total_amount' => 'integer',
        'issued_at' => 'date',
    ];

    public const TYPE_INVOICE = 'invoice';
    public const TYPE_RECEIPT = 'receipt';

    public const TYPES = [
        self::TYPE_INVOICE => '請求書',
        self::TYPE_RECEIPT => '領収書',
    ];

    public function monthlyInvoice(): BelongsTo
    {
        return $this->belongsTo(MonthlyInvoice::class, 'monthly_invoice_id');
    }

    public function getTypeLabel(): string
    {
        return self::TYPES[$this->type] ?? $this->type;
    }

    /**
     * ファイルのSHA-256ハッシュを計算する。
     */
    public static function hashFile(string $filePath): string
    {
        return hash_file('sha256', $filePath);
    }

    /**
     * 保存したファイルのハッシュが一致するか検証する。
     */
    public function verifyHash(string $filePath): bool
    {
        return hash_equals($this->file_hash, self::hashFile($filePath));
    }
}
