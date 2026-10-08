<?php

namespace App\Services\Pdf\Fonts;

use App\Services\Pdf\Contracts\FontRegistryInterface;
use Dompdf\Dompdf;

class WindowsFontRegistry implements FontRegistryInterface
{
    private const FONT_DIR = 'C:/Windows/Fonts/';

    public function register(Dompdf $pdf): void
    {
        $fontMapper = $pdf->getFontMetrics();

        // 1. ipaexg（確実な日本語フォント）を登録
        $ipaFontDir = storage_path('fonts');
        $normalTtf = $ipaFontDir . '/ipaexg_normal_0ec7c40eaabbd5656858c88c69d1e606.ttf';
        $boldTtf = $ipaFontDir . '/ipaexg_bold_0ec7c40eaabbd5656858c88c69d1e606.ttf';

        if (file_exists($normalTtf)) {
            try {
                $fontMapper->getFont('ipaexg', 'normal');
            } catch (\Throwable) {
                try {
                    $url = 'file:///' . str_replace('\\', '/', $normalTtf);
                    $fontMapper->registerFont([
                        'family' => 'ipaexg',
                        'weight' => 'normal',
                        'style' => 'normal',
                    ], $url);
                } catch (\Throwable $e) {
                    \Illuminate\Support\Facades\Log::warning('Failed to register ipaexg normal: ' . $e->getMessage());
                }
            }
        }

        if (file_exists($boldTtf)) {
            try {
                $fontMapper->getFont('ipaexg', 'bold');
            } catch (\Throwable) {
                try {
                    $url = 'file:///' . str_replace('\\', '/', $boldTtf);
                    $fontMapper->registerFont([
                        'family' => 'ipaexg',
                        'weight' => 'bold',
                        'style' => 'normal',
                    ], $url);
                } catch (\Throwable $e) {
                    \Illuminate\Support\Facades\Log::warning('Failed to register ipaexg bold: ' . $e->getMessage());
                }
            }
        }

// 3. Noto Sans JP フォントの登録
$notoDir = storage_path('fonts');
$notoNormal = $notoDir . '/NotoSansJP-Regular.ttf';
$notoBold   = $notoDir . '/NotoSansJP-Bold.ttf';

if (file_exists($notoNormal)) {
    try {
        $fontMapper->getFont('NotoSansJP', 'normal');
    } catch (\Throwable) {
        try {
            $url = 'file:///' . str_replace('\\', '/', $notoNormal);
            $fontMapper->registerFont([
                'family' => 'NotoSansJP',
                'weight' => 'normal',
                'style'  => 'normal',
            ], $url);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Failed to register NotoSansJP normal: ' . $e->getMessage());
        }
    }
}

if (file_exists($notoBold)) {
    try {
        $fontMapper->getFont('NotoSansJP', 'bold');
    } catch (\Throwable) {
        try {
            $url = 'file:///' . str_replace('\\', '/', $notoBold);
            $fontMapper->registerFont([
                'family' => 'NotoSansJP',
                'weight' => 'bold',
                'style'  => 'normal',
            ], $url);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Failed to register NotoSansJP bold: ' . $e->getMessage());
        }
    }
}

// 2. Windows標準フォント（YuMincho, YuGothic等）の登録
        $windowsFonts = [
            'YuMincho' => ['normal' => 'yumin.ttf', 'bold' => 'yumindb.ttf'],
            'YuGothic' => ['normal' => 'YuGothR.ttc#0', 'bold' => 'YuGothB.ttc#0'],
            'Meiryo' => ['normal' => 'meiryo.ttc#0', 'bold' => 'meiryob.ttc#0'],
            'msgothic' => ['normal' => 'msgothic.ttc#0', 'bold' => 'msgothic.ttc#0'],
        ];

        foreach ($windowsFonts as $familyName => $files) {
            try {
                $fontMapper->getFont($familyName, 'normal');
                continue;
            } catch (\Throwable) {
                // 未登録なので登録試行
            }

            foreach (['normal' => $files['normal'], 'bold' => $files['bold']] as $weight => $fileName) {
                $path = self::FONT_DIR . $fileName;
                $cleanPath = explode('#', $path)[0];
                if (file_exists($cleanPath)) {
                    try {
                        $url = 'file:///' . str_replace('\\', '/', $path);
                        $fontMapper->registerFont([
                            'family' => $familyName,
                            'weight' => $weight,
                            'style' => 'normal',
                        ], $url);
                    } catch (\Throwable $e) {
                        // 登録失敗は無視
                    }
                }
            }
        }
    }

     public function getFontFamilies(): array
     {
         return ['ipaexg', 'YuMincho', 'YuGothic', 'Meiryo', 'msgothic', 'NotoSansJP'];
     }
}
