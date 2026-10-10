<?php

namespace App\Filament\Resources\ItemMasterResource\Pages;

use App\Filament\Resources\ItemMasterResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListItemMasters extends ListRecords
{
    protected static string $resource = ItemMasterResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('品目を追加'),
        ];
    }
}
