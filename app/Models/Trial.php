<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Trial extends Model
{
    use HasFactory, SoftDeletes;
    
    protected $fillable = [
        'company_name',
        'contact_name',
        'email',
        'phone',
        'facility_type',
        'resident_capacity',
        'score',
        'status',
        'trial_started_at',
        'trial_ends_at',
        'facility_id',
        'trial_config',
    ];
    
    protected $casts = [
        'trial_started_at' => 'datetime',
        'trial_ends_at' => 'datetime',
        'trial_config' => 'array',
        'score' => 'integer',
    ];
    
    public function isActive(): bool
    {
        return $this->status === 'active';
    }
    
    public function isExpired(): bool
    {
        return $this->status === 'expired' || 
               ($this->trial_ends_at && $this->trial_ends_at->isPast());
    }
    
    public function daysUntilExpiry(): int
    {
        if (!$this->trial_ends_at || $this->trial_ends_at->isPast()) {
            return 0;
        }

        // 日付単位での残り日数（時刻のミリ秒差の影響を排除）
        return (int) $this->trial_ends_at->copy()->startOfDay()
            ->diffInDays(now()->copy()->startOfDay(), true);
    }
    
    /**
     * 施設
     */
    public function facility()
    {
        return $this->belongsTo(Facility::class);
    }
}