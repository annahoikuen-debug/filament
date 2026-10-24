<?php

namespace App\Policies;

use App\Enums\InvoiceStatus;
use App\Models\MonthlyInvoice;
use App\Models\User;

class MonthlyInvoicePolicy
{
    /**
     * アーカイブ済み（確定済み）の請求書は更新・削除を禁止する
     */
    public function update(User $user, MonthlyInvoice $invoice): bool
    {
        // 入金済みまたは請求済みの場合は更新を禁止
        $archivedStatuses = [InvoiceStatus::Paid, InvoiceStatus::Billed];

        return ! in_array($invoice->status, $archivedStatuses, true);
    }

    /**
     * アーカイブ済みの請求書は削除を禁止する
     */
    public function delete(User $user, MonthlyInvoice $invoice): bool
    {
        // 入金済みまたは請求済みの場合は削除を禁止
        $archivedStatuses = [InvoiceStatus::Paid, InvoiceStatus::Billed];

        return ! in_array($invoice->status, $archivedStatuses, true);
    }

    /**
     * 請求確定の認可（現場介護職 care_staff は確定不可）
     */
    public function finalize(User $user, ?MonthlyInvoice $invoice = null): bool
    {
        return $this->allowsBillingAction($user, $invoice);
    }

    /**
     * 全銀フォーマット出力の認可（経理・管理者のみ）
     */
    public function exportZengin(User $user, ?MonthlyInvoice $invoice = null): bool
    {
        return $this->allowsBillingAction($user, $invoice);
    }

    /**
     * 入金消込の認可（経理・管理者のみ）
     */
    public function markAsPaid(User $user, ?MonthlyInvoice $invoice = null): bool
    {
        return $this->allowsBillingAction($user, $invoice);
    }

    /**
     * 請求関連の重要操作に共通する認可判定
     *
     * - care_staff は禁止
     * - 法人管理者は全施設OK
     * - それ以外は自施設の請求書のみ（請求書未指定の能力チェックは可）
     */
    private function allowsBillingAction(User $user, ?MonthlyInvoice $invoice = null): bool
    {
        if ($user->role === 'care_staff') {
            return false;
        }

        if ($user->canAccessAllFacilities()) {
            return true;
        }

        if ($invoice && $invoice->facility_id) {
            return (int) $user->facility_id === (int) $invoice->facility_id;
        }

        return true;
    }
}
