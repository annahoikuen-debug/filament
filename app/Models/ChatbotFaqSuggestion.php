<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChatbotFaqSuggestion extends Model
{
    protected $fillable = [
        'cluster_key',
        'representative_message',
        'occurrence_count',
        'last_occurred_at',
        'status',
        'facility_id',
        'approved_faq_id',
    ];

    protected $casts = [
        'occurrence_count' => 'integer',
        'last_occurred_at' => 'datetime',
    ];

    public function approvedFaq(): BelongsTo
    {
        return $this->belongsTo(ChatbotFaq::class, 'approved_faq_id');
    }

    public function facility(): BelongsTo
    {
        return $this->belongsTo(Facility::class);
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeOrderedByCount($query)
    {
        return $query->orderByDesc('occurrence_count');
    }

    /**
     * クラスタキー（代表メッセージの正規化ハッシュ）を生成する
     */
    public static function makeClusterKey(string $message): string
    {
        $normalized = preg_replace('/[\s\p{P}]+/u', '', mb_strtolower(trim($message))) ?? $message;

        return substr(md5($normalized), 0, 16);
    }
}
