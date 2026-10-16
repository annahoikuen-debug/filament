<?php

namespace App\Filament\Resources\DailyChargeResource\Pages;

use App\Filament\Resources\DailyChargeResource;
use App\Services\QuickInputService;
use Filament\Actions;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Auth;

class ListDailyCharges extends ListRecords
{
    protected static string $resource = DailyChargeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('自費記録を追加'),
            Action::make('quickCreate')
                ->label('今日の記録（クイック）')
                ->icon('heroicon-o-bolt')
                ->color('success')
                ->url(function () {
                    $service = app(QuickInputService::class);
                    $defaults = $service->getLastInputForUser(Auth::user());
                    $query = http_build_query(array_filter($defaults, fn ($v) => $v !== null && $v !== ''));
                    return static::getResource()::getUrl('create') . '?' . $query;
                })
                ->visible(fn () => Auth::check()),
        ];
    }
}
