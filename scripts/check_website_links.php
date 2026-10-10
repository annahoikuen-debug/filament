<?php
// website/ 内の全HTMLの内部リンク・画像参照の存在チェック
$base = __DIR__ . '/../website';
$htmlFiles = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS));
$errors = [];
$count = 0;
foreach ($htmlFiles as $file) {
    if ($file->getExtension() !== 'html') continue;
    $count++;
    $path = $file->getPathname();
    $dir = dirname($path);
    $html = file_get_contents($path);
    if (preg_match_all('/(?:href|src)="([^"]+)"/', $html, $m)) {
        foreach ($m[1] as $url) {
            // ルート配信されるパス（Laravel側で動的配信されるため静的チェック対象外）
            $routeServed = ['/chatbot/widget.js'];
            if (in_array($url, $routeServed, true)) continue;
            if ($url === '' || $url[0] === '#' || str_starts_with($url, 'http') || str_starts_with($url, 'tel:') || str_starts_with($url, 'mailto:')) continue;
            $url = preg_replace('/#.*$/', '', $url);
            if ($url === '') continue;
            $target = realpath($dir . '/' . $url);
            if ($target === false || !file_exists($target)) {
                $errors[] = substr($path, strlen($base) + 1) . ' -> ' . $url;
            }
        }
    }
}
echo "Checked $count HTML files\n";
if ($errors) {
    echo "BROKEN LINKS (" . count($errors) . "):\n";
    foreach ($errors as $e) echo "  $e\n";
    exit(1);
}
echo "No broken links.\n";
