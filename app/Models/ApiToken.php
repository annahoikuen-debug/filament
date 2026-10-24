<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApiToken extends Model
{
    protected $fillable = [
        'facility_id',
        'name',
        'token',
        'abilities',
        'last_used_at',
        'expires_at',
    ];

    protected $hidden = [
        'token',
    ];

    protected $casts = [
        'last_used_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    public function facility(): BelongsTo
    {
        return $this->belongsTo(Facility::class);
    }

    /**
     * 権限リストを取得
     *
     * @return array<int, string>
     */
    public function abilityList(): array
    {
        $abilities = $this->abilities;

        if (is_array($abilities)) {
            return $abilities;
        }

        if (is_string($abilities) && $abilities !== '') {
            $decoded = json_decode($abilities, true);

            return is_array($decoded) ? $decoded : array_filter(array_map('trim', explode(',', $abilities)));
        }

        return [];
    }

    /**
     * 指定権限を持つか確認
     */
    public function can(string $ability): bool
    {
        $abilities = $this->abilityList();

        return in_array('*', $abilities, true) || in_array($ability, $abilities, true);
    }

    /**
     * 有効期限切れか確認
     */
    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    /**
     * プレーンテキストのトークンをハッシュ化して保存用に変換
     */
    public static function hashToken(string $plainToken): string
    {
        return hash('sha256', $plainToken);
    }

    /**
     * 新しいトークンを生成（プレーンテキストと保存用レコードを返す）
     *
     * @return array{0: string, 1: self} [plainToken, model]
     */
    public static function issue(int $facilityId, string $name, array $abilities, ?\DateTimeInterface $expiresAt = null): array
    {
        $plainToken = 'sk_'.bin2hex(random_bytes(24));

        $token = static::create([
            'facility_id' => $facilityId,
            'name' => $name,
            'token' => static::hashToken($plainToken),
            'abilities' => json_encode(array_values($abilities)),
            'expires_at' => $expiresAt,
        ]);

        return [$plainToken, $token];
    }

    /**
     * プレーンテキストのトークンから有効なレコードを検索
     */
    public static function findValidToken(string $plainToken, string $ability): ?self
    {
        $token = static::where('token', static::hashToken($plainToken))->first();

        if (! $token || ! $token->can($ability) || $token->isExpired()) {
            return null;
        }

        return $token;
    }
}
