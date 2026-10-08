<?php

namespace App\Services\Pdf;

use App\Models\PdfTemplateSettings;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;

class TemplateSettingsService
{
    private const CACHE_TTL = 3600; // 1時間
    private const CACHE_PREFIX = 'pdf_template_settings_';

    /**
     * テンプレート設定を取得（DB優先、configフォールバック、キャッシュ対応）
     *
     * @param  string  $key  'invoice' or 'receipt'
     * @param  string|null  $locale  ロケール（省略時はapp locale）
     * @param  string|null  $theme  テーマ（省略時はstandard）
     * @return array
     */
    public function getSettings(string $key, ?string $locale = null, ?string $theme = null): array
    {
        $locale = $locale ?? app()->getLocale();
        $theme = $theme ?? 'standard';

        $cacheKey = self::CACHE_PREFIX . "{$key}_{$locale}_{$theme}";

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($key, $locale, $theme) {
            // DBからアクティブなテンプレートを取得（ロケール・テーマ指定）
            $dbTemplate = PdfTemplateSettings::getActive($key, $locale, $theme);

            // configから基本設定を取得
            $baseConfig = Config::get('pdf.default', []);
            $typeConfig = Config::get("pdf.{$key}", []);

            // マージ: base -> type-specific -> DB template
            $merged = array_merge($baseConfig, $typeConfig);

            if ($dbTemplate) {
                $dbConfig = $dbTemplate->toConfigArray();
                $merged = array_merge($merged, $dbConfig);
            }

            return $merged;
        });
    }

    /**
     * キャッシュをクリア
     */
    public function clearCache(?string $key = null, ?string $locale = null, ?string $theme = null): void
    {
        if ($key && $locale && $theme) {
            Cache::forget(self::CACHE_PREFIX . "{$key}_{$locale}_{$theme}");
        } else {
            // 全キャッシュクリア（プレフィックス一致で削除するためタグ使用推奨だが、ここでは全キー列挙）
            foreach (['invoice', 'receipt'] as $k) {
                foreach (['ja', 'en'] as $loc) {
                    foreach (['standard', 'minimal', 'classic', 'modern', 'compact'] as $th) {
                        Cache::forget(self::CACHE_PREFIX . "{$k}_{$loc}_{$th}");
                    }
                }
            }
        }
    }

    /**
     * 全テンプレート一覧取得
     *
     * @param  string|null  $key  フィルタするkey
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getAll(?string $key = null)
    {
        $query = PdfTemplateSettings::orderBy('key')->orderByDesc('is_default')->orderByDesc('version');

        if ($key) {
            $query->where('key', $key);
        }

        return $query->get();
    }

    /**
     * テンプレート作成
     */
    public function create(array $data): PdfTemplateSettings
    {
        // is_defaultがtrueの場合、他のデフォルトを解除
        if (!empty($data['is_default'])) {
            PdfTemplateSettings::where('key', $data['key'])
                ->where('is_default', true)
                ->update(['is_default' => false]);
        }

        // バージョン自動インクリメント
        $maxVersion = PdfTemplateSettings::where('key', $data['key'])
            ->max('version') ?? 0;
        $data['version'] = $maxVersion + 1;

        $template = PdfTemplateSettings::create($data);

        // キャッシュクリア
        $this->clearCache($data['key'] ?? null, $data['locale'] ?? null, $data['theme'] ?? null);

        return $template;
    }

    /**
     * テンプレート更新
     */
    public function update(PdfTemplateSettings $template, array $data): PdfTemplateSettings
    {
        // is_default変更時の処理
        if (isset($data['is_default']) && $data['is_default'] && !$template->is_default) {
            PdfTemplateSettings::where('key', $template->key)
                ->where('id', '!=', $template->id)
                ->where('is_default', true)
                ->update(['is_default' => false]);
        }

        // バージョンアップ
        if (isset($data['version']) && $data['version'] > $template->version) {
            $template->version = $data['version'];
        } else {
            $template->version = $template->version + 1;
        }

        $template->update($data);

        // キャッシュクリア
        $this->clearCache($template->key, $template->locale, $template->theme);

        return $template->fresh();
    }

    /**
     * テンプレート削除（デフォルトは削除不可）
     */
    public function delete(PdfTemplateSettings $template): bool
    {
        if ($template->is_default) {
            throw new \RuntimeException('デフォルトテンプレートは削除できません。');
        }

        $key = $template->key;
        $locale = $template->locale;
        $theme = $template->theme;

        $result = $template->delete();

        // キャッシュクリア
        $this->clearCache($key, $locale, $theme);

        return $result;
    }

    /**
     * デフォルトテンプレートをDBに作成（初期化用）
     */
    public function seedDefaults(): void
    {
        PdfTemplateSettings::createDefaults();

        // キャッシュクリア
        $this->clearCache();
    }
}