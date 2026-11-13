<?php

namespace App\Filament\Resources\InvoiceDocumentResource\Pages;

use App\Filament\Resources\InvoiceDocumentResource;
use Filament\Pages\Actions;
use Filament\Resources\Pages\ListRecords;

class ListInvoiceDocuments extends ListRecords
{
    protected static string $resource = InvoiceDocumentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('verifyAll')
                ->label('全件改ざん検証')
                ->icon('heroicon-o-shield-check')
                ->color('warning')
                ->requiresConfirmation()
                ->action(function () {
                    $verified = 0;
                    $mismatched = 0;

                    foreach (\App\Models\InvoiceDocument::all() as $record) {
                        $filePath = storage_path('app/'.$record->file_path);
                        if (! file_exists($filePath)) {
                            $mismatched++;
                            continue;
                        }

                        if ($record->verifyHash($filePath)) {
                            $verified++;
                        } else {
                            $mismatched++;
                        }
                    }

                    \Filament\Notifications\Notification::make()
                        ->title('改ざん検証完了')
                        ->body("一致: {$verified}件 / 不一致: {$mismatched}件")
                        ->success($mismatched === 0)
                        ->send();
                }),
        ];
    }
}
