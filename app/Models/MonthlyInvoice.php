<?php

namespace App\Models;

use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Config;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class MonthlyInvoice extends Model
{
    use HasFactory, LogsActivity;

    protected $guarded = ['id'];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([
                'resident_id',
                'facility_id',
                'billing_year_month',
                'rent_subtotal',
                'management_fee_subtotal',
                'service_subtotal',
                'total_amount',
                'status',
                'paid_at',
                'payment_method',
                'receipt_number',
                'receipt_issued_at',
                'version',
                'taxable_amount',
                'tax_amount',
                'tax_rate',
                'tax_breakdown',
            ])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->setDescriptionForEvent(fn (string $eventName) => "MonthlyInvoice {$eventName}")
            ->useLogName('monthly_invoice');
    }

    protected $attributes = [
        'version' => 0,
    ];

    protected $casts = [
        'rent_subtotal' => 'integer',
        'management_fee_subtotal' => 'integer',
        'service_subtotal' => 'integer',
        'total_amount' => 'integer',
        'total_with_tax' => 'integer',
        'status' => InvoiceStatus::class,
        'paid_at' => 'date',
        'payment_method' => PaymentMethod::class,
        'receipt_issued_at' => 'datetime',
        'taxable_amount' => 'integer',
        'tax_amount' => 'integer',
        'tax_rate' => 'decimal:2',
        'tax_breakdown' => 'array',
        'version' => 'integer',
        'invoice_date_mode' => 'string',
        'custom_invoice_date' => 'date',
        'receipt_date_mode' => 'string',
        'custom_receipt_date' => 'date',
    ];

    protected $appends = [
        'non_taxable_amount',
        'taxable_amount',
        'tax_amount',
        'total_with_tax',
    ];
    /**
     * 締め済み（closed）の月に変更を許可する入金・領収書系フィールド
     *
     * 締め後も入金消込（status=paid, paid_at, payment_method, 領収書番号）は
     * 必要な操作であるため許可し、それ以外の請求金額・按分・年月の変更は禁止する。
     */
    private const CLOSED_MONTH_ALLOWED_FIELDS = [
        'status',
        'paid_at',
        'payment_method',
        'receipt_number',
        'receipt_issued_at',
        'receipt_date_mode',
        'custom_receipt_date',
        'version',
        'updated_at',
    ];

    /**
     * モデル起動時のイベント設定
     * 計算ロジックは InvoiceCalculationService に委譲。
     * ここでは合計金額の再計算と楽観ロックのバージョン管理のみ行う。
     */
    protected static function booted(): void
    {
        static::saving(function (MonthlyInvoice $invoice) {
            // 締め済み月のロック（金額・年月・新規作成を禁止、入金消込のみ許可）
            $invoice->guardAgainstClosedMonth();

            // 合計金額の計算（税抜き小計）
            $invoice->total_amount = (int) $invoice->rent_subtotal
                + (int) $invoice->management_fee_subtotal
                + (int) $invoice->service_subtotal;

            // 税込み合計（SQL集計・CSV出力用に永続化。表示はアクセッサで再計算）
            // $invoice->tax_amount はアクセッサ経由（tax_breakdown → 保存値 → floor算出）
            $invoice->total_with_tax = $invoice->total_amount + (int) $invoice->tax_amount;

            // 楽観ロック: 更新時のみバージョンをインクリメント
            if ($invoice->exists && $invoice->isDirty()) {
                $invoice->version = ($invoice->version ?? 0) + 1;
            }
        });

        static::deleting(function (MonthlyInvoice $invoice) {
            // 締め済み月の請求書は削除禁止（削除時は dirty が空のため入金消込許可パスを使わない）
            $invoice->guardAgainstClosedMonth(true);
        });
    }

    /**
     * 月次締め（closed）ガード: 締め済みの月の請求データは変更・削除・新規作成できない
     *
     * 差戻しが必要な場合は MonthlyClosingWizard から reopen すること。
     *
     * @param  bool  $isDelete  削除時（入金消込の許可パスを無効化する）
     *
     * @throws \RuntimeException
     */
    private function guardAgainstClosedMonth(bool $isDelete = false): void
    {
        if (! $this->facility_id || ! $this->billing_year_month) {
            return;
        }

        // 入金消込など支払系の更新のみの場合はガードをパス（DBクエリも発行しない）
        if ($this->exists && ! $isDelete) {
            $dirtyKeys = array_keys($this->getDirty());
            $onlyPaymentFields = $dirtyKeys === []
                || array_diff($dirtyKeys, self::CLOSED_MONTH_ALLOWED_FIELDS) === [];

            // ステータスは「入金済への遷移」または「変更なし」のみ許可
            $statusOk = ! $this->isDirty('status')
                || $this->status === InvoiceStatus::Paid
                || $this->status === $this->getOriginal('status');

            if ($onlyPaymentFields && $statusOk) {
                return;
            }
        }

        if (MonthlyClosingState::isClosed($this->billing_year_month, (int) $this->facility_id)) {
            throw new \RuntimeException(
                "{$this->billing_year_month} は締め済みのため、請求データの変更・削除・作成はできません。"
                .'変更が必要な場合は月次締めワザードから差戻しをしてください。'
            );
        }
    }

    /**
     * 入居者
     */
    public function resident(): BelongsTo
    {
        return $this->belongsTo(Resident::class);
    }

    /**
     * 施設
     */
    public function facility(): BelongsTo
    {
        return $this->belongsTo(Facility::class);
    }

    /**
     * 申請者（承認ワークフロー用）
     */
    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    /**
     * 承認者（承認ワークフロー用）
     */
    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * 入金処理を行い、領収書番号を発行する
     */
    public function markAsPaid(PaymentMethod $method, ?string $paidAt = null, ?string $receiptDateMode = null, ?string $customReceiptDate = null): void
    {
        // 証憑添付必須モードの場合、証憑がないと入金消込をブロック
        if (Config::get('billing.payment_evidence_required', false)
            && $this->paymentEvidences()->count() === 0) {
            throw new \DomainException(
                '入金消込には証憑（受領書・明細書・振込明細等）の添付が必要です。'
                .'証憑をアップロードしてから実行してください。'
            );
        }

        $paidDate = $paidAt ? Carbon::parse($paidAt)->toDateString() : now()->toDateString();

        $this->update([
            'status' => InvoiceStatus::Paid,
            'paid_at' => $paidDate,
            'payment_method' => $method,
            'receipt_number' => $this->receipt_number ?? sprintf('REC-%s-%04d', str_replace('-', '', $this->billing_year_month), $this->resident_id),
            'receipt_issued_at' => $this->receipt_issued_at ?? now(),
            'receipt_date_mode' => $receiptDateMode ?? 'auto',
            'custom_receipt_date' => $customReceiptDate,
        ]);
    }

    /**
     * 承認申請を行う（Unbilled → PendingApproval）
     *
     * @throws \DomainException 申請できない状態の場合
     */
    public function submitForApproval(int $userId): void
    {
        if ($this->status !== InvoiceStatus::Unbilled) {
            throw new \DomainException(
                '承認申請は「未請求」の請求書に対してのみ行えます。現在のステータス: '.$this->status->getLabel()
            );
        }

        $this->update([
            'status' => InvoiceStatus::PendingApproval,
            'submitted_by' => $userId,
            'submitted_at' => now(),
        ]);
    }

    /**
     * 承認する（PendingApproval → Billed）
     *
     * double_check モードでは申請者と承認者が同一不可。
     *
     * @throws \DomainException 承認できない状態の場合
     */
    public function approve(int $userId): void
    {
        $mode = Config::get('billing.mode', 'off');

        if ($mode === 'off') {
            // 承認フロー未使用時は直接 Billed へ
            if ($this->status !== InvoiceStatus::Unbilled) {
                throw new \DomainException(
                    '請求確定は「未請求」の請求書に対してのみ行えます。現在のステータス: '.$this->status->getLabel()
                );
            }

            $this->update([
                'status' => InvoiceStatus::Billed,
            ]);

            return;
        }

        if ($this->status !== InvoiceStatus::PendingApproval) {
            throw new \DomainException(
                '承認は「承認待ち」の請求書に対してのみ行えます。現在のステータス: '.$this->status->getLabel()
            );
        }

        if ($mode === 'double_check' && $this->submitted_by === $userId) {
            throw new \DomainException(
                '申請者自身による承認はできません。別のユーザーが承認してください。'
            );
        }

        if ($mode === 'admin_only' && ! auth()->user()?->is_admin) {
            throw new \DomainException(
                '承認は法人管理者のみ行えます。'
            );
        }

        $this->update([
            'status' => InvoiceStatus::Billed,
            'approved_by' => $userId,
            'approved_at' => now(),
        ]);
    }

    /**
     * 承認待ちに戻す（Billed → PendingApproval または PendingApproval → Unbilled 差戻し）
     *
     * @throws \DomainException 差戻しできない状態の場合
     */
    public function returnToPending(?int $userId = null): void
    {
        if ($this->status === InvoiceStatus::PendingApproval) {
            // 差戻し（承認待ちから未請求へ）
            $this->update([
                'status' => InvoiceStatus::Unbilled,
                'submitted_by' => null,
                'submitted_at' => null,
                'approved_by' => null,
                'approved_at' => null,
            ]);

            return;
        }

        if ($this->status === InvoiceStatus::Billed && $userId) {
            // 請求済から承認待ちへ差戻し（承認取り消し）
            $this->update([
                'status' => InvoiceStatus::PendingApproval,
                'submitted_by' => $userId,
                'submitted_at' => now(),
                'approved_by' => null,
                'approved_at' => null,
            ]);

            return;
        }

        throw new \DomainException(
            '差戻しできないステータスです: '.$this->status->getLabel()
        );
    }

    /**
     * 証憑リレーション
     */
    public function paymentEvidences(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(PaymentEvidence::class, 'monthly_invoice_id');
    }

    /**
     * 非課税額（家賃）を取得する
     */
    public function getNonTaxableAmountAttribute(): int
    {
        // rent_subtotal が null の行でも TypeError にならないようキャスト
        return (int) $this->rent_subtotal;
    }

    /**
     * 課税対象額（管理費＋自費）を取得する
     *
     * tax_breakdown がある場合はそこから算出、なければ保存値（$value = DB生値）、
     * それも無ければ小計から算出する。
     * ※ 自己参照（$this->taxable_amount）を避け、無限再帰を防ぐ。
     */
    public function getTaxableAmountAttribute($value = null): int
    {
        if ($this->tax_breakdown && isset($this->tax_breakdown['standard']['taxable_amount'], $this->tax_breakdown['reduced']['taxable_amount'])) {
            return (int) $this->tax_breakdown['standard']['taxable_amount'] + (int) $this->tax_breakdown['reduced']['taxable_amount'];
        }

        if ($value !== null) {
            return (int) $value;
        }

        return (int) $this->management_fee_subtotal + (int) $this->service_subtotal;
    }

    /**
     * 消費税額を取得する
     *
     * tax_breakdown がある場合はそこから算出、なければ保存値（$value = DB生値）、
     * それも無ければ TaxCalculator と同じ切り捨て（floor）で算出する。
     * ※ 自己参照（$this->tax_amount）を避け、無限再帰を防ぐ。
     */
    public function getTaxAmountAttribute($value = null): int
    {
        if ($this->tax_breakdown && isset($this->tax_breakdown['standard']['tax_amount'], $this->tax_breakdown['reduced']['tax_amount'])) {
            return (int) $this->tax_breakdown['standard']['tax_amount'] + (int) $this->tax_breakdown['reduced']['tax_amount'];
        }

        if ($value !== null) {
            return (int) $value;
        }

        // 消費税の端数処理は切り捨て（floor）: TaxCalculator と同一のルール
        return (int) floor($this->getTaxableAmountAttribute() * ((float) ($this->tax_rate ?? 10) / 100));
    }

    /**
     * 税込み合計額を取得する
     *
     * DBには total_with_tax カラムを持つが、表示・計算では常に内訳から
     * 再計算した値を返す（単一の正解 = tax_breakdown / TaxCalculator）。
     */
    public function getTotalWithTaxAttribute(): int
    {
        return (int) $this->rent_subtotal + $this->getTaxableAmountAttribute() + $this->getTaxAmountAttribute();
    }

    /**
     * 請求年月スコープ
     */
    public function scopeForYearMonth(Builder $query, string $yearMonth): Builder
    {
        return $query->where('billing_year_month', $yearMonth);
    }

    /**
     * 施設でフィルタするスコープ
     */
    public function scopeForFacility(Builder $query, int $facilityId): Builder
    {
        return $query->where('facility_id', $facilityId);
    }
}
