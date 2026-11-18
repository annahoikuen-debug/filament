# 実装計画書 - コードレビュー指摘事項の修正

## 1. 目的

本計画書は、コードレビュー（2026-11-17）で特定した以下の3つの改善事項を実施するための詳細な実装計画を定義する。

| ID | 優先度 | 対象ファイル | 内容 |
|----|--------|--------------|------|
| CR-01 | High | `composer.json` | PHP バージョン制約を `^8.2` から `^8.4.27` に引き上げ、CVE-2026-109618/109619 対応バージョンを必須化 |
| CR-02 | Medium | `.gitignore` | ストレージ内実行アーティファクト（PDF/ZIP/テストディスク等）を git 追跡から除外 |
| CR-03 | Low | `app/Models/Resident.php` | `saving` イベント内で毎回 `new FuzzyMatcher` をインスタンス化している箇所を最適化 |

## 2. 前提条件

- PHP 8.3.36 (CLI) がローカル環境にインストール済み（ただし Docker/CI は PHP 8.4.27+ を前提）
- `composer` コマンドは Windows 環境では未インストール（`vendor/` は未インストール状態）
- git リポジトリは `E:\seikyu` で管理済み。master/develop ブランチが存在
- Pint (Laravel コード整形ツール) は `composer.json` の `require-dev` に定義済みだが、`vendor/` 未導入のため未実行

## 3. 実装範囲（インスコープ）

### CR-01: `composer.json` PHP バージョン制約の引き上げ

**現状:**
```json
"require": {
    "php": "^8.2",
    ...
}
```

**変更後:**
```json
"require": {
    "php": "^8.4.27",
    ...
}
```

**理由:**
- Dockerfile (`Dockerfile:5`, `Dockerfile:47`) で PHP 8.4.27 未満のビルドを失敗させる CVE-2026-109618/CVE-2026-109619 対策が既に導入済み
- CI (`.github/workflows/ci.yml`) も PHP 8.4 固定
- `composer.json` の `^8.2` は 8.2/8.3 を許容しており、脆弱バージョンでのインストールを防げない
- コミット `7133466` などのセキュリティ修正履歴と整合性を取る

### CR-02: `.gitignore` へのストレージアーティファクト追加

**現状の問題:**
`git status` に以下の実行アーティファクトが表示され、リポジトリが汚染される:
- `storage/app/temp/` 内の ZIP ファイル
- `storage/framework/testing/disks/s3/database_backups/` 内のテストバックアップ
- `storage/app/private/invoices/` 内の生成済 PDF（請求書・領収書）

**変更後:** 以下を `.gitignore` に追加:
```
/storage/app/temp/*
/storage/app/private/invoices/*
/storage/framework/testing/disks/s3/database_backups/*
```

**理由:**
- 実行時に生成される一時ファイル・出力ファイルをバージョン管理対象外にする
- リポジトリ衛生の維持（コミット `90bd591` の Pint 整方針と整合）

### CR-03: `Resident.php` の FuzzyMatcher 最適化

**現状:**
`app/Models/Resident.php:59` の `saving` イベント内で、保存毎に `new FuzzyMatcher` をインスタンス化:
```php
$matcher = new FuzzyMatcher;
```

**変更後:**
`FuzzyMatcher` を stateless なユーティリティとして扱い、`static` メソッド化するか、シングルトンインスタンスを再利用する。

**理由:**
- モデル保存毎にオブジェクト生成が発生し、パフォーマンスに微小だが不要なオーバーヘッド
- `FuzzyMatcher` には状態を持つプロパティがないため、static 化可能

## 4. 非実装範囲（アウトスコープ）

- 既存機能の新規追加
- テストコードの大幅な追加（CR-01/CR-02 は既存テストで担保）
- Dockerfile の PHP 8.4.27 チェックは既に実装済みのため変更不要
- Pint 整形の実行（`vendor/` 未導入のため、CI 側で担保）

## 5. 詳細実装手順

### ステップ 1: `composer.json` の PHP バージョン制約変更

**ファイル:** `E:\seikyu\composer.json`

**編集箇所:** 7行目

```diff
-        "php": "^8.2",
+        "php": "^8.4.27",
```

**検証:** `composer.json` が有効な JSON であることを確認（`php -r "json_decode(file_get_contents('composer.json'));"`）

### ステップ 2: `.gitignore` へのストレージアーティファクト追加

**ファイル:** `E:\seikyu\.gitignore`

**編集箇所:** ファイル末尾に追加

```diff
+# 実行時生成アーティファクト（バージョン管理対象外）
+/storage/app/temp/*
+/storage/app/private/invoices/*
+/storage/framework/testing/disks/s3/database_backups/*
```

