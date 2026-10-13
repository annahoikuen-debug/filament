<?php
/**
 * 機能説明アニメーションGIF 24個 生成スクリプト
 * 使用: php scripts/generate_feature_gifs.php
 * 出力: website/assets/gifs/gif-XX-*.gif
 *
 * 依存: PHP GD拡張, resources/fonts/ipaexg.ttf
 * 実装計画書: docs/FEATURE_GIF_IMPLEMENTATION_PLAN.md
 */

const W = 480;
const H = 360;
const DELAY = 60; // 0.6秒/フレーム

// ---------------------------------------------------------------------------
// GIF構築クラス（GIF89a 連結方式 / 共通128色パレット）
// ---------------------------------------------------------------------------
class GifBuilder
{
    /** @var string[] */
    private array $frames = [];
    private int $delay;
    private array $palette;

    public function __construct(int $delay = DELAY)
    {
        $this->delay = $delay;
        // 共通パレット: インデックス0=白, 1=黒, その後ブランド色
        $this->palette = [
            [255,255,255],[20,20,20],[59,130,246],[16,185,129],
            [239,68,68],[245,158,11],[107,114,128],[229,231,235],
            [243,244,246],[30,41,59],[255,255,204],[204,255,204],
            [255,204,204],[204,204,255],[255,224,178],[178,235,242],
            [252,231,243],[220,252,231],[254,215,170],[199,210,254],
            [241,245,249],[148,163,184],[71,85,105],[51,65,85],
            [255,255,255],[250,250,249],[245,245,244],[240,240,239],
            [235,235,234],[230,230,229],[225,225,224],[220,220,219],
            [215,215,214],[210,210,209],[205,205,204],[200,200,199],
            [195,195,194],[190,190,189],[185,185,184],[180,180,179],
            [175,175,174],[170,170,169],[165,165,164],[160,160,159],
            [155,155,154],[150,150,149],[145,145,144],[140,140,139],
            [135,135,134],[130,130,129],[125,125,124],[120,120,119],
            [115,115,114],[110,110,109],[105,105,104],[100,100,99],
            [95,95,94],[90,90,89],[85,85,84],[80,80,79],
            [75,75,74],[70,70,69],[65,65,64],[60,60,59],
            [55,55,54],[50,50,49],[45,45,44],[40,40,39],
            [35,35,34],[30,30,29],[25,25,24],[22,22,21],
            // 残りはグレーを敷き詰める
        ];
        // 128色まで拡張
        for ($i = count($this->palette); $i < 128; $i++) {
            $v = (int)(40 + ($i % 40) * 5);
            $this->palette[] = [$v, $v, $v];
        }
    }

    /** パレットインデックスへスナップ */
    public function color($im, int $r, int $g, int $b): int
    {
        $best = 0; $bestD = PHP_INT_MAX;
        foreach ($this->palette as $i => $p) {
            $d = ($p[0]-$r)**2 + ($p[1]-$g)**2 + ($p[2]-$b)**2;
            if ($d < $bestD) { $bestD = $d; $best = $i; }
        }
        return imagecolorresolve($im, $this->palette[$best][0], $this->palette[$best][1], $this->palette[$best][2]);
    }

    public function addFrame($im): void
    {
        imagepalettetotruecolor($im);
        imagetruecolortopalette($im, false, 128);
        ob_start();
        imagegif($im);
        $this->frames[] = ob_get_clean();
    }

    public function count(): int { return count($this->frames); }

    /** アニメーションGIFバイナリを生成 */
    public function build(): string
    {
        if (empty($this->frames)) throw new RuntimeException('no frames');
        $first = $this->frames[0];
        // 先頭フレーム = Header(6) + LSD(7) + GCT + [GCE + ImgDesc + data] ...
        // LSDの色テーブルフラグからGCTサイズを算出
        $packed = ord($first[10]);
        $gctFlag = ($packed >> 7) & 1;
        $gctSize = $gctFlag ? 2 ** ((($packed & 0x07)) + 1) * 3 : 0;
        $header = substr($first, 0, 13 + $gctSize); // header + LSD + GCT

        // NETSCAPE2.0 無限ループ拡張
        $loop = "\x21\xFF\x0BNETSCAPE2.0\x03\x01\x00\x00\x00";

        // GD は GIF87a ヘッダを出力するため GIF89a に書き換え（拡張ブロック使用に必須）
        $header = substr($first, 0, 6) === "GIF87a"
            ? "GIF89a" . substr($header, 6)
            : $header;
        $out = $header . $loop;
        foreach ($this->frames as $i => $frame) {
            if ($i === 0) {
                $body = substr($frame, 13 + $gctSize);
            } else {
                $body = substr($frame, 13); // header + LSD を除去（GCE以降）
            }
            // GCE の遅延を書き換え（GCEは \x21\xF9\x04 packed delayLo delayHi \x00）
            $body = $this->setDelay($body, $this->delay);
            $out .= $body;
        }
        $out .= "\x3B"; // trailer
        return $out;
    }

    private function setDelay(string $body, int $delay): string
    {
        $pos = strpos($body, "\x21\xF9\x04");
        if ($pos === false) return $body;
        $body[$pos + 4] = chr($delay & 0xFF);
        $body[$pos + 5] = chr(($delay >> 8) & 0xFF);
        return $body;
    }

    public function save(string $path): void
    {
        file_put_contents($path, $this->build());
    }
}

// ---------------------------------------------------------------------------
// 描画ヘルパー
// ---------------------------------------------------------------------------
const FONT = __DIR__ . '/../resources/fonts/ipaexg.ttf';

function newFrame(): GdImage
{
    $im = imagecreate(W, H);
    imagecolorallocate($im, 255, 255, 255); // index0 = white
    return $im;
}

function col($im, int $r, int $g, int $b): int
{
    global $gif;
    return $gif->color($im, $r, $g, $b);
}

function txt($im, string $str, int $x, int $y, int $size, int $color, string $align = 'left'): void
{
    $bb = imagettfbbox($size, 0, FONT, $str);
    $w = abs($bb[4] - $bb[0]);
    if ($align === 'center') $x -= intdiv($w, 2);
    if ($align === 'right') $x -= $w;
    imagettftext($im, $size, 0, $x, $y, $color, FONT, $str);
}

