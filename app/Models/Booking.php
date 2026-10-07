<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Booking extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'trial_id',
        'name',
        'email',
        'phone',
        'preferred_date',
        'preferred_time',
        'notes',
        'status',
        'confirmed_at',
    ];

    protected $casts = [
        'preferred_date' => 'date',
        'confirmed_at' => 'datetime',
    ];

    public function trial()
    {
        return $this->belongsTo(Trial::class);
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }
}
