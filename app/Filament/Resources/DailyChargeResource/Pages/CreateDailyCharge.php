<?php

namespace App\Filament\Resources\DailyChargeResource\Pages;

use App\Filament\Resources\DailyChargeResource;
use Filament\Resources\Pages\CreateRecord;

class CreateDailyCharge extends CreateRecord
{
    protected static string $resource = DailyChargeResource::class;

    protected static bool $canCreateAnother = true;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return '自費利用記録を登録しました';
    }
}