function chrome($im, string $title, string $badge = ''): void
{
    global $gif;
    $dark   = col($im, 30, 41, 59);
    $blue   = col($im, 59, 130, 246);
    $gray   = col($im, 229, 231, 235);
    $slate  = col($im, 100, 116, 139);

    // タイトルバー
    imagefilledrectangle($im, 0, 0, W, 34, $dark);
    // ウィンドウボタン風
    imagefilledellipse($im, 18, 17, 12, 12, col($im, 239, 68, 68));
    imagefilledellipse($im, 36, 17, 12, 12, col($im, 245, 158, 11));
    imagefilledellipse($im, 54, 17, 12, 12, col($im, 16, 185, 129));
    txt($im, $title, 74, 23, 12, col($im, 255, 255, 255));
    if ($badge !== '') {
        $bb = imagettfbbox(10, 0, FONT, $badge);
        $bw = abs($bb[4] - $bb[0]) + 16;
        imagefilledroundedrect($im, W - $bw - 10, 8, W - 10, 26, 5, $blue);
        txt($im, $badge, W - 10 - intdiv($bw - 16, 2), 22, 10, col($im, 255, 255, 255), 'center');
    }
    // 下部バー
    imagefilledrectangle($im, 0, H - 22, W, H, $gray);
    txt($im, 'あんしん | 高齢者施設請求管理システム', 10, H - 7, 9, $slate);
}

/** imagefilledroundedrect（GDに無いので自作） */
function imagefilledroundedrect($im, int $x1, int $y1, int $x2, int $y2, int $r, int $color): void
{
    imagefilledrectangle($im, $x1 + $r, $y1, $x2 - $r, $y2, $color);
    imagefilledrectangle($im, $x1, $y1 + $r, $x2, $y2 - $r, $color);
    imagefilledellipse($im, $x1 + $r, $y1 + $r, $r * 2, $r * 2, $color);
    imagefilledellipse($im, $x2 - $r, $y1 + $r, $r * 2, $r * 2, $color);
    imagefilledellipse($im, $x1 + $r, $y2 - $r, $r * 2, $r * 2, $color);
    imagefilledellipse($im, $x2 - $r, $y2 - $r, $r * 2, $r * 2, $color);
}

function progressBar($im, float $ratio): void
{
    global $gif;
    $x1 = 30; $x2 = W - 30; $y = H - 40;
    imagefilledroundedrect($im, $x1, $y, $x2, $y + 10, 5, col($im, 229, 231, 235));
    $w = (int)(($x2 - $x1) * min(1, max(0, $ratio)));
    if ($w > 8) imagefilledroundedrect($im, $x1, $y, $x1 + $w, $y + 10, 5, col($im, 59, 130, 246));
}

function stepBadge($im, int $n, int $total): void
{
    global $gif;
    imagefilledroundedrect($im, 10, 44, 86, 64, 9, col($im, 16, 185, 129));
    txt($im, "手順 {$n}/{$total}", 14, 58, 10, col($im, 255, 255, 255));
}

function formField($im, int $x, int $y, int $w, string $label, string $value, bool $active): void
{
    global $gif;
    $border = $active ? col($im, 59, 130, 246) : col($im, 209, 213, 219);
    $vcol   = $active ? col($im, 20, 20, 20) : col($im, 156, 163, 175);
    txt($im, $label, $x, $y - 4, 10, col($im, 71, 85, 105));
    imagerectangle($im, $x, $y, $x + $w, $y + 24, $border);
    if ($active) imagefilledrectangle($im, $x, $y, $x + $w, $y + 24, col($im, 239, 246, 255));
    imagerectangle($im, $x, $y, $x + $w, $y + 24, $border);
    if ($value !== '') txt($im, $value, $x + 8, $y + 17, 11, $vcol);
}

function listRow($im, int $y, array $cells, bool $highlight, bool $dim = false): void
{
    global $gif;
    $w = W - 40;
    if ($highlight) imagefilledrectangle($im, 20, $y, 20 + $w, $y + 24, col($im, 219, 234, 254));
    elseif ($dim) imagefilledrectangle($im, 20, $y, 20 + $w, $y + 24, col($im, 243, 244, 246));
    $tcol = $dim ? col($im, 156, 163, 175) : col($im, 30, 41, 59);
    $xs = [30, 140, 250, 360];
    foreach ($cells as $i => $c) {
        txt($im, $c, $xs[$i] ?? 30, $y + 17, 10, $tcol);
    }
}

function check($im, int $x, int $y): void
{
    $green = col($im, 16, 185, 129);
    imagesetthickness($im, 3);
    imageline($im, $x, $y + 6, $x + 5, $y + 11, $green);
    imageline($im, $x + 5, $y + 11, $x + 14, $y - 2, $green);
    imagesetthickness($im, 1);
}

function bigCheck($im): void
{
    imagefilledellipse($im, W - 40, 60, 44, 44, col($im, 16, 185, 129));
    check($im, W - 54, 56);
}

// ---------------------------------------------------------------------------
// シナリオ描画関数（各フレーム $t=進行度0..1 を受ける）
// ---------------------------------------------------------------------------
$S = []; // scenarios

// ---- 共通: タイピング風フォーム ----
$S['01-resident-registration'] = function ($t) {
    $im = newFrame();
    chrome($im, '入居者マスタ登録', '入力系');
    $n = (int)floor($t * 5); // 0..4
    formField($im, 30, 80, 410, '氏名', in_array($n, [1,2,3,4]) ? '山田 太郎' : '', $n === 1);
    formField($im, 30, 140, 200, '部屋番号', in_array($n, [2,3,4]) ? '201号室' : '', $n === 2);
    formField($im, 240, 140, 200, '契約開始日', in_array($n, [3,4]) ? '2026-10-01' : '', $n === 3);
    if ($n === 4) {
        imagefilledroundedrect($im, 170, 200, 310, 232, 6, col($im, 16, 185, 129));
        txt($im, '保存しました ✓', 190, 221, 12, col($im, 255, 255, 255));
        bigCheck($im);
        txt($im, '入居者ID: R-025 が発行されました', 120, 280, 11, col($im, 16, 185, 129));
    } else {
        stepBadge($im, $n + 1, 4);
    }
    progressBar($im, ($n + 1) / 5);
    return $im;
};

