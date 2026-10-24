<?php

namespace App\Models;

use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class User extends Authenticatable implements FilamentUser
{
    use HasFactory, LogsActivity, Notifiable;

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

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([
                'name',
                'email',
                'email_verified_at',
                'is_admin',
                'role',
                'facility_id',
            ])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->setDescriptionForEvent(fn (string $eventName) => "User {$eventName}")
            ->useLogName('user');
    }

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
     * 施設スコープを適用すべきかどうか
     *
     * 法人管理者 (corporate_admin) のみ全施設のデータにアクセスできる。
     * 施設管理者以外のロール（care_staff・ロール未設定等）で施設IDを持つ
     * アカウントも、ここでは必ず自施設に限定される。
     * 施設IDが未設定の従来アカウントは従来通りの挙動を維持する。
     */
    public function requiresFacilityScope(): bool
    {
        return ! $this->isCorporateAdmin() && (bool) $this->facility_id;
    }

    /**
     * 全施設のデータにアクセスできるか（法人管理者のみ）
     */
    public function canAccessAllFacilities(): bool
    {
        return $this->isCorporateAdmin();
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
