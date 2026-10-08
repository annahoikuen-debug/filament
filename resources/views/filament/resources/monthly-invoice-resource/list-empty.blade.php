<div class="fi-table-empty-state py-16 text-center">
    <div class="max-w-md mx-auto">
        <svg class="mx-auto h-16 w-16 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
        </svg>
        <h3 class="mt-4 text-lg font-medium text-gray-900">請求データがありません</h3>
        <p class="mt-2 text-gray-500">
            この月の請求データはまだ生成されていません。<br>
            翌月1日以降に「一括生成」で作成できます。
        </p>
        <div class="mt-6 flex justify-center gap-3">
            <x-filament::button
                wire:click="$dispatch('generateBilling')"
                color="primary"
                icon="heroicon-o-plus-circle"
                size="lg"
            >
                今月の請求を生成
            </x-filament::button>
            <x-filament::button
                href="{{ route('filament.admin.resources.residents.create') }}"
                color="gray"
                icon="heroicon-o-user-plus"
            >
                入居者を登録
            </x-filament::button>
        </div>
        <p class="mt-4 text-sm text-gray-400">
            ※ 入居者マスタが未登録の場合は、先に入居者を登録してください
        </p>
    </div>
</div>