<?php
// DB状態確認用スクリプト
$dbPath = __DIR__ . '/../../database/database.sqlite';
if (! file_exists($dbPath)) {
    echo "DB not found: {$dbPath}\n";
    exit(1);
}

$db = new PDO('sqlite:' . $dbPath);
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$tables = $db->query("SELECT name FROM sqlite_master WHERE type='table' ORDER BY name")->fetchAll(PDO::FETCH_COLUMN);

echo "=== Tables ===\n";
foreach ($tables as $table) {
    try {
        $stmt = $db->prepare('SELECT COUNT(*) FROM "' . str_replace('"', '', $table) . '"');
        $count = $stmt->fetchColumn();
    } catch (Throwable $e) {
        $count = 'ERR: ' . $e->getMessage();
    }
    echo str_pad($table, 40) . " {$count}\n";
}
