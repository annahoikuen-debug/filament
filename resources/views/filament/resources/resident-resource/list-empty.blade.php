<div class="fi-table-empty-state py-16 text-center">
    <div class="max-w-md mx-auto">
        <svg class="mx-auto h-16 w-16 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path>
        </svg>
        <h3 class="mt-4 text-lg font-medium text-gray-900">入居者が登録されていません</h3>
        <p class="mt-2 text-gray-500">
            まずは入居者を登録して、請求管理を始めましょう。
        </p>
        <div class="mt-6">
            <x-filament::button
                href="{{ route('filament.admin.resources.residents.create') }}"
                color="primary"
                icon="heroicon-o-user-plus"
                size="lg"
            >
                最初の入居者を登録
            </x-filament::button>
        </div>
        <p class="mt-4 text-sm text-gray-400">
            CSV一括インポートも可能です（開発中）
        </p>
    </div>
</div>