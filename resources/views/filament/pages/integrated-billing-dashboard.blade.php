<x-filament-panels::page>
    <div class="fi-page">
        <div class="fi-page-header">
            <h1 class="fi-page-title">{{ heading() }}</h1>
        </div>

        <div class="fi-page-content">
            <!-- フィルタフォーム -->
            <x-filament::section>
                <form wire:submit.prevent="applyFilters" class="flex flex-wrap gap-4 items-end">
                    <div class="w-48">
                        <x-filament::label for="billing_year_month" :value="__('filament-panels::resources/pages/list-records.filters.label')" />
                        <x-filament::select
                            id="billing_year_month"
                            wire:model.live="tableFilters.billing_year_month"
                            :options="$getMonthOptions()"
                            class="w-full"
                        />
                    </div>

                    @if (auth()->user()?->isCorporateAdmin())
                    <div class="w-64">
                        <x-filament::label for="facility_id" :value="__('filament-panels::resources/pages/list-records.filters.label')" />
                        <x-filament::select
                            id="facility_id"
                            wire:model.live="tableFilters.facility_id"
                            :options="$getFacilityOptions()"
                            placeholder="全施設"
                            class="w-full"
                        />
                    </div>
                    @endif

                    <x-filament::button type="submit" class="h-10">
                        {{ __('filament-panels::resources/pages/list-records.filters.apply') }}
                    </x-filament::button>
                </form>
            </x-filament::section>

            <!-- 統計サマリーカード -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
                <x-filament::stats-overview-stat
                    :label="__('住居費総計')"
                    :value="$formatMoney($getHousingTotal())"
                    :color="'primary'"
                    :icon="'heroicon-o-currency-yen'"
                />

                <x-filament::stats-overview-stat
                    :label="__('介護サービス総計')"
                    :value="$formatMoney($getCareTotal())"
                    :color="'warning'"
                    :icon="'heroicon-o-heart'"
                />

                <x-filament::stats-overview-stat
                    :label="__('総合計')"
                    :value="$formatMoney($getGrandTotal())"
                    :color="'danger'"
                    :icon="'heroicon-o-calculator'"
                />

                <x-filament::stats-overview-stat
                    :label="__('対象入居者数')"
                    :value="$getResidentCount()"
                    :color="'success'"
                    :icon="'heroicon-o-users'"
                />
            </div>

            <!-- テーブル -->
            <x-filament::table :table="$table" />
        </div>
    </div>
</x-filament-panels::page>