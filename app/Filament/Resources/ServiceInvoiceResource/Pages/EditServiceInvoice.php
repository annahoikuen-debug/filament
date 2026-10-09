<?php

namespace App\Filament\Resources\ServiceInvoiceResource\Pages;

use App\Filament\Resources\ServiceInvoiceResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditServiceInvoice extends EditRecord
{
    protected static string $resource = ServiceInvoiceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
