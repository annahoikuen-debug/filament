<?php

namespace App\Filament\Resources\ApiTokenResource\Pages;

use App\Filament\Resources\ApiTokenResource;
use App\Models\ApiToken;
use Filament\Infolists\Components\Section as InfolistSection;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Pages\CreateRecord;

class CreateApiToken extends CreateRecord
{
    protected static string $resource = ApiTokenResource::class;

    /**
     * 発行された平文トークン（一度だけ表示）
     */
    public ?string $plainToken = null;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        [$plainToken, $record] = ApiToken::issue(
            (int) $data['facility_id'],
            $data['name'],
            $data['abilities'] ?? [],
            $data['expires_at'] ?? null,
        );

        $this->plainToken = $plainToken;

        // トークン発行後に作成処理を上書きするため false を返す
        $this->record = $record;

        return $record->only(array_keys($record->getAttributes()));
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return 'APIトークンを作成しました';
    }

    public function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('index');
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            InfolistSection::make('APIトークンが発行されました')
                ->description('このトークンは再度表示できません。今すぐコピーして安全に保管してください。')
                ->schema([
                    TextEntry::make('plain_token')
                        ->label('APIトークン')
                        ->state($this->plainToken ?? '(表示済み)')
                        ->copyable()
                        ->copyMessage('コピーしました')
                        ->fontFamily('mono')
                        ->color('danger'),
                ]),
        ]);
    }
}
