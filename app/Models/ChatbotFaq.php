<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class ChatbotFaq extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = [
        'question',
        'keywords',
        'answer',
        'category',
        'is_active',
        'sort_order',
        'facility_id',
    ];

    protected $casts = [
        'keywords' => 'array',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([
                'question',
                'keywords',
                'answer',
                'category',
                'is_active',
                'sort_order',
                'facility_id',
            ])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->setDescriptionForEvent(fn (string $eventName) => "ChatbotFaq {$eventName}")
            ->useLogName('chatbot_faq');
    }

    public function facility(): BelongsTo
    {
        return $this->belongsTo(Facility::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('id');
    }
}