$S['02-daily-charge-mobile'] = function ($t) {
    $im = newFrame();
    chrome($im, '消耗品費用 スマホ入力', '入力系');
    $n = (int)floor($t * 6); // 0..5
    // スマホ枠
    imagefilledroundedrect($im, 140, 46, 340, 330, 18, col($im, 30, 41, 59));
    imagefilledrectangle($im, 148, 62, 332, 312, col($im, 255, 255, 255));
    txt($im, '費用入力', 240, 84, 12, col($im, 30, 41, 59), 'center');
    formField($im, 158, 100, 164, '項目', $n >= 1 ? '紙おむつ(パンツ)' : '', $n === 1);
    formField($im, 158, 155, 164, '数量', $n >= 2 ? '30' : '', $n === 2);
    formField($im, 158, 210, 164, '日付', $n >= 3 ? '10-15' : '', $n === 3);
    if ($n === 4 || $n === 5) {
        imagefilledroundedrect($im, 158, 255, 322, 280, 6, col($im, 16, 185, 129));
        txt($im, '登録完了 ✓', 240, 272, 11, col($im, 255, 255, 255), 'center');
    } else {
        imagefilledroundedrect($im, 158, 255, 322, 280, 6, col($im, 59, 130, 246));
        txt($im, '登録する', 240, 272, 11, col($im, 255, 255, 255), 'center');
    }
    if ($n === 5) {
        txt($im, '小計 ¥13,500（30個 × ¥450）', 240, 300, 9, col($im, 16, 185, 129), 'center');
        check($im, 160, 290);
    }
    if ($n <= 4) stepBadge($im, $n + 1, 5);
    return $im;
};

$S['03-care-insurance-charge'] = function ($t) {
    $im = newFrame();
    chrome($im, '介護保険費用の入力', '入力系');
    $n = (int)floor($t * 5);
    listRow($im, 70,  ['介護サービス項目', '単価', '数量'], $n === 0);
    listRow($im, 100, ['訪問介護(30分)',  '¥1,920', '1'], $n === 1, $n < 1);
    listRow($im, 128, ['通所介護(1日)',   '¥7,900', '2'], $n === 2 || $n >= 2, $n < 2);
    if ($n >= 3) {
        imagefilledroundedrect($im, 30, 170, 450, 200, 6, col($im, 219, 234, 254));
        txt($im, '給付率 90% → 利用者負担 10%', 40, 190, 11, col($im, 30, 64, 175));
    }
    if ($n === 4) {
        imagefilledroundedrect($im, 150, 220, 330, 252, 6, col($im, 16, 185, 129));
        txt($im, '登録完了 ✓  負担額 ¥1,770', 165, 241, 11, col($im, 255, 255, 255));
        bigCheck($im);
    }
    if ($n < 4) stepBadge($im, $n + 1, 4);
    progressBar($im, ($n + 1) / 5);
    return $im;
};

$S['04-recurring-charge'] = function ($t) {
    $im = newFrame();
    chrome($im, '定期課金の設定', '入力系');
    $n = (int)floor($t * 5);
    formField($im, 30, 80, 410, '課金項目', $n >= 1 ? '居室賃料' : '', $n === 1);
    formField($im, 30, 140, 200, '金額', $n >= 2 ? '¥85,000' : '', $n === 2);
    formField($im, 240, 140, 200, '頻度', $n >= 3 ? '毎月（月末請求）' : '', $n === 3);
    if ($n === 4) {
        imagefilledroundedrect($im, 170, 200, 310, 232, 6, col($im, 16, 185, 129));
        txt($im, '有効化しました ✓', 190, 221, 12, col($im, 255, 255, 255));
        bigCheck($im);
        txt($im, '毎月の請求書へ自動集計されます', 120, 275, 11, col($im, 16, 185, 129));
    } else {
        stepBadge($im, $n + 1, 4);
    }
    progressBar($im, ($n + 1) / 5);
    return $im;
};

$S['05-price-history'] = function ($t) {
    $im = newFrame();
    chrome($im, '料金履歴管理', '入力系');
    $n = (int)floor($t * 4);
    listRow($im, 70, ['料金項目', '価格', '適用開始'], true);
    listRow($im, 100, ['紙おむつ(パンツ)', '¥450', '2024-04-01'], true, $n >= 1);
    if ($n >= 1) listRow($im, 128, ['紙おむつ(パンツ)', '¥480', '2026-10-01'], $n === 1);
    if ($n >= 2) {
        txt($im, '↑ 旧価格は自動で履歴化', 250, 118, 9, col($im, 239, 68, 68));
    }
    if ($n >= 3) {
        imagefilledroundedrect($im, 30, 170, 450, 200, 6, col($im, 220, 252, 231));
        txt($im, '過去請求書は旧価格のまま保持されます', 40, 190, 11, col($im, 21, 128, 61));
        bigCheck($im);
    }
    if ($n < 3) stepBadge($im, $n + 1, 3);
    return $im;
};

$S['06-csv-import'] = function ($t) {
    $im = newFrame();
    chrome($im, 'CSV一括取り込み', '入力系');
    $n = (int)floor($t * 4);
    $labels = ['CSVファイル選択', 'プレビュー確認', 'データ検証', '取り込み完了'];
    // フロー図
    $boxW = 92; $gap = 24; $x = 30; $y = 80;
    foreach ($labels as $i => $label) {
        $active = $i <= $n;
        $fill = $i === $n ? col($im, 59, 130, 246) : ($active ? col($im, 16, 185, 129) : col($im, 229, 231, 235));
        imagefilledroundedrect($im, $x + $i * ($boxW + $gap), $y, $x + $i * ($boxW + $gap) + $boxW, $y + 56, 8, $fill);
        $tc = $active ? col($im, 255, 255, 255) : col($im, 107, 114, 128);
        txt($im, (string)($i + 1), $x + $i * ($boxW + $gap) + $boxW / 2, $y + 26, 12, $tc, 'center');
        txt($im, mb_substr($label, 0, 7), $x + $i * ($boxW + $gap) + $boxW / 2, $y + 46, 8, $tc, 'center');
        if ($i < 3) txt($im, '→', $x + $i * ($boxW + $gap) + $boxW + 2, $y + 36, 16, col($im, 107, 114, 128));
    }
    if ($n >= 1) {
        imagefilledroundedrect($im, 30, 170, 450, 240, 8, col($im, 243, 244, 246));
        listRow($im, 180, ['氏名', '部屋', '金額'], false);
        listRow($im, 204, ['山田太郎', '201', '¥13,500'], $n === 1);
        listRow($im, 228, ['鈴木花子', '102', '¥7,900'], $n === 1);
    }
    if ($n === 3) {
        txt($im, '42件の自費記録を取り込みました ✓', 90, 285, 13, col($im, 16, 185, 129));
        bigCheck($im);
    }
    progressBar($im, ($n + 1) / 4);
    return $im;
};

