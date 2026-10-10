<?php

namespace Tests\Feature\Chatbot\Public;

use Tests\TestCase;

class SiteEmbedTest extends TestCase
{
    private string $websiteRoot;

    protected function setUp(): void
    {
        parent::setUp();
        $this->websiteRoot = dirname(__DIR__, 4).'/website';
    }

    /**
     * @return array<int, string>
     */
    private function getHtmlFiles(): array
    {
        $files = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->websiteRoot, \RecursiveDirectoryIterator::SKIP_DOTS),
        );

        foreach ($iterator as $file) {
            if ($file->getExtension() === 'html') {
                $files[] = $file->getPathname();
            }
        }

        sort($files);

        return $files;
    }

    public function test_all_pages_except_404_contain_widget_script_tag_exactly_once(): void
    {
        $files = $this->getHtmlFiles();

        // 31ページ（404除く30ページ）以上存在すること
        $this->assertGreaterThanOrEqual(30, count($files) - 1);

        $failures = [];
        foreach ($files as $file) {
            $name = basename($file);

            if ($name === '404.html') {
                $content = file_get_contents($file);
                if (str_contains($content, '<!-- Chatbot widget -->')) {
                    $failures[] = "$file: 404.html に埋め込みしてはならない";
                }

                continue;
            }

            $content = file_get_contents($file);
            $count = substr_count($content, '<!-- Chatbot widget -->');

            if ($count !== 1) {
                $failures[] = "$file: widget script タグが {$count} 回含まれている（期待: 1回）";
            }
        }

        $this->assertSame([], $failures, implode("\n", $failures));
    }

    public function test_embedded_script_tag_has_correct_attributes(): void
    {
        $files = $this->getHtmlFiles();
        $target = null;

        foreach ($files as $file) {
            if (basename($file) === 'index.html') {
                $target = $file;
                break;
            }
        }

        $this->assertNotNull($target);
        $content = file_get_contents($target);

        $this->assertStringContainsString('src="/chatbot/widget.js"', $content);
        $this->assertStringContainsString('data-api-url="/api/public/chatbot/message"', $content);
        $this->assertStringContainsString('defer', $content);
    }
}
