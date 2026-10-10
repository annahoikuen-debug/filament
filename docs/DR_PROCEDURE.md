# 災害復旧（DR）手順書

## 1. 目的と目標値

| 項目 | 目標 |
|------|------|
| RPO（目標復旧時点） | 24時間以内（毎日 03:00 の自動バックアップ） |
| RTO（目標復旧時間） | 4時間以内 |

- バックアップは [`backup:database`](../app/Console/Commands/DatabaseBackup.php) コマンドにより、データベースダンプ＋ストレージファイル（`storage/app` 配下、`backups` を除く）を取得する。
- バックアップは 30 日間保持され、期限切れ分は自動削除される。
- 保存先: `storage/app/backups/YYYY-MM-DD_HHMMSS/`（`--compress` 指定時は `.zip`）。
- S3 設定がある場合は [`uploadToS3()`](../app/Console/Commands/DatabaseBackup.php) によりオフサイトへもアップロードする。

## 2. バックアップの取得

### 2.1 自動バックアップ（本番）

[`routes/console.php`](../routes/console.php) に登録されたスケジューラにより、毎日 03:00（production / staging）に実行:

```bash
php artisan backup:database --type=full --compress --retention=30
```

### 2.2 手動バックアップ

```bash
php artisan backup:database --type=full --compress
```

- `--type` : `database`（DBのみ） / `storage`（ストレージのみ） / `full`（両方、デフォルト）
- `--compress` : ZIP 圧縮して保存
- `--retention=N` : N 日より古いバックアップを削除

## 3. リストア手順

> ⚠️ リストア実行前に必ず現在の状態の退避バックアップが自動作成される（`pre_restore_*` プレフィックス）。これを削除しないこと。

### 3.1 ドライラン（影響確認）

```bash
php artisan backup:restore storage/app/backups/2026-10-09_030000.zip --dry-run
```

実行されるステップの一覧が表示されるのみで、データは変更されない。

### 3.2 リストア実行

```bash
php artisan backup:restore storage/app/backups/2026-10-09_030000.zip --force
```

処理内容:
1. ZIP の展開（一時ディレクトリ `restore_temp_*`）
2. 退避用の pre_restore バックアップ作成
3. データベースリストア（MySQL / PostgreSQL / SQLite に対応。対応するダンプファイルを自動検出）
4. ストレージリストア（`storage/app` 配下を復元、`backups` ディレクトリは除外）
5. 一時ディレクトリの削除

### 3.3 リストア後の検証

```bash
# 1. アプリケーションが正常起動するか
php artisan migrate:status

# 2. 主要データの存在確認（件数が 0 でないこと）
php artisan tinker --execute="echo App\Models\Resident::count(); echo App\Models\MonthlyInvoice::count();"

# 3. 設定キャッシュのクリア
php artisan config:clear && php artisan cache:clear

# 4. ログイン動作・領収書 PDF 生成の動作確認
```

検証結果をインシデント記録（付録 A テンプレート）に記載する。

## 4. インシデント対応フロー

```
1. 発覚（監視アラート / 利用者報告 / バックアップ失敗通知）
      ↓
2. 影響範囲の特定（データ損失の有無・期間、RPO との比較）
      ↓
3. 初動報告（施設管理者 → 経営者）
      ↓
4. 復旧判断（最新バックアップからのリストア可否）
      ↓
5. リストア実施（本手順 3 節。目標: RTO 4 時間以内）
      ↓
6. 検証（本手順 3.3 節）
      ↓
7. 再発防止策の検討・実装
      ↓
8. インシデント記録の完了・報告書作成
```

### 4.1 連絡体制

| 役割 | 担当 | タイミング |
|------|------|-----------|
| 第一発見者 | 施設管理者 / システム管理者 | 即時 |
| 復旧責任者 | システム管理者 | リストア判断時 |
| 最終決裁 | 経営者 | データ損失が確定した場合 |

## 5. バックアップの正常性確認（月次）

- 毎月 1 回、直近のバックアップに対して `--dry-run` でリストア手順が実行可能であることを確認する。
- バックアップディレクトリのサイズが極端に小さくないか（[`formatBytes()`](../app/Console/Commands/DatabaseBackup.php) 出力や `dir` コマンドで確認）目視チェックする。
- S3 へのオフサイト保存を有効にしている場合は、S3 側のオブジェクト存在も確認する。

## 6. 注意事項

- バックアップには個人情報（入居者氏名・生年月日・請求情報）が含まれるため、保管場所へのアクセス制御を徹底すること。
- S3 保存時はバケットの暗号化（SSE-S3 / SSE-KMS）とパブリックアクセスブロックを必須とする。
- 退避用 `pre_restore_*` バックアップはインシデント完結後、問題がないことを確認してから削除する。
- 本手順書は構成変更時に必ず更新すること。

## 付録 A: インシデント記録テンプレート

```
発生日時:
発見者:
影響範囲（データ損失期間・対象施設）:
使用したバックアップ（パス・日時）:
リストア実行日時:
検証結果:
完了日時:
再発防止策:
```
