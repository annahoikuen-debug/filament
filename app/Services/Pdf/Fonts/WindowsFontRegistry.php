<?php

namespace App\Services\Pdf\Fonts;

use App\Services\Pdf\Contracts\FontRegistryInterface;
use Dompdf\Dompdf;

class WindowsFontRegistry implements FontRegistryInterface
{
    private const FONTS = [
        'YuMincho' => ['yumin.ttf', 'yumindb.ttf'],
        'YuGothic' => ['YuGothR.ttc#0', 'YuGothB.ttc#0'],
        'Meiryo' => ['meiryo.ttc#0', 'meiryob.ttc#0'],
        'msgothic' => ['msgothic.ttc#0', 'msgothic.ttc#0'],
    ];

    private const FONT_DIR = 'C:/Windows/Fonts/';

    public function register(Dompdf $pdf): void
    {
        $fontMapper = $pdf->getFontMetrics();

        // 1. ipaexg（確実な日本語フォント）を優先登録
        $ipaFontDir = storage_path('fonts');
        $normalTtf = $ipaFontDir . '/ipaexg_normal_0ec7c40eaabbd5656858c88c69d1e606.ttf';
        $boldTtf = $ipaFontDir . '/ipaexg_bold_0ec7c40eaabbd5656858c88c69d1e606.ttf';
        if (file_exists($normalTtf)) {
            try {
                $fontMapper->getFont('ipaexg', 'normal');
            } catch (\Throwable) {
                try {
                    $fontMapper->registerFont([
                        'family' => 'ipaexg',
                        'weight' => 'normal',
                        'style' => 'normal',
                    ], $normalTtf);
                } catch (\Throwable) {}
            }
        }
        if (file_exists($boldTtf)) {
            try {
                $fontMapper->getFont('ipaexg', 'bold');
            } catch (\Throwable) {
                try {
                    $fontMapper->registerFont([
                        'family' => 'ipaexg',
                        'weight' => 'bold',
                        'style' => 'normal',
                    ], $boldTtf);
                } catch (\Throwable) {}
            }
        }

        foreach (self::FONTS as $familyName => [$normal, $bold]) {
            // 既に登録済みかチェック
            try {
                $fontMapper->getFont($familyName, 'normal');
                continue;
            } catch (\Throwable) {
                // 未登録なので続行
            }

            try {
                $fontMapper->registerFont(
                    family: $familyName,
                    normal: self::FONT_DIR . $normal,
                    bold: self::FONT_DIR . $bold,
                    italic: self::FONT_DIR . $normal,
                    bold_italic: self::FONT_DIR . $bold,
                );
            } catch (\Throwable $e) {
                // フォント登録失敗時は無視（フォールバックで対応）
            }
        }
    }

    public function getFontFamilies(): array
    {
        return array_merge(['ipaexg'], array_keys(self::FONTS));
    }
}