$S['07-bulk-invoice-generation'] = function ($t) {
    $im = newFrame();
    chrome($im, '月次請求書 一括生成', '請求計算');
    $n = (int)floor($t * 8); // 0..7
    if ($n === 0) {
        imagefilledroundedrect($im, 140, 120, 340, 160, 8, col($im, 59, 130, 246));
        txt($im, '2026年10月分を生成', 240, 145, 12, col($im, 255, 255, 255), 'center');
    } else {
        $done = min(25, $n * 4);
        imagefilledroundedrect($im, 30, 90, 450, 230, 8, col($im, 243, 244, 246));
        for ($i = 0; $i < min($done, 25); $i++) {
            $row = intdiv($i, 5); $c = $i % 5;
            $x = 45 + $c * 82; $y = 105 + $row * 26;
            imagefilledroundedrect($im, $x, $y, $x + 72, $y + 20, 4, col($im, 16, 185, 129));
            txt($im, 'R-' . str_pad((string)($i + 1), 3, '0', STR_PAD_LEFT), $x + 8, $y + 14, 8, col($im, 255, 255, 255));
        }
        txt($im, "請求書作成中... {$done}/25件", 240, 60, 13, col($im, 59, 130, 246), 'center');
        progressBar($im, $done / 25);
        if ($done >= 25) {
            imagefilledroundedrect($im, 150, 250, 330, 285, 8, col($im, 16, 185, 129));
            txt($im, '25件 完了 ✓ 税込合計 ¥3,056,750', 165, 272, 11, col($im, 255, 255, 255));
        }
    }
    return $im;
};

$S['08-tax-calculation'] = function ($t) {
    $im = newFrame();
    chrome($im, '税率の自動判別', '請求計算');
    $n = (int)floor($t * 4);
    listRow($im, 70, ['項目', '金額', '税率'], true);
    listRow($im, 100, ['家賃', '¥85,000', '非課税'], true, $n < 1);
    listRow($im, 128, ['管理費', '¥18,000', '10%'], $n >= 1, $n < 1);
    listRow($im, 156, ['食費', '¥46,200', '8%'], $n >= 2, $n < 2);
    if ($n >= 1) txt($im, '課税対象外', 380, 114, 8, col($im, 107, 114, 128));
    if ($n >= 3) {
        imagefilledroundedrect($im, 30, 195, 450, 260, 8, col($im, 219, 234, 254));
        txt($im, '消費税額: ¥7,050（内軽減 8%: ¥3,696）', 45, 222, 11, col($im, 30, 64, 175));
        txt($im, '税込合計: ¥166,750', 45, 246, 12, col($im, 30, 64, 175));
        check($im, 420, 230);
    }
    if ($n < 3) stepBadge($im, $n + 1, 3);
    return $im;
};

$S['09-proration'] = function ($t) {
    $im = newFrame();
    chrome($im, '月途中入居の日割り計算', '請求計算');
    $n = (int)floor($t * 4);
    // カレンダー（10月）
    imagefilledroundedrect($im, 30, 60, 250, 250, 8, col($im, 243, 244, 246));
    txt($im, '2026年10月', 140, 80, 11, col($im, 30, 41, 59), 'center');
    for ($d = 1; $d <= 31; $d++) {
        $row = intdiv($d - 1, 7); $c = ($d - 1) % 7;
        $x = 45 + $c * 28; $y = 95 + $row * 22;
        $hl = ($n >= 1 && $d === 20);
        $fill = $hl ? col($im, 239, 68, 68) : ($n >= 2 && $d >= 20 ? col($im, 254, 215, 170) : col($im, 229, 231, 235));
        imagefilledellipse($im, $x, $y, 20, 18, $fill);
        txt($im, (string)$d, $x, $y + 4, 8, $hl || ($n >= 2 && $d >= 20) ? col($im, 255, 255, 255) : col($im, 71, 85, 105), 'center');
    }
    if ($n >= 1) txt($im, '← 10/20 入居', 260, 200, 10, col($im, 239, 68, 68));
    if ($n >= 2) {
        imagefilledroundedrect($im, 260, 90, 450, 130, 8, col($im, 219, 234, 254));
        txt($im, '請求日数: 12/31日', 275, 115, 12, col($im, 30, 64, 175));
    }
    if ($n >= 3) {
        imagefilledroundedrect($im, 260, 150, 450, 200, 8, col($im, 220, 252, 231));
        txt($im, '日割り家賃', 275, 172, 10, col($im, 21, 128, 61));
        txt($im, '¥32,903', 275, 194, 14, col($im, 21, 128, 61));
        check($im, 425, 180);
    }
    if ($n < 3) stepBadge($im, $n + 1, 3);
    return $im;
};

$S['10-facility-filter'] = function ($t) {
    $im = newFrame();
    chrome($im, '施設別フィルタ', '請求計算');
    $n = (int)floor($t * 4);
    // タブ
    $tabs = ['全施設(25)', 'ひまわり(25)', '第二寮(0)'];
    foreach ($tabs as $i => $tab) {
        $active = ($n === 0 && $i === 0) || ($n >= 1 && $i === 1);
        $fill = $active ? col($im, 59, 130, 246) : col($im, 229, 231, 235);
        imagefilledroundedrect($im, 20 + $i * 140, 50, 150 + $i * 140, 74, 6, $fill);
        txt($im, $tab, 85 + $i * 140, 67, 10, $active ? col($im, 255, 255, 255) : col($im, 71, 85, 105), 'center');
    }
    listRow($im, 95, ['入居者', '請求額', '状態'], true);
    $rows = [
        ['山田太郎', '¥166,750', '確定'],
        ['鈴木花子', '¥152,300', '確定'],
        ['佐藤一郎', '¥141,800', '下書き'],
    ];
    foreach ($rows as $i => $r) {
        listRow($im, 123 + $i * 28, $r, $n === 2 && $i === 1, $n === 1 && $i === 2 && false);
    }
    if ($n >= 2) {
        imagefilledroundedrect($im, 30, 240, 450, 280, 8, col($im, 220, 252, 231));
        txt($im, 'ひまわりケア施設: 25件 / 合計 ¥3,056,750', 45, 265, 11, col($im, 21, 128, 61));
    }
    if ($n < 3) stepBadge($im, min($n + 1, 3), 3);
    return $im;
};

