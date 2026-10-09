<?php

namespace App\Filament\Resources\ServiceInvoiceResource\Pages;

use App\Filament\Resources\ServiceInvoiceResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListServiceInvoices extends ListRecords
{
    protected static string $resource = ServiceInvoiceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
