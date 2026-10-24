<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\File;

class ElectronicRecord extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $casts = [
        'transaction_date' => 'date',
        'amount' => 'integer',
        'file_size' => 'integer',
        'version' => 'integer',
    ];

    public function facility(): BelongsTo
    {
        return $this->belongsTo(Facility::class);
    }

    public function recordable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * ファイルのハッシュ値を検証して改ざんの有無を判定
     */
    public function verifyIntegrity(): bool
    {
        if (! File::exists($this->file_path)) {
            return false;
        }

        $currentHash = hash_file('sha256', $this->file_path);

        return hash_equals($this->sha256_hash, $currentHash);
    }
}
