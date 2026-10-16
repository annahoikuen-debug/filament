<?php

namespace App\Filament\Resources\DailyChargeResource\Pages;

use App\Filament\Resources\DailyChargeResource;
use App\Services\QuickInputService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;

class CreateDailyCharge extends CreateRecord
{
    protected static string $resource = DailyChargeResource::class;

    protected function getFormActions(): array
    {
        $actions = parent::getFormActions();
        
        array_unshift($actions, Action::make('saveAndContinue')
            ->label('保存して続けて入力')
            ->icon('heroicon-o-plus-circle')
            ->color('primary')
            ->submit('saveAndContinue')
            ->requiresConfirmation()
            ->modalHeading('保存して続けて入力')
            ->modalDescription('この記録を保存し、同じ品目・単価・備考で次の入力フォームを表示します。'));

        return $actions;
    }

    protected function handleRecordCreation(array $data): \App\Models\DailyCharge
    {
        $record = static::getModel()::create($data);

        if (request()->has('saveAndContinue')) {
            // クイック入力用データをセッションに保存
            session()->flash('quick_input_data', [
                'charge_item_id' => $record->charge_item_id,
                'unit_price'     => $record->unit_price,
                'quantity'       => $record->quantity,
                'note'           => $record->note,
            ]);

            Notification::make()
                ->title('保存しました。続けて入力できます。')
                ->success()
                ->send();

            // 同じページにリダイレクト（フォームリセット）
            redirect(static::getResource()::getUrl('create'));
        }

        return $record;
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('index');
    }

    public function mount(): void
    {
        parent::mount();

        // 1. URLクエリパラメータから初期値を設定（クイック作成ボタンからの遷移）
        $queryDefaults = request()->only(['charge_item_id', 'unit_price', 'quantity', 'note', 'date']);
        $queryDefaults = array_filter($queryDefaults, fn ($v) => $v !== null && $v !== '');
        if ($queryDefaults) {
            $this->form->fill(array_merge($this->form->getState(), $queryDefaults));
            // 入居者と日付はクエリに含まれない場合のみ初期化
            if (! isset($queryDefaults['resident_id'])) {
                $this->form->fill(array_merge($this->form->getState(), [
                    'resident_id' => null,
                ]));
            }
            if (! isset($queryDefaults['date'])) {
                $this->form->fill(array_merge($this->form->getState(), [
                    'date' => now()->toDateString(),
                ]));
            }
        }

        // 2. セッションからクイック入力データを復元（「保存して続けて入力」後）
        if (session()->has('quick_input_data')) {
            $data = session()->pull('quick_input_data');
            $this->form->fill(array_merge($this->form->getState(), $data));
            // 入居者と日付は初期化（連続記録用）
            $this->form->fill(array_merge($this->form->getState(), [
                'resident_id' => null,
                'date'        => now()->toDateString(),
            ]));
        }
    }
}
