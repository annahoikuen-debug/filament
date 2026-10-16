<?php

namespace App\Services\Chatbot;

use Illuminate\Support\Facades\Cache;

/**
 * チャットボットのセッション文脈ストア
 *
 * session_id に紐づく対話文脈（入居者 ID・直前 intent・直前年月）を
 * Laravel Cache に 15 分間保持する軽量ストア。
 */
final class ChatbotSessionContext
{
    private const TTL_MINUTES = 15;

    private const PREFIX = 'chatbot_context:';

    public static function get(string $sessionId, string $key): mixed
    {
        if ($sessionId === '') {
            return null;
        }

        return Cache::get(self::PREFIX."{$sessionId}:{$key}");
    }

    public static function put(string $sessionId, string $key, mixed $value): void
    {
        if ($sessionId === '') {
            return;
        }

        Cache::put(self::PREFIX."{$sessionId}:{$key}", $value, now()->addMinutes(self::TTL_MINUTES));
    }

    public static function forget(string $sessionId, string $key): void
    {
        if ($sessionId === '') {
            return;
        }

        Cache::forget(self::PREFIX."{$sessionId}:{$key}");
    }
}
