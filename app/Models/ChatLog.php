<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChatLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'session_id',
        'channel',
        'visitor_id',
        'user_message',
        'intent',
        'bot_reply',
        'faq_matched',
        'facility_id',
        'feedback',
        'feedback_at',
    ];

    protected $casts = [
        'faq_matched' => 'boolean',
        'feedback_at' => 'datetime',
        'created_at' => 'datetime',
        'visitor_id' => 'string',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function facility(): BelongsTo
    {
        return $this->belongsTo(Facility::class);
    }

    public function scopeForFacility($query, int $facilityId)
    {
        return $query->where('facility_id', $facilityId);
    }

    public function scopeChannel($query, string $channel)
    {
        return $query->where('channel', $channel);
    }
}
