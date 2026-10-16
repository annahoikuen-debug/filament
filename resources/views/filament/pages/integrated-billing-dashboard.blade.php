<x-filament-panels::page>
    <div class="space-y-6">
        {{-- 上部フィルタ（請求年月・施設） --}}
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-4">
            <div class="flex flex-wrap items-center gap-4">
                <div class="w-48">
                    <label for="filter_year_month" class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">請求年月</label>
                    <select
                        id="filter_year_month"
                        wire:model.live="filters.billing_year_month"
                        class="w-full text-sm rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 shadow-sm focus:border-primary-500 focus:ring-primary-500"
                    >
                        @foreach (\App\Models\MonthlyInvoice::distinct()->orderBy('billing_year_month', 'desc')->pluck('billing_year_month') as $ym)
                            <option value="{{ $ym }}">{{ $ym }}</option>
                        @endforeach
                    </select>
                </div>

                @if (auth()->user()?->isCorporateAdmin())
                    <div class="w-64">
                        <label for="filter_facility" class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">施設</label>
                        <select
                            id="filter_facility"
                            wire:model.live="filters.facility_id"
                            class="w-full text-sm rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 shadow-sm focus:border-primary-500 focus:ring-primary-500"
                        >
                            <option value="">全施設</option>
                            @foreach (\App\Models\Facility::all() as $facility)
                                <option value="{{ $facility->id }}">{{ $facility->name }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif
            </div>
        </div>

        {{-- 統合請求一覧テーブル --}}
        <div>
            {{ $this->table }}
        </div>
    </div>
</x-filament-panels::page>