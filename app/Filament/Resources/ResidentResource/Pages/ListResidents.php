<?php

namespace App\Filament\Resources\ResidentResource\Pages;

use App\Enums\ResidentStatus;
use App\Filament\Resources\ResidentResource;
use App\Models\Resident;
use Filament\Actions;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListResidents extends ListRecords
{
    protected static string $resource = ResidentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('新規入居者登録'),
        ];
    }

    /**
     * スマホ・タブレットでワンタップ切替できるステータスタブ
     * デフォルトで「入居中」のみ表示
     */
    public function getTabs(): array
    {
        return [
            'active' => Tab::make('入居中')
                ->badge(Resident::query()->where('status', ResidentStatus::Active)->count())
                ->badgeColor('success')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', ResidentStatus::Active)),

            'all' => Tab::make('すべて')
                ->badge(Resident::query()->count()),

            'moved_out' => Tab::make('退去済')
                ->badge(Resident::query()->where('status', ResidentStatus::MovedOut)->count())
                ->badgeColor('gray')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', ResidentStatus::MovedOut)),
        ];
    }

    public function getDefaultActiveTab(): string|int|null
    {
        return 'active';
    }
}
