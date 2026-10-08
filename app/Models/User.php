<?php

namespace App\Models;

use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable implements FilamentUser
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'email_verified_at',
        'is_admin',
        'role',
        'facility_id',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'is_admin' => 'boolean',
    ];

    /**
     * 所属施設
     */
    public function facility()
    {
        return $this->belongsTo(Facility::class);
    }

    /**
     * 法人管理者かどうか
     */
    public function isCorporateAdmin(): bool
    {
        return $this->role === 'corporate_admin';
    }

    /**
     * 施設管理者かどうか
     */
    public function isFacilityAdmin(): bool
    {
        return $this->role === 'facility_admin';
    }

    /**
     * Filament管理画面へのアクセス認可
     */
    public function canAccessPanel(Panel $panel): bool
    {
        // 管理者フラグを持つアカウントのみアクセス許可
        return $this->is_admin === true;
    }
}