**検証:** `git status --short` で該当ファイルが表示されなくなることを確認

### ステップ 3: `Resident.php` の FuzzyMatcher 最適化

**ファイル:** `E:\seikyu\app\Models\Resident.php`

**編集方針:**
`FuzzyMatcher` クラスを確認し、stateless であることを確認した上で、`static` メソッド `normalizeKana()` を追加するか、または `saving` イベント外でインスタンスを一度だけ作成して再利用する。

**案 A（推奨）: `FuzzyMatcher::normalizeKana()` を static にする**

`app/Services/Chatbot/FuzzyMatcher.php`:
```php
public static function normalizeKana(string $text): string
{
    // 既存のインスタンスメソッドのロジックを static 化
    $matcher = new self;
    return $matcher->normalizeKana($text);
}
```

`app/Models/Resident.php`:
```diff
-            $matcher = new FuzzyMatcher;
-            if ($resident->name) {
-                $resident->normalized_name = $matcher->normalizeKana($resident->name);
-            }
-            if ($resident->name_kana) {
-                $resident->normalized_kana = $matcher->normalizeKana($resident->name_kana);
-            }
-            if ($resident->nickname) {
-                $resident->normalized_nickname = $matcher->normalizeKana($resident->nickname);
-            } else {
-                $resident->normalized_nickname = null;
-            }
+            if ($resident->name) {
+                $resident->normalized_name = FuzzyMatcher::normalizeKana($resident->name);
+            }
+            if ($resident->name_kana) {
+                $resident->normalized_kana = FuzzyMatcher::normalizeKana($resident->name_kana);
+            }
+            if ($resident->nickname) {
+                $resident->normalized_nickname = FuzzyMatcher::normalizeKana($resident->nickname);
+            } else {
+                $resident->normalized_nickname = null;
+            }
```

**検証:** `tests/Unit/ResidentModelTest.php` 等で `normalized_name` / `normalized_kana` / `normalized_nickname` の設定が正しく動作することを確認

## 6. 検証計画

| 検証項目 | 方法 | 期待結果 |
|----------|------|----------|
| `composer.json` JSON 妥当性 | `php -r "json_decode(file_get_contents('composer.json'));"` | エラーなし |
| PHP バージョン制約 | CI の PHP 8.4 ジョブで `composer install` | 8.4.27+ でインストール成功 |
| `.gitignore` 除外 | `git status --short` | ストレージアーティファクトが表示されない |
| `Resident.php` 正規化 | `tests/Unit/ResidentModelTest.php` 実行 | `normalized_*` 属性が正しく設定される |
| Pint 整形 | CI `code-style` ジョブ | `vendor/bin/pint --test` が通過 |

## 7. リスクとRollback

| リスク | 影響度 | 対策 |
|--------|--------|------|
| `composer.json` の `^8.4.27` により、PHP 8.2/8.3 環境での `composer install` が失敗 | Medium | Dockerfile/CI は既に 8.4 固定。ローカル開発環境も 8.4 へ移行を案内 |
| `.gitignore` 追加により、誤って重要ファイルが除外される | Low | 対象は `temp/`、`private/invoices/`、`testing/` のみで、ソースコードは含まない |
| `FuzzyMatcher` static 化による挙動変更 | Low |  stateless であることを確認済み。既存テストで担保 |

**Rollback手順:**
- `git revert <commit>` で該当コミットを打ち消し
- または、`git checkout HEAD -- <ファイル>` で個別ファイルを元に戻す

## 8. スケジュール見積もり

| ステップ | 工数 | 担当 |
|----------|------|------|
| ステップ 1: composer.json 修正 | 0.5h | 開発者 |
| ステップ 2: .gitignore 修正 | 0.5h | 開発者 |
| ステップ 3: Resident.php 最適化 | 1.0h | 開発者 |
| 検証・テスト実行 | 1.0h | 開発者 |
| **合計** | **3.0h** | |

## 9. 関連ドキュメント

- `README.md` - 技術スタック（PHP 8.2+ 表記は `^8.4.27` に合わせる必要あり）
- `Dockerfile` - PHP 8.4.27 チェック（既に実装済み）
- `.github/workflows/ci.yml` - CI PHP 8.4 固定（既に実装済み）
- `plans/UX_EFFICIENCY_ROADMAP_36_STEPS.md` - UX 改善ロードマップ（本計画とは別スコープ）

## 10. 承認フロー

1. 開発者が本計画書に基づき実装を実施
2. レビュアーが差分をレビュー
3. CI（Pint + テスト）が通過後、マージ

---
作成日: 2026-11-17
作成者: Kilo (Software Engineer)
対象バージョン: v1.7.2 "あんしん"