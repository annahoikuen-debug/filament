<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Subscription extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'trial_id',
        'facility_id',
        'plan',
        'status',
        'monthly_price',
        'started_at',
        'ends_at',
        'contract_accepted_at',
        'contract_accepted_ip',
        'contract_accepted_user_agent',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'ends_at' => 'datetime',
        'contract_accepted_at' => 'datetime',
        'monthly_price' => 'integer',
    ];

    public function trial()
    {
        return $this->belongsTo(Trial::class);
    }

    public function facility()
    {
        return $this->belongsTo(Facility::class);
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }
}
