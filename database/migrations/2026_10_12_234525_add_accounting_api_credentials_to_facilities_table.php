<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('facilities', function (Blueprint $table) {
            // freee API認証情報
            $table->string('freee_client_id')->nullable()->comment('freee API Client ID');
            $table->string('freee_client_secret')->nullable()->comment('freee API Client Secret');
            $table->text('freee_access_token')->nullable()->comment('freee API Access Token');
            $table->text('freee_refresh_token')->nullable()->comment('freee API Refresh Token');
            $table->timestamp('freee_token_expires_at')->nullable()->comment('freee トークン有効期限');
            $table->unsignedBigInteger('freee_company_id')->nullable()->comment('freee 事業所ID');

            // MFクラウド会計 API認証情報
            $table->string('mf_client_id')->nullable()->comment('MF API Client ID');
            $table->string('mf_client_secret')->nullable()->comment('MF API Client Secret');
            $table->text('mf_access_token')->nullable()->comment('MF API Access Token');
            $table->text('mf_refresh_token')->nullable()->comment('MF API Refresh Token');
            $table->timestamp('mf_token_expires_at')->nullable()->comment('MF トークン有効期限');
            $table->unsignedBigInteger('mf_office_id')->nullable()->comment('MF 事業所ID');

            // 共通設定
            $table->json('accounting_api_settings')->nullable()->comment('会計API共通設定 (sync_enabled, auto_sync_on_export, etc.)');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('facilities', function (Blueprint $table) {
            $table->dropColumn([
                'freee_client_id',
                'freee_client_secret',
                'freee_access_token',
                'freee_refresh_token',
                'freee_token_expires_at',
                'freee_company_id',
                'mf_client_id',
                'mf_client_secret',
                'mf_access_token',
                'mf_refresh_token',
                'mf_token_expires_at',
                'mf_office_id',
                'accounting_api_settings',
            ]);
        });
    }
};
