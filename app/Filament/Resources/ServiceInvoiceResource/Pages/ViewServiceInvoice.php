<?php

namespace App\Filament\Resources\ServiceInvoiceResource\Pages;

use App\Filament\Resources\ServiceInvoiceResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewServiceInvoice extends ViewRecord
{
    protected static string $resource = ServiceInvoiceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
