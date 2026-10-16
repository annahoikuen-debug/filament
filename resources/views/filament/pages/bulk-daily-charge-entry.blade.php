<x-filament-panels::page>
    <div class="space-y-6">
        {{-- モードタブ切替（Step 13） --}}
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-2">
            <div class="flex space-x-2">
                <button type="button" wire:click="$set('mode', 'single')"
                    class="flex-1 px-4 py-2.5 rounded-lg text-sm font-semibold transition-colors {{ $mode === 'single' ? 'bg-primary-600 text-white shadow' : 'bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300 hover:bg-gray-200' }}">
                    単一品目モード
                </button>
                <button type="button" wire:click="$set('mode', 'preset')"
                    class="flex-1 px-4 py-2.5 rounded-lg text-sm font-semibold transition-colors {{ $mode === 'preset' ? 'bg-primary-600 text-white shadow' : 'bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300 hover:bg-gray-200' }}">
                    プリセット一括モード
                </button>
            </div>
        </div>

        @if ($mode === 'single')
            {{-- 単一品目モード --}}
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 items-end">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                            自費サービス品目 <span class="text-danger-600">*</span>
                        </label>
                        <select wire:model.live="chargeItemId" class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 text-sm focus:ring-primary-500 focus:border-primary-500 py-2.5">
                            @foreach (\App\Models\ChargeItem::where('is_active', true)->get() as $item)
                                <option value="{{ $item->id }}">{{ $item->name }} (標準単価: ¥{{ number_format($item->default_price) }})</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                            利用日 <span class="text-danger-600">*</span>
                        </label>
                        <input type="date" wire:model.live="date" class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 text-sm focus:ring-primary-500 focus:border-primary-500 py-2.5">
                    </div>

                    <div class="bg-gray-50 dark:bg-gray-900 p-3 rounded-lg border border-gray-200 dark:border-gray-700 flex justify-between items-center">
                        <span class="text-sm text-gray-500 dark:text-gray-400">適用単価:</span>
                        <span class="text-xl font-bold text-gray-900 dark:text-white">¥{{ number_format($unitPrice) }}</span>
                    </div>
                </div>
            </div>
        @else
            {{-- プリセット一括モード --}}
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
                <div class="grid grid-cols-1 md:grid-cols-4 gap-6 items-end">
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                            プリセット <span class="text-danger-600">*</span>
                        </label>
                        <select wire:model.live="presetId" class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 text-sm focus:ring-primary-500 focus:border-primary-500 py-2.5">
                            @forelse ($presets as $preset)
                                <option value="{{ $preset['id'] }}">{{ $preset['name'] }}</option>
                            @empty
                                <option value="">（プリセット未登録）</option>
                            @endforelse
                        </select>
                        @php
                            $currentPreset = collect($presets)->firstWhere('id', $presetId);
                        @endphp
                        @if (!empty($currentPreset['description']))
                            <p class="text-xs text-gray-500 mt-1">{{ $currentPreset['description'] }}</p>
                        @endif
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                            開始日 <span class="text-danger-600">*</span>
                        </label>
                        <input type="date" wire:model.live="presetStartDate" class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 text-sm py-2.5">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                            終了日 <span class="text-danger-600">*</span>
                        </label>
                        <input type="date" wire:model.live="presetEndDate" class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 text-sm py-2.5">
                    </div>
                </div>

                {{-- 曜日パターン --}}
                <div class="mt-5">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                        適用曜日（未選択＝期間内すべての日）
                    </label>
                    <div class="flex flex-wrap gap-2">
                        @foreach (['日', '月', '火', '水', '木', '金', '土'] as $index => $label)
                            <label class="inline-flex items-center cursor-pointer">
                                <input type="checkbox" value="{{ $index }}" wire:model.live="presetWeekdays"
                                    class="rounded border-gray-300 text-primary-600 focus:ring-primary-500 sr-only peer">
                                <span class="px-3.5 py-2 rounded-lg text-sm font-medium bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300 peer-checked:bg-primary-600 peer-checked:text-white transition-colors">
                                    {{ $label }}
                                </span>
                            </label>
                        @endforeach
                    </div>
                </div>

                {{-- プレビュー --}}
                @if ($presetPreview !== [])
                    <div class="mt-5 bg-gray-50 dark:bg-gray-900 rounded-lg border border-gray-200 dark:border-gray-700 p-4">
                        <p class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">プリセット内容のプレビュー</p>
                        <div class="flex flex-wrap gap-2 mb-3">
                            @foreach ($presetPreview as $item)
                                <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium bg-primary-50 dark:bg-primary-900/40 text-primary-700 dark:text-primary-300">
                                    {{ $item['name'] }} ×{{ $item['quantity'] }}（¥{{ number_format($item['unit_price']) }}）
                                </span>
                            @endforeach
                        </div>
                        <p class="text-sm text-gray-600 dark:text-gray-400">
                            適用日数: <strong>{{ count($this->presetDates) }}日</strong> ／
                            1名あたり合計: <strong class="text-emerald-600 dark:text-emerald-400">¥{{ number_format(collect($presetPreview)->sum(fn ($i) => $i['unit_price'] * $i['quantity'])) }}</strong>
                            ／ 選択{{ $this->selectedCount }}名の予定総額:
                            <strong class="text-emerald-600 dark:text-emerald-400">¥{{ number_format($this->totalAmount) }}</strong>
                        </p>
                    </div>
                @endif
            </div>
        @endif

        {{-- 入居者一覧グリッド / カード --}}
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden">
            <div class="p-4 border-b border-gray-200 dark:border-gray-700 flex flex-wrap justify-between items-center gap-3 bg-gray-50 dark:bg-gray-900/50">
                <div class="flex flex-wrap items-center gap-2">
                    <button type="button" wire:click="selectAll" class="text-xs font-semibold px-3 py-2 bg-gray-200 dark:bg-gray-700 hover:bg-gray-300 rounded-lg text-gray-700 dark:text-gray-200">
                        全選択
                    </button>
                    <button type="button" wire:click="deselectAll" class="text-xs font-semibold px-3 py-2 bg-gray-200 dark:bg-gray-700 hover:bg-gray-300 rounded-lg text-gray-700 dark:text-gray-200">
                        全解除
                    </button>

                    {{-- フロア別選択 --}}
                    @foreach ($this->floors as $floor)
                        <button type="button" wire:click="selectFloor('{{ $floor }}')" class="text-xs font-semibold px-3 py-2 bg-blue-100 dark:bg-blue-900/40 hover:bg-blue-200 rounded-lg text-blue-700 dark:text-blue-300">
                            {{ $floor }}
                        </button>
                    @endforeach

                    <span class="text-xs text-gray-500 dark:text-gray-400 ml-1">
                        入居者数: {{ count($entries) }}名
                    </span>
                </div>

                <div class="text-sm">
                    選択中: <strong class="text-primary-600 dark:text-primary-400">{{ $this->selectedCount }}</strong> 名
                    @if ($mode === 'single')
                        / 合計予定額: <strong class="text-emerald-600 dark:text-emerald-400">¥{{ number_format($this->totalAmount) }}</strong>
                    @endif
                </div>
            </div>

            {{-- 📱 スマホ用カードビュー（md未満で表示） --}}
            <div class="block md:hidden p-4 space-y-3 max-h-[650px] overflow-y-auto">
                @forelse ($entries as $index => $entry)
                    <div class="p-4 rounded-xl border {{ !empty($entry['selected']) ? 'bg-primary-50/40 border-primary-300 dark:bg-primary-950/20 dark:border-primary-800' : 'bg-white border-gray-200 dark:bg-gray-750 dark:border-gray-700' }} shadow-sm transition-all">
                        <div class="flex items-center justify-between mb-3">
                            <label class="flex items-center space-x-3 cursor-pointer flex-1">
                                <input type="checkbox" wire:model.live="entries.{{ $index }}.selected" class="rounded border-gray-300 text-primary-600 focus:ring-primary-500 h-5 w-5">
                                <div>
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-mono font-medium bg-gray-100 dark:bg-gray-800 text-gray-800 dark:text-gray-200 mr-2">
                                        {{ $entry['room_number'] }}
                                    </span>
                                    <span class="font-bold text-gray-900 dark:text-white text-base">
                                        {{ $entry['name'] }}
                                    </span>
                                </div>
                            </label>
                        </div>

                        @if ($mode === 'single')
                            <div class="grid grid-cols-2 gap-3 mt-3 pt-3 border-t border-gray-100 dark:border-gray-700 items-center">
                                <div>
                                    <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">数量（±ボタンで調整）</label>
                                    <div class="flex items-center space-x-1">
                                        <button type="button" 
                                            @click="$wire.set('entries.{{ $index }}.quantity', Math.max(1, ($wire.entries[{{ $index }}]['quantity'] || 1) - 1))"
                                            class="w-10 h-10 flex items-center justify-center bg-gray-200 dark:bg-gray-700 rounded-lg text-lg font-bold text-gray-700 dark:text-gray-200 active:bg-gray-300">
                                            -
                                        </button>
                                        <input type="number" min="1" max="99" wire:model.live="entries.{{ $index }}.quantity" 
                                            class="w-14 text-center rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 text-base py-2 focus:ring-primary-500 focus:border-primary-500">
                                        <button type="button" 
                                            @click="$wire.set('entries.{{ $index }}.quantity', ($wire.entries[{{ $index }}]['quantity'] || 1) + 1)"
                                            class="w-10 h-10 flex items-center justify-center bg-gray-200 dark:bg-gray-700 rounded-lg text-lg font-bold text-gray-700 dark:text-gray-200 active:bg-gray-300">
                                            +
                                        </button>
                                    </div>
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">小計</label>
                                    <div class="text-base font-bold text-emerald-600 dark:text-emerald-400 py-2">
                                        ¥{{ number_format(($entry['quantity'] ?? 1) * $unitPrice) }}
                                    </div>
                                </div>
                            </div>
                            <div class="mt-3">
                                <input type="text" placeholder="備考（例: 午前中利用）" wire:model.defer="entries.{{ $index }}.note" 
                                    class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 text-sm py-2 px-3 focus:ring-primary-500 focus:border-primary-500">
                            </div>
                        @else
                            <div class="text-xs text-gray-500 dark:text-gray-400 pt-2 border-t border-gray-100 dark:border-gray-700">
                                プリセットモード（数量・備考はテンプレートに従います）
                            </div>
                        @endif
                    </div>
                @empty
                    <div class="py-8 text-center text-gray-500">
                        対象となる入居者が登録されていません。
                    </div>
                @endforelse
            </div>

            {{-- 💻 PC/タブレット用テーブルビュー（md以上で表示） --}}
            <div class="hidden md:block overflow-x-auto max-h-[600px] overflow-y-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-gray-50 dark:bg-gray-900 text-gray-600 dark:text-gray-400 border-b border-gray-200 dark:border-gray-700 sticky top-0 z-10">
                        <tr>
                            <th class="py-3 px-4 w-12 text-center">選択</th>
                            <th class="py-3 px-4 w-24">部屋番号</th>
                            <th class="py-3 px-4 min-w-[140px]">入居者氏名</th>
                            <th class="py-3 px-4 w-32">数量</th>
                            <th class="py-3 px-4">備考（任意）</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                        @forelse ($entries as $index => $entry)
                            <tr class="hover:bg-gray-50/50 dark:hover:bg-gray-750 transition-colors {{ !empty($entry['selected']) ? 'bg-primary-50/30 dark:bg-primary-950/20' : '' }}">
                                <td class="py-3 px-4 text-center">
                                    <input type="checkbox" wire:model.live="entries.{{ $index }}.selected" class="rounded border-gray-300 text-primary-600 focus:ring-primary-500 h-4 w-4">
                                </td>
                                <td class="py-3 px-4 font-mono font-medium text-gray-600 dark:text-gray-300">
                                    {{ $entry['room_number'] }}
                                </td>
                                <td class="py-3 px-4 font-medium text-gray-900 dark:text-white">
                                    {{ $entry['name'] }}
                                </td>
                                <td class="py-3 px-4">
                                    <input type="number" min="1" max="99" wire:model.live="entries.{{ $index }}.quantity" @if($mode === 'preset') disabled @endif class="w-24 rounded border-gray-300 dark:border-gray-600 dark:bg-gray-700 text-sm py-1 px-2 focus:ring-primary-500 focus:border-primary-500 {{ empty($entry['selected']) ? 'opacity-50' : '' }} {{ $mode === 'preset' ? 'cursor-not-allowed' : '' }}">
                                </td>
                                <td class="py-3 px-4">
                                    <input type="text" placeholder="{{ $mode === 'preset' ? '（プリセットモードでは備考なしで登録されます）' : '例: 午前中利用' }}" @if($mode === 'preset') disabled @endif wire:model.defer="entries.{{ $index }}.note" class="w-full rounded border-gray-300 dark:border-gray-600 dark:bg-gray-700 text-sm py-1 px-2 focus:ring-primary-500 focus:border-primary-500 {{ empty($entry['selected']) ? 'opacity-50' : '' }}">
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="py-8 text-center text-gray-500">
                                    対象となる入居者が登録されていません。
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{-- フッター・保存ボタン --}}
            <div class="p-4 bg-gray-50 dark:bg-gray-900 border-t border-gray-200 dark:border-gray-700 flex justify-between items-center">
                <div class="text-sm text-gray-600 dark:text-gray-400">
                    @if ($mode === 'preset')
                        ※ チェックを入れた入居者に対し、プリセット内容を期間・曜日パターンに従って一括登録します。
                    @else
                        ※ チェックを入れた入居者のみ保存されます。
                    @endif
                </div>
                <div>
                    <x-filament::button wire:click="saveBulk" color="primary" icon="heroicon-o-check" size="lg">
                        {{ $mode === 'preset' ? 'プリセットを一括適用する' : '選択した自費記録を一括保存する' }}
                    </x-filament::button>
                </div>
            </div>

            {{-- 保存前確認モーダル --}}
            <div x-show="showConfirmModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50">
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-xl w-full max-w-2xl mx-4 p-6">
                    <div class="flex justify-between items-start mb-4">
                        <h2 class="text-xl font-bold text-gray-900 dark:text-white">保存の確認</h2>
                        <button type="button" @click="showConfirmModal = false" class="text-gray-500 hover:text-gray-700">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                            </svg>
                        </button>
                    </div>

                    <div class="space-y-4">
                        <div class="text-base text-gray-700 dark:text-gray-300">
                            {{ $mode === 'preset' ? 'プリセットを適用します' : '自費記録を保存します' }} よろしいですか？
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">対象入居者数</p>
                                <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ $confirmSelectedCount }} 名</p>
                            </div>
                            <div>
                                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">予定総額</p>
                                <p class="text-2xl font-bold text-emerald-600 dark:text-emerald-400">¥{{ number_format($confirmTotalAmount) }}</p>
                            </div>
                        </div>

                        @if ($confirmSkippedLocked > 0)
                            <div class="bg-yellow-50 dark:bg-yellow-950/20 border border-yellow-200 dark:border-yellow-800 rounded-lg p-4">
                                <div class="flex items-start space-x-3">
                                    <div class="flex-shrink-0">
                                        <svg class="h-5 w-5 text-yellow-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                        </svg>
                                    </div>
                                    <div>
                                        <p class="text-sm font-medium text-yellow-800 dark:text-yellow-200 mb-1">確定済みのためスキップ</p>
                                        <p class="text-base text-yellow-700 dark:text-yellow-300">{{ $confirmSkippedLocked }} 名の入居者が請求済または入金済のため、対象外となります。</p>
                                    </div>
                                </div>
                            </div>
                        @endif
                    </div>

                    <div class="flex justify-end space-x-3 mt-6">
                        <button type="button" @click="showConfirmModal = false" class="px-4 py-2 bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-lg hover:bg-gray-300 transition-colors">
                            キャンセル
                        </button>
                        <button type="button" wire:click="executeSave" class="px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700 transition-colors">
                            実行して保存
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-filament-panels::page>
