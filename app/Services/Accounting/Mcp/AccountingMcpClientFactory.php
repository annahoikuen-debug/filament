<?php

namespace App\Services\Accounting\Mcp;

use App\Models\Facility;
use Illuminate\Support\Facades\Log;

class AccountingMcpClientFactory
{
    /**
     * 施設と会計ソフト種類からMCPクライアントを生成
     */
    public static function createForFacility(Facility $facility, string $softwareType): ?AccountingMcpClientInterface
    {
        return match ($softwareType) {
            Facility::SOFTWARE_FREEE => self::createFreeeClient($facility),
            Facility::SOFTWARE_MF => self::createMfClient($facility),
            default => null,
        };
    }

    /**
     * freee クライアントを生成
     */
    public static function createFreeeClient(Facility $facility): ?FreeeMcpClient
    {
        if (! $facility->hasFreeeCredentials()) {
            return null;
        }

        // トークン期限チェック・リフレッシュ
        if (! $facility->isFreeeTokenValid()) {
            $refreshed = self::refreshFreeeToken($facility);
            if (! $refreshed) {
                Log::warning('[freee] トークンリフレッシュに失敗しました', ['facility_id' => $facility->id]);

                return null;
            }
            $facility->refresh();
        }

        return new FreeeMcpClient(
            $facility->freee_access_token,
            $facility->freee_company_id,
            ['timeout' => 60, 'max_retries' => 3]
        );
    }

    /**
     * MFクライアントを生成
     */
    public static function createMfClient(Facility $facility): ?MfMcpClient
    {
        if (! $facility->hasMfCredentials()) {
            return null;
        }

        // トークン期限チェック・リフレッシュ
        if (! $facility->isMfTokenValid()) {
            $refreshed = self::refreshMfToken($facility);
            if (! $refreshed) {
                Log::warning('[MF] トークンリフレッシュに失敗しました', ['facility_id' => $facility->id]);

                return null;
            }
            $facility->refresh();
        }

        return new MfMcpClient(
            $facility->mf_access_token,
            $facility->mf_office_id,
            ['timeout' => 60, 'max_retries' => 3]
        );
    }

    /**
     * freee トークンをリフレッシュ
     */
    private static function refreshFreeeToken(Facility $facility): bool
    {
        if (empty($facility->freee_refresh_token) || empty($facility->freee_client_id) || empty($facility->freee_client_secret)) {
            return false;
        }

        try {
            $tempClient = new FreeeMcpClient('', 0);
            $newTokens = $tempClient->refreshToken(
                $facility->freee_client_id,
                $facility->freee_client_secret,
                $facility->freee_refresh_token
            );

            $facility->update([
                'freee_access_token' => $newTokens['access_token'],
                'freee_refresh_token' => $newTokens['refresh_token'] ?? $facility->freee_refresh_token,
                'freee_token_expires_at' => $newTokens['expires_at'] ?? null,
            ]);

            return true;
        } catch (\Throwable $e) {
            Log::error('[freee] トークンリフレッシュエラー', [
                'facility_id' => $facility->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * MF トークンをリフレッシュ
     */
    private static function refreshMfToken(Facility $facility): bool
    {
        if (empty($facility->mf_refresh_token) || empty($facility->mf_client_id) || empty($facility->mf_client_secret)) {
            return false;
        }

        try {
            $tempClient = new MfMcpClient('', 0);
            $newTokens = $tempClient->refreshToken(
                $facility->mf_client_id,
                $facility->mf_client_secret,
                $facility->mf_refresh_token
            );

            $facility->update([
                'mf_access_token' => $newTokens['access_token'],
                'mf_refresh_token' => $newTokens['refresh_token'] ?? $facility->mf_refresh_token,
                'mf_token_expires_at' => $newTokens['expires_at'] ?? null,
            ]);

            return true;
        } catch (\Throwable $e) {
            Log::error('[MF] トークンリフレッシュエラー', [
                'facility_id' => $facility->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }
}
