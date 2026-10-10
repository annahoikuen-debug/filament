<?php

namespace Tests\Unit\Pdf;

use App\Services\Pdf\Fonts\WindowsFontRegistry;
use Dompdf\Dompdf;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class WindowsFontRegistryFilesTest extends TestCase
{
    private WindowsFontRegistry $registry;

    private string $fontsDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->registry = new WindowsFontRegistry;
        $this->fontsDir = storage_path('fonts');
        File::ensureDirectoryExists($this->fontsDir);
    }

    protected function tearDown(): void
    {
        // ダミーフォントファイルを削除
        foreach (File::glob($this->fontsDir.'/*') as $file) {
            @unlink($file);
        }
        parent::tearDown();
    }

    private function createDummyFont(string $filename): string
    {
        $path = $this->fontsDir.'/'.$filename;
        // 有効なTTFでなくても良い: レジストリは失敗を握りつぶして警告ログに記録する
        file_put_contents($path, 'DUMMY FONT DATA for testing');
        return $path;
    }

    public function test_register_with_ipaexg_font_files_present(): void
    {
        $this->createDummyFont('ipaexg_normal_0ec7c40eaabbd5656858c88c69d1e606.ttf');
        $this->createDummyFont('ipaexg_bold_0ec7c40eaabbd5656858c88c69d1e606.ttf');
        $this->createDummyFont('NotoSansJP-Regular.ttf');
        $this->createDummyFont('NotoSansJP-Bold.ttf');

        $pdf = new Dompdf;

        // 例外を投げずに完了すること（ファイル不在時・無効フォント時も握りつぶす）
        $this->registry->register($pdf);

        $families = $this->registry->getFontFamilies();
        $this->assertContains('ipaexg', $families);
        $this->assertContains('NotoSansJP', $families);
    }

    public function test_register_with_missing_font_files_does_not_throw(): void
    {
        $pdf = new Dompdf;

        $this->registry->register($pdf);

        $this->assertNotEmpty($this->registry->getFontFamilies());
    }

    public function test_register_with_windows_fonts(): void
    {
        // Windows 環境の C:/Windows/Fonts に存在するフォントを登録
        $pdf = new Dompdf;

        $this->registry->register($pdf);

        $families = $this->registry->getFontFamilies();
        $this->assertIsArray($families);
        $this->assertCount(6, $families);
        foreach (['ipaexg', 'YuMincho', 'YuGothic', 'Meiryo', 'msgothic', 'NotoSansJP'] as $family) {
            $this->assertContains($family, $families);
        }
    }

    public function test_get_font_families_returns_all_six_families(): void
    {
        $families = $this->registry->getFontFamilies();

        $this->assertSame(
            ['ipaexg', 'YuMincho', 'YuGothic', 'Meiryo', 'msgothic', 'NotoSansJP'],
            $families
        );
    }
}
