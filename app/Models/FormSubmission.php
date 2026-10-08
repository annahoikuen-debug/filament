<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FormSubmission extends Model
{
    protected $fillable = [
        'type',
        'company',
        'name',
        'email',
        'phone',
        'payload',
    ];

    protected $casts = [
        'payload' => 'array',
    ];
}