$S['11-invoice-recalculate'] = function ($t) {
    $im = newFrame();
    chrome($im, '請求書の再計算', '請求計算');
    $n = (int)floor($t * 4);
    imagefilledroundedrect($im, 30, 55, 450, 220, 8, col($im, 243, 244, 246));
    listRow($im, 65, ['明細', '金額'], true);
    listRow($im, 93, ['居室賃料', '¥85,000'], true);
    listRow($im, 121, ['管理費(税込)', '¥19,800'], true);
    if ($n >= 1) listRow($im, 149, ['紙おむつ 30個', '¥13,500'], $n === 1);
    if ($n >= 2) {
        imagefilledroundedrect($im, 250, 180, 440, 210, 6, col($im, 59, 130, 246));
        txt($im, '再計算を実行', 260, 200, 11, col($im, 255, 255, 255));
    }
    if ($n >= 3) {
        imagefilledroundedrect($im, 30, 235, 450, 285, 8, col($im, 220, 252, 231));
        txt($im, '合計 ¥118,300 → ¥131,800 に更新 ✓', 45, 260, 12, col($im, 21, 128, 61));
        bigCheck($im);
    }
    if ($n < 3) stepBadge($im, $n + 1, 3);
    return $im;
};

$S['12-unpaid-alert'] = function ($t) {
    $im = newFrame();
    chrome($im, '未収金アラート', '請求計算');
    $n = (int)floor($t * 4);
    // ウィジェット
    $flash = ($n % 2 === 1);
    $fill = $flash ? col($im, 254, 226, 226) : col($im, 254, 240, 240);
    imagefilledroundedrect($im, 30, 60, 450, 130, 8, $fill);
    imagefilledellipse($im, 60, 95, 40, 40, col($im, 239, 68, 68));
    txt($im, '!', 60, 105, 20, col($im, 255, 255, 255), 'center');
    txt($im, '未収請求書: 3件', 95, 90, 14, col($im, 153, 27, 27));
    txt($im, '合計 ¥460,850 が延滞中', 95, 115, 11, col($im, 153, 27, 27));
    listRow($im, 155, ['入居者', '金額', '延滞日数'], true);
    listRow($im, 183, ['田中美智子', '¥152,300', '12日'], $n >= 2, $n < 2);
    if ($n >= 2) listRow($im, 211, ['高橋健一', '¥166,750', '5日'], $n >= 2, $n < 2);
    if ($n >= 3) {
        imagefilledroundedrect($im, 330, 245, 450, 275, 6, col($im, 59, 130, 246));
        txt($im, '督促メール送信', 340, 264, 10, col($im, 255, 255, 255));
        check($im, 310, 255);
    }
    if ($n < 3) stepBadge($im, $n + 1, 3);
    return $im;
};

$S['13-invoice-pdf'] = function ($t) {
    $im = newFrame();
    chrome($im, '個別請求書PDF出力', 'PDF出力');
    $n = (int)floor($t * 4);
    if ($n === 0) {
        listRow($im, 70, ['入居者', '金額', '操作'], true);
        listRow($im, 100, ['山田太郎', '¥166,750', '[PDF]'], true);
        txt($im, 'PDFボタンをクリック →', 200, 114, 10, col($im, 59, 130, 246));
    } elseif ($n === 1) {
        txt($im, 'レンダリング中...', 240, 150, 13, col($im, 59, 130, 246), 'center');
        for ($i = 0; $i < 10; $i++) imagefilledrectangle($im, 140 + $i * 20, 180, 156 + $i * 20, 196, col($im, 219, 234, 254));
        imagefilledrectangle($im, 140, 180, 160 + ($t * 200 % 200), 196, col($im, 59, 130, 246));
        progressBar($im, ($t * 4 - 1));
    } else {
        // PDFプレビュー風
        imagefilledrectangle($im, 90, 55, 390, 330, col($im, 255, 255, 255));
        imagerectangle($im, 90, 55, 390, 330, col($im, 209, 213, 219));
        txt($im, '請 求 書', 240, 90, 16, col($im, 30, 41, 59), 'center');
        txt($im, 'ひまわりケア施設', 110, 120, 9, col($im, 71, 85, 105));
        txt($im, '山田太郎 様', 110, 145, 11, col($im, 30, 41, 59));
        imageline($im, 110, 160, 370, 160, col($im, 209, 213, 219));
        txt($im, '居室賃料 ......... ¥85,000', 110, 185, 9, col($im, 51, 65, 85));
        txt($im, '管理費 ........... ¥19,800', 110, 205, 9, col($im, 51, 65, 85));
        txt($im, '紙おむつ ......... ¥13,500', 110, 225, 9, col($im, 51, 65, 85));
        txt($im, '合計(税込) ....... ¥118,300', 110, 260, 11, col($im, 30, 41, 59));
        if ($n === 3) { txt($im, '8.3KB PDF生成完了 ✓', 300, 300, 9, col($im, 16, 185, 129)); check($im, 360, 290); }
    }
    if ($n < 3) stepBadge($im, $n + 1, 3);
    return $im;
};

$S['14-receipt-pdf'] = function ($t) {
    $im = newFrame();
    chrome($im, '領収書PDF出力', 'PDF出力');
    $n = (int)floor($t * 4);
    if ($n === 0) {
        imagefilledroundedrect($im, 140, 120, 340, 160, 8, col($im, 16, 185, 129));
        txt($im, '領収書を発行', 240, 145, 13, col($im, 255, 255, 255), 'center');
    } elseif ($n === 1) {
        txt($im, '印刷準備中...', 240, 150, 13, col($im, 16, 185, 129), 'center');
        imagefilledrectangle($im, 180, 190, 300, 196, col($im, 229, 231, 235));
        imagefilledrectangle($im, 180, 190, 180 + (int)(($t * 4) % 1 * 120) + 30, 196, col($im, 16, 185, 129));
    } else {
        imagefilledrectangle($im, 90, 55, 390, 330, col($im, 255, 255, 204));
        imagerectangle($im, 90, 55, 390, 330, col($im, 202, 178, 90));
        txt($im, '領 収 書', 240, 95, 18, col($im, 30, 41, 59), 'center');
        txt($im, '¥118,300', 240, 140, 18, col($im, 30, 41, 59), 'center');
        txt($im, '上記正に領収いたしました', 240, 175, 10, col($im, 71, 85, 105), 'center');
        txt($im, '山田太郎 様', 110, 215, 11, col($im, 30, 41, 59));
        txt($im, 'ひまわりケア施設', 110, 250, 10, col($im, 71, 85, 105));
        // 印影
        imagefilledellipse($im, 330, 280, 60, 60, col($im, 255, 204, 204));
        txt($im, '収印', 330, 288, 14, col($im, 153, 27, 27), 'center');
        if ($n === 3) check($im, 360, 70);
    }
    if ($n < 3) stepBadge($im, $n + 1, 3);
    return $im;
};

