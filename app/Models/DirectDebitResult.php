<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DirectDebitResult extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'amount' => 'integer',
        'is_success' => 'boolean',
        'processed_at' => 'date',
    ];

    public function monthlyInvoice(): BelongsTo
    {
        return $this->belongsTo(MonthlyInvoice::class);
    }

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(ResidentBankAccount::class, 'resident_bank_account_id');
    }
}
