<?php

namespace App\Filament\Resources\DailyChargeResource\Pages;

use App\Filament\Resources\DailyChargeResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListDailyCharges extends ListRecords
{
    protected static string $resource = DailyChargeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('＋ 自費記録を登録')
                ->icon('heroicon-o-plus'),
        ];
    }
}
