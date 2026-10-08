<div class="fi-table-empty-state py-16 text-center">
    <div class="max-w-md mx-auto">
        <svg class="mx-auto h-16 w-16 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
        </svg>
        <h3 class="mt-4 text-lg font-medium text-gray-900">自費記録がありません</h3>
        <p class="mt-2 text-gray-500">
            日々の自費利用（おむつ・理美容・受診同行等）を記録してください。<br>
            記録されたデータは翌月の請求に自動反映されます。
        </p>
        <div class="mt-6">
            <x-filament::button
                href="{{ route('filament.admin.resources.daily-charges.create') }}"
                color="primary"
                icon="heroicon-o-plus-circle"
                size="lg"
            >
                自費記録を追加
            </x-filament::button>
        </div>
        <p class="mt-4 text-sm text-gray-400">
            ※ 品目マスタで「デフォルト単価」を設定すると入力が楽になります
        </p>
    </div>
</div>