$S['15-bulk-zip'] = function ($t) {
    $im = newFrame();
    chrome($im, '一括ZIPダウンロード', 'PDF出力');
    $n = (int)floor($t * 6); // 0..5
    if ($n === 0) {
        imagefilledroundedrect($im, 140, 130, 340, 170, 8, col($im, 59, 130, 246));
        txt($im, '2026-10 全25件 ZIP化', 240, 155, 12, col($im, 255, 255, 255), 'center');
    } else {
        $done = min(50, ($n - 1) * 12 + 10);
        txt($im, "PDF生成中... {$done}/50枚（請求書+領収書）", 240, 80, 12, col($im, 59, 130, 246), 'center');
        progressBar($im, $done / 50);
        for ($i = 0; $i < min(10, intdiv($done, 5)); $i++) {
            $x = 45 + ($i % 5) * 82; $y = 120 + intdiv($i, 5) * 50;
            imagefilledrectangle($im, $x, $y, $x + 60, $y + 40, col($im, 255, 255, 255));
            imagerectangle($im, $x, $y, $x + 60, $y + 40, col($im, 209, 213, 219));
            txt($im, 'PDF', $x + 30, $y + 26, 10, col($im, 239, 68, 68), 'center');
        }
        if ($done >= 50) {
            imagefilledroundedrect($im, 120, 240, 360, 290, 10, col($im, 30, 41, 59));
            txt($im, 'invoices-2026-10.zip (15.3KB)', 240, 262, 11, col($im, 255, 255, 204), 'center');
            txt($im, 'ダウンロード準備完了 ✓', 240, 280, 10, col($im, 16, 185, 129), 'center');
        }
    }
    return $im;
};

$S['16-category-zip'] = function ($t) {
    $im = newFrame();
    chrome($im, 'カテゴリ別ZIP出力', 'PDF出力');
    $n = (int)floor($t * 4);
    $cats = ['居室関連', 'サービス', '消耗品'];
    foreach ($cats as $i => $cat) {
        $on = $n >= 1;
        imagefilledrectangle($im, 40, 80 + $i * 34, 56, 96 + $i * 34, $on ? col($im, 59, 130, 246) : col($im, 255, 255, 255));
        imagerectangle($im, 40, 80 + $i * 34, 56, 96 + $i * 34, col($im, 107, 114, 128));
        if ($on) check($im, 43, 82 + $i * 34);
        txt($im, $cat, 70, 94 + $i * 34, 11, col($im, 30, 41, 59));
    }
    if ($n >= 2) {
        txt($im, '↓ カテゴリごとに分割', 300, 100, 11, col($im, 59, 130, 246));
        foreach ($cats as $i => $cat) {
            $x = 280; $y = 120 + $i * 50;
            imagefilledroundedrect($im, $x, $y, $x + 170, $y + 36, 6, col($im, 30, 41, 59));
            txt($im, $cat . '.zip', $x + 85, $y + 23, 10, col($im, 255, 255, 204), 'center');
        }
    }
    if ($n === 3) {
        txt($im, 'CSVサマリー同梱 ✓', 300, 290, 10, col($im, 16, 185, 129));
        check($im, 285, 280);
    }
    if ($n < 3) stepBadge($im, $n + 1, 3);
    return $im;
};

$S['17-password-pdf'] = function ($t) {
    $im = newFrame();
    chrome($im, 'パスワード保護PDF', 'PDF出力');
    $n = (int)floor($t * 4);
    formField($im, 30, 80, 410, 'パスワード方式', $n >= 1 ? '生年月日（YYYYMMDD）' : '', $n === 1);
    formField($im, 30, 140, 410, '例', $n >= 2 ? '1940-01-15 → 19400115' : '', $n === 2);
    if ($n >= 2) {
        // 鍵アイコン
        imagefilledellipse($im, 70, 215, 36, 36, col($im, 245, 158, 11));
        imagefilledrectangle($im, 62, 230, 78, 258, col($im, 245, 158, 11));
        txt($im, 'PDFを開くには生年月日が必要です', 110, 215, 11, col($im, 71, 85, 105));
    }
    if ($n === 3) {
        imagefilledroundedrect($im, 110, 265, 370, 300, 8, col($im, 220, 252, 231));
        txt($im, '保護付きPDFを生成しました ✓', 130, 288, 12, col($im, 21, 128, 61));
        check($im, 100, 275);
    }
    if ($n < 3) stepBadge($im, $n + 1, 3);
    return $im;
};

$S['18-bank-qr'] = function ($t) {
    $im = newFrame();
    chrome($im, '振込QRコード生成', 'PDF出力');
    $n = (int)floor($t * 4);
    // QR風ドット（決定論的に生成）
    $qrX = 150; $qrY = 70; $cell = 12;
    mt_srand(20261001);
    $dots = [];
    for ($r = 0; $r < 15; $r++) for ($c = 0; $c < 15; $c++) $dots[$r][$c] = mt_rand(0, 1);
    mt_srand(20261001);
    // コーナー検出器
    $finder = [[1,1,1,1,1,1,1],[1,0,0,0,0,0,1],[1,0,1,1,1,0,1],[1,0,1,1,1,0,1],[1,0,1,1,1,0,1],[1,0,0,0,0,0,1],[1,1,1,1,1,1,1]];
    $shown = ($n === 0) ? 0 : min(225, $n * 70);
    $drawn = 0;
    for ($r = 0; $r < 15; $r++) for ($c = 0; $c < 15; $c++) {
        $black = false;
        if ($r < 7 && $c < 7) $black = $finder[$r][$c] === 1;
        elseif ($r < 7 && $c >= 8) $black = $finder[$r][$c - 8] === 1;
        elseif ($r >= 8 && $c < 7) $black = $finder[$r - 8][$c] === 1;
        else $black = $dots[$r][$c] === 1;
        if ($drawn++ < $shown && $black) {
            imagefilledrectangle($im, $qrX + $c * $cell, $qrY + $r * $cell, $qrX + $c * $cell + $cell - 2, $qrY + $r * $cell + $cell - 2, col($im, 20, 20, 20));
        }
    }
    imagerectangle($im, $qrX - 4, $qrY - 4, $qrX + 15 * $cell + 2, $qrY + 15 * $cell + 2, col($im, 209, 213, 219));
    if ($n === 0) txt($im, 'QRコード生成中...', 240, 330, 11, col($im, 59, 130, 246), 'center');
    if ($n >= 1) txt($im, 'みずほ銀行 渋谷支店', 300, 90, 10, col($im, 51, 65, 85));
    if ($n >= 2) txt($im, '口座 1234567 普通', 300, 110, 10, col($im, 51, 65, 85));
    if ($n >= 3) { txt($im, '¥118,300 / 山田太郎', 300, 130, 10, col($im, 16, 185, 129)); check($im, 430, 120); }
    return $im;
};

