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
}
