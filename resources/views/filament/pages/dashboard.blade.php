<x-filament-panels::page>
    <!-- Header -->
    <div class="flex items-center justify-between gap-4 mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">
                ダッシュボード
                @if($facility)
                    <span class="ml-3 text-sm font-normal text-gray-500">({{ $facility->name }})</span>
                @endif
            </h1>
            <p class="text-gray-500 mt-1">
                {{ \Carbon\Carbon::createFromFormat('Y-m', $currentYearMonth)->format('Y年m月') }} 請求管理
            </p>
        </div>
        <div class="flex items-center gap-2">
            <select wire:model.live="currentYearMonth" class="fi-input block w-48 border-gray-300 rounded-md shadow-sm">
                @foreach($yearMonthOptions as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </select>
            <x-filament::button
                wire:click="generateBilling"
                color="primary"
                icon="heroicon-o-plus-circle"
                size="lg"
            >
                今月の請求を生成
            </x-filament::button>
        </div>
    </div>

    <!-- Quick Stats Grid -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <div class="bg-white rounded-xl border border-gray-200 p-5 shadow-sm">
            <div class="text-sm font-medium text-gray-500">入居者数</div>
            <div class="mt-2 text-3xl font-bold text-gray-900">{{ $quickStats['total_residents'] }}</div>
            <div class="mt-1 text-sm text-gray-500">稼働率: {{ $quickStats['occupancy_rate'] }}%</div>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 p-5 shadow-sm">
            <div class="text-sm font-medium text-gray-500">請求総額</div>
            <div class="mt-2 text-3xl font-bold text-gray-900">¥{{ number_format($quickStats['total_billed']) }}</div>
            <div class="mt-1 text-sm text-gray-500">税込み合計</div>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 p-5 shadow-sm">
            <div class="text-sm font-medium text-gray-500">入金済み</div>
            <div class="mt-2 text-3xl font-bold text-green-600">¥{{ number_format($quickStats['total_paid']) }}</div>
            <div class="mt-1 text-sm text-gray-500">回収率: {{ $quickStats['collection_rate'] }}%</div>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 p-5 shadow-sm">
            <div class="text-sm font-medium text-gray-500">未回収</div>
            <div class="mt-2 text-3xl font-bold text-amber-600">¥{{ number_format($quickStats['total_unpaid']) }}</div>
            <div class="mt-1 text-sm text-gray-500">平均回収: {{ $quickStats['avg_collection_days'] }}日</div>
        </div>
    </div>

    <!-- Billing Progress Card -->
    <div class="bg-white rounded-xl border border-gray-200 mb-6 shadow-sm">
        <div class="flex items-center justify-between p-4 border-b border-gray-200">
            <h2 class="text-lg font-semibold text-gray-900">今月の請求進捗</h2>
            <span class="text-sm text-gray-500">
                {{ $billingStats['billed'] }} / {{ $billingStats['total'] }} 件 ({{ $billingStats['progress'] }}%)
            </span>
        </div>
        <div class="p-4">
            <div class="w-full bg-gray-200 rounded-full h-4 mb-3">
                <div class="bg-blue-600 h-4 rounded-full transition-all duration-300"
                     style="width: {{ $billingStats['progress'] }}%"></div>
            </div>
            <div class="grid grid-cols-4 gap-4 text-center text-sm">
                <div>
                    <div class="text-2xl font-bold text-gray-900">{{ $billingStats['total'] }}</div>
                    <div class="text-gray-500">総件数</div>
                </div>
                <div>
                    <div class="text-2xl font-bold text-gray-900">{{ $billingStats['unbilled'] }}</div>
                    <div class="text-gray-500">未請求</div>
                </div>
                <div>
                    <div class="text-2xl font-bold text-blue-600">{{ $billingStats['billed'] }}</div>
                    <div class="text-gray-500">請求済</div>
                </div>
                <div>
                    <div class="text-2xl font-bold text-green-600">{{ $billingStats['paid'] }}</div>
                    <div class="text-gray-500">入金済</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Unpaid Residents Table -->
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm">
        <div class="flex items-center justify-between p-4 border-b border-gray-200">
            <h2 class="text-lg font-semibold text-gray-900">未入金 上位5件</h2>
        </div>
        @if(empty($unpaidResidents))
            <div class="p-8 text-center text-gray-500">
                <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                </svg>
                <p class="mt-2">未入金の請求はありません</p>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">部屋番号</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">入居者名</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">請求額</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ステータス</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">アクション</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        @foreach($unpaidResidents as $item)
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-3 text-sm text-gray-900">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                        {{ $item['room_number'] }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-sm font-medium text-gray-900">{{ $item['name'] }}</td>
                                <td class="px-4 py-3 text-sm text-gray-900 font-semibold">
                                    ¥{{ number_format($item['total_amount']) }}
                                </td>
                                <td class="px-4 py-3 text-sm">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                        @if($item['status'] === 'unbilled') bg-yellow-100 text-yellow-800
                                        @elseif($item['status'] === 'billed') bg-blue-100 text-blue-800
                                        @else bg-gray-100 text-gray-800 @endif">
                                        {{ $item['status_label'] }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-sm">
                                    <x-filament::button
                                        size="xs"
                                        color="success"
                                        wire:click="markAsPaid({{ $item['id'] }})"
                                    >
                                        入金消込
                                    </x-filament::button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</x-filament-panels::page>