$S['19-dashboard'] = function ($t) {
    $im = newFrame();
    chrome($im, 'ダッシュボード', '管理系');
    $n = (int)floor($t * 4);
    $cards = [
        ['今月の請求書', '25件', 30, 60],
        ['税込合計', '¥3,056,750', 250, 60],
        ['自費記録', '42件', 30, 160],
        ['未収', '3件', 250, 160],
    ];
    foreach ($cards as $i => $c) {
        if ($n >= $i) {
            imagefilledroundedrect($im, $c[2], $c[3], $c[2] + 200, $c[3] + 80, 10, col($im, 243, 244, 246));
            imagerectangle($im, $c[2], $c[3], $c[2] + 200, $c[3] + 80, col($im, 229, 231, 235));
            txt($im, $c[0], $c[2] + 15, $c[3] + 30, 10, col($im, 100, 116, 139));
            $accent = $i === 3 ? col($im, 239, 68, 68) : col($im, 59, 130, 246);
            txt($im, $c[1], $c[2] + 15, $c[3] + 62, 16, $accent);
        }
    }
    if ($n >= 4) {
        imagefilledroundedrect($im, 30, 260, 450, 300, 8, col($im, 219, 234, 254));
        txt($im, '2026年10月度: 生成 → PDF → CSV 連携までワンストップ', 45, 285, 11, col($im, 30, 64, 175));
    }
    if ($n < 4) stepBadge($im, min($n + 1, 4), 4);
    return $im;
};

$S['20-accounting-csv'] = function ($t) {
    $im = newFrame();
    chrome($im, '会計ソフト連携CSV', '管理系');
    $n = (int)floor($t * 4);
    imagefilledroundedrect($im, 30, 60, 210, 240, 8, col($im, 243, 244, 246));
    txt($im, '出力設定', 45, 82, 11, col($im, 30, 41, 59));
    foreach (['freee', 'マネーフォワード', '弥生会計'] as $i => $s) {
        $on = $n >= 1 && $i === 0;
        imagefilledrectangle($im, 45, 100 + $i * 30, 57, 112 + $i * 30, $on ? col($im, 59, 130, 246) : col($im, 255, 255, 255));
        imagerectangle($im, 45, 100 + $i * 30, 57, 112 + $i * 30, col($im, 107, 114, 128));
        if ($on) check($im, 47, 102 + $i * 30);
        txt($im, $s, 68, 112 + $i * 30, 10, col($im, 51, 65, 85));
    }
    if ($n >= 2) {
        imagefilledroundedrect($im, 240, 60, 450, 150, 8, col($im, 255, 255, 255));
        imagerectangle($im, 240, 60, 450, 150, col($im, 209, 213, 219));
        txt($im, '仕訳プレビュー', 255, 80, 10, col($im, 30, 41, 59));
        txt($im, '売掛金   118,300', 255, 105, 9, col($im, 51, 65, 85));
        txt($im, '売上高   107,550', 255, 125, 9, col($im, 51, 65, 85));
        txt($im, '仮受消費税 10,750', 255, 145 - 22, 9, col($im, 51, 65, 85));
    }
    if ($n === 3) {
        imagefilledroundedrect($im, 240, 180, 450, 240, 8, col($im, 30, 41, 59));
        txt($im, 'invoices-2026-10.csv', 345, 207, 11, col($im, 204, 255, 204), 'center');
        txt($im, 'ダウンロード完了 ✓', 345, 228, 10, col($im, 16, 185, 129), 'center');
    }
    if ($n < 3) stepBadge($im, $n + 1, 3);
    return $im;
};

$S['21-invoice-merge'] = function ($t) {
    $im = newFrame();
    chrome($im, '同居家族 請求統合', '管理系');
    $n = (int)floor($t * 4);
    // 2つの請求書
    imagefilledrectangle($im, 40, 70, 220, 220, col($im, 255, 255, 255));
    imagerectangle($im, 40, 70, 220, 220, col($im, 209, 213, 219));
    txt($im, '請求書 R-001', 130, 95, 10, col($im, 30, 41, 59), 'center');
    txt($im, '山田太郎', 130, 120, 10, col($im, 51, 65, 85), 'center');
    txt($im, '¥100,000', 130, 150, 12, col($im, 59, 130, 246), 'center');
    imagefilledrectangle($im, 260, 70, 440, 220, col($im, 255, 255, 255));
    imagerectangle($im, 260, 70, 440, 220, col($im, 209, 213, 219));
    txt($im, '請求書 R-002', 350, 95, 10, col($im, 30, 41, 59), 'center');
    txt($im, '山田花子', 350, 120, 10, col($im, 51, 65, 85), 'center');
    txt($im, '¥150,000', 350, 150, 12, col($im, 59, 130, 246), 'center');
    if ($n >= 1) {
        txt($im, '→ 統合', 240, 170, 12, col($im, 245, 158, 11), 'center');
        imagefilledroundedrect($im, 205, 180, 275, 210, 6, col($im, 245, 158, 11));
        txt($im, '統合', 240, 200, 11, col($im, 255, 255, 255), 'center');
    }
    if ($n >= 2) {
        imagefilledrectangle($im, 140, 240, 340, 330, col($im, 255, 255, 204));
        imagerectangle($im, 140, 240, 340, 330, col($im, 202, 178, 90));
        txt($im, '統合請求書', 240, 262, 11, col($im, 30, 41, 59), 'center');
        txt($im, '世帯合計 ¥250,000', 240, 288, 12, col($im, 30, 41, 59), 'center');
    }
    if ($n === 3) { txt($im, '内訳 2名分を保持 ✓', 240, 315, 10, col($im, 21, 128, 61), 'center'); check($im, 145, 305); }
    if ($n < 3) stepBadge($im, $n + 1, 3);
    return $im;
};

