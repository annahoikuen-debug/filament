<?php

namespace App\Filament\Resources\MonthlyInvoiceResource\Pages;

use App\Filament\Resources\MonthlyInvoiceResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditMonthlyInvoice extends EditRecord
{
    protected static string $resource = MonthlyInvoiceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