$S['22-chatbot'] = function ($t) {
    $im = newFrame();
    chrome($im, 'チャットボット照会', '管理系');
    $n = (int)floor($t * 5);
    imagefilledroundedrect($im, 30, 55, 450, 320, 12, col($im, 243, 244, 246));
    // ユーザーメッセージ
    if ($n >= 1) {
        imagefilledroundedrect($im, 200, 75, 440, 110, 10, col($im, 59, 130, 246));
        txt($im, '山田さんの10月の請求額は？', 215, 98, 10, col($im, 255, 255, 255));
    }
    if ($n === 2) {
        imagefilledroundedrect($im, 45, 130, 130, 160, 10, col($im, 229, 231, 235));
        txt($im, '検索中...', 60, 150, 10, col($im, 107, 114, 128));
    }
    if ($n >= 3) {
        imagefilledroundedrect($im, 45, 130, 430, 210, 10, col($im, 255, 255, 255));
        imagerectangle($im, 45, 130, 430, 210, col($im, 229, 231, 235));
        txt($im, '山田太郎 様（201号室）2026年10月', 60, 155, 10, col($im, 30, 41, 59));
        txt($im, '税込合計: ¥166,750（確定）', 60, 180, 11, col($im, 59, 130, 246));
        txt($im, '明細: 賃料/管理費/おむつ など 5件', 60, 200, 9, col($im, 100, 116, 139));
    }
    if ($n >= 4) {
        imagefilledroundedrect($im, 45, 230, 430, 285, 10, col($im, 220, 252, 231));
        txt($im, '[請求書を開く] [PDF出力] クイック返信', 60, 255, 10, col($im, 21, 128, 61));
    }
    if ($n < 4) stepBadge($im, min($n + 1, 4), 4);
    return $im;
};

$S['23-multi-facility'] = function ($t) {
    $im = newFrame();
    chrome($im, '複数施設の管理', '管理系');
    $n = (int)floor($t * 4);
    $facilities = [['ひまわりケア施設', '25名', '¥3,056,750'], ['第二ひまわり', '18名', '¥2,210,400'], ['はなみずき', '22名', '¥2,680,150']];
    listRow($im, 65, ['施設名', '入居者', '月次合計'], true);
    foreach ($facilities as $i => $f) {
        listRow($im, 95 + $i * 32, $f, $n === 2 && $i === 0, $n >= 2 && $i !== 0);
    }
    if ($n >= 1) txt($im, '施設を切替...', 380, 80, 9, col($im, 59, 130, 246));
    if ($n >= 3) {
        imagefilledroundedrect($im, 30, 230, 450, 280, 8, col($im, 219, 234, 254));
        txt($im, '3施設 合計: 65名 / ¥7,947,300', 45, 260, 12, col($im, 30, 64, 175));
        check($im, 425, 250);
    }
    if ($n < 3) stepBadge($im, min($n + 1, 3), 3);
    return $im;
};

$S['24-template-settings'] = function ($t) {
    $im = newFrame();
    chrome($im, 'PDFテンプレート設定', '管理系');
    $n = (int)floor($t * 4);
    formField($im, 30, 80, 410, 'ロゴ画像', $n >= 1 ? 'himawari-logo.png' : '', $n === 1);
    formField($im, 30, 140, 200, 'アクセント色', $n >= 2 ? '#3B82F6' : '', $n === 2);
    formField($im, 240, 140, 200, '印影', $n >= 3 ? '表示する' : '', $n === 3);
    // プレビュー
    imagefilledrectangle($im, 30, 190, 450, 310, col($im, 255, 255, 255));
    imagerectangle($im, 30, 190, 450, 310, col($im, 209, 213, 219));
    txt($im, 'プレビュー', 240, 212, 10, col($im, 100, 116, 139), 'center');
    imagefilledrectangle($im, 45, 225, 105, 255, $n >= 2 ? col($im, 59, 130, 246) : col($im, 229, 231, 235));
    txt($im, '請求書', 130, 245, 12, col($im, 30, 41, 59));
    if ($n >= 3) {
        imagefilledellipse($im, 410, 240, 40, 40, col($im, 255, 204, 204));
        txt($im, '印', 410, 246, 12, col($im, 153, 27, 27), 'center');
    }
    if ($n >= 3) { txt($im, '設定を保存 ✓', 240, 335 - 12, 10, col($im, 16, 185, 129), 'center'); }
    if ($n < 3) stepBadge($im, min($n + 1, 3), 3);
    return $im;
};

// ---------------------------------------------------------------------------
// メイン: 全シナリオをレンダリングしてGIF書き出し
// ---------------------------------------------------------------------------
$outDir = __DIR__ . '/../website/assets/gifs';
if (!is_dir($outDir)) mkdir($outDir, 0777, true);

$global = new GifBuilder();
// グローバル $gif は col() で使う（パレット共通のため1インスタンスを共有）
$GLOBALS['gif'] = $global;

$ok = 0; $fail = 0;
foreach ($S as $name => $fn) {
    try {
        $gif = new GifBuilder();
        $GLOBALS['gif'] = $gif;
        $FRAMES = 10;
        for ($f = 0; $f < $FRAMES; $f++) {
            $t = $f / ($FRAMES - 1);
            $im = $fn($t);
            // 最終フレームを2回入れて末尾で一瞬停止
            $gif->addFrame($im);
            imagedestroy($im);
        }
        // 最終フレームを繰り返して停止感を出す
        $im = $fn(1.0);
        $gif->addFrame($im);
        imagedestroy($im);

        $path = $outDir . '/gif-' . $name . '.gif';
        $gif->save($path);
        $kb = round(filesize($path) / 1024, 1);
        echo "OK  gif-{$name}.gif ({$gif->count()} frames, {$kb}KB)\n";
        $ok++;
    } catch (Throwable $e) {
        echo "ERR gif-{$name}: {$e->getMessage()}\n";
        $fail++;
    }
}

echo "\n===== 結果: 成功 {$ok} / 失敗 {$fail} =====\n";
exit($fail > 0 ? 1 : 0);
