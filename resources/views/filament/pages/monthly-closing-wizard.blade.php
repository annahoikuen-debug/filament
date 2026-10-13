<x-filament-panels::page>
    <div class="space-y-6" x-data="{ open: true }">
        {{-- ヘッダー: 対象月・施設・締め状態 --}}
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">対象月</label>
                    <input type="month" wire:model.live="yearMonth" class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 text-sm">
                </div>
                @if (!auth()->user()?->isFacilityAdmin())
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">施設</label>
                        <select wire:model.live="facilityId" class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 text-sm">
                            <option value="">選択してください</option>
                            @foreach (\App\Models\Facility::all() as $facility)
                                <option value="{{ $facility->id }}">{{ $facility->name }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif
                <div class="bg-gray-50 dark:bg-gray-900 p-3 rounded-lg border border-gray-200 dark:border-gray-700">
                    <span class="text-xs text-gray-500 dark:text-gray-400 block">締め状態</span>
                    <span class="text-lg font-bold {{ ($this->closingState?->status ?? 'unclosed') === 'closed' ? 'text-emerald-600' : 'text-amber-600' }}">
                        {{ ['unclosed' => '未締め', 'generated' => '請求生成済', 'approved' => '承認済', 'sent' => '送付済', 'closed' => '締め済（ロック）'][$this->closingState?->status ?? 'unclosed'] }}
                    </span>
                </div>
            </div>
        </div>

        {{-- ステップインジケーター --}}
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-4">
            <div class="flex items-center justify-between">
                @foreach ([1 => '整合性チェック', 2 => '請求データ生成', 3 => '請求書出力', 4 => '会計CSV & クローズ'] as $i => $label)
                    <button type="button" wire:click="goToStep({{ $i }})"
                        class="flex items-center gap-2 px-3 py-2 rounded-lg text-sm font-medium transition-colors
                            {{ $step === $i ? 'bg-primary-600 text-white' : ($step > $i ? 'bg-emerald-100 dark:bg-emerald-900/40 text-emerald-700 dark:text-emerald-300' : 'bg-gray-100 dark:bg-gray-700 text-gray-500') }}">
                        <span class="w-6 h-6 rounded-full flex items-center justify-center text-xs font-bold {{ $step === $i ? 'bg-white text-primary-600' : ($step > $i ? 'bg-emerald-600 text-white' : 'bg-gray-300 dark:bg-gray-600 text-white') }}">
                            {{ $step > $i ? '✓' : $i }}
                        </span>
                        <span class="hidden md:inline">{{ $label }}</span>
                    </button>
                    @if ($i < 4)
                        <div class="flex-1 h-0.5 {{ $step > $i ? 'bg-emerald-500' : 'bg-gray-200 dark:bg-gray-600' }}"></div>
                    @endif
                @endforeach
            </div>
        </div>

        {{-- Step 1: 整合性チェック & 異常値確認 --}}
        @if ($step === 1)
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Step 1: 整合性チェック & 異常値確認</h3>
                <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">
                    請求額0円、入退去日不整合、未確定請求、自費記録0件、前月比乖離（±30%）の5ルールで検査します。Errorがある場合は次へ進めません。
                </p>
                <div class="flex gap-3">
                    <x-filament::button wire:click="runAnomalyCheck" icon="heroicon-o-magnifying-glass">
                        整合性チェックを実行
                    </x-filament::button>
                    @if ($anomalyReport)
                        <x-filament::button wire:click="goToStep(2)" color="success"
                            icon="heroicon-o-arrow-right" :disabled="$anomalyReport['error_count'] > 0">
                            次へ（Error {{ $anomalyReport['error_count'] }}件）
                        </x-filament::button>
                    @endif
                </div>

                @if ($anomalyReport)
                    <div class="mt-4 space-y-2">
                        @forelse ($anomalyReport['errors'] as $item)
                            <div class="p-3 rounded-lg bg-danger-50 dark:bg-danger-950/30 border border-danger-200 dark:border-danger-800">
                                <span class="text-xs font-bold text-danger-700 dark:text-danger-300 uppercase">ERROR / {{ $item['rule_label'] }}</span>
                                <p class="text-sm text-danger-800 dark:text-danger-200">{{ $item['message'] }}</p>
                            </div>
                        @empty
                            <div class="p-3 rounded-lg bg-emerald-50 dark:bg-emerald-950/30 border border-emerald-200 dark:border-emerald-800 text-sm text-emerald-800 dark:text-emerald-200">
                                ✓ Errorは検出されませんでした。
                            </div>
                        @endforelse
                        @foreach ($anomalyReport['warnings'] as $item)
                            <div class="p-3 rounded-lg bg-amber-50 dark:bg-amber-950/30 border border-amber-200 dark:border-amber-800">
                                <span class="text-xs font-bold text-amber-700 dark:text-amber-300 uppercase">WARNING / {{ $item['rule_label'] }}</span>
                                <p class="text-sm text-amber-800 dark:text-amber-200">{{ $item['message'] }}</p>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        @endif

        {{-- Step 2: 月次請求データの一括生成 --}}
        @if ($step === 2)
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Step 2: 月次請求データの一括生成 & 差額レビュー</h3>
                <x-filament::button wire:click="generateInvoices" icon="heroicon-o-calculator" :disabled="($this->closingState?->status ?? '') === 'closed'">
                    月次請求データを一括生成
                </x-filament::button>

                @if ($generationReport)
                    <div class="mt-4 grid grid-cols-2 md:grid-cols-4 gap-3">
                        <div class="p-4 rounded-lg bg-emerald-50 dark:bg-emerald-950/30 text-center">
                            <div class="text-2xl font-bold text-emerald-700 dark:text-emerald-300">{{ $generationReport['created'] }}</div>
                            <div class="text-xs text-gray-500">新規作成</div>
                        </div>
                        <div class="p-4 rounded-lg bg-blue-50 dark:bg-blue-950/30 text-center">
                            <div class="text-2xl font-bold text-blue-700 dark:text-blue-300">{{ $generationReport['updated'] }}</div>
                            <div class="text-xs text-gray-500">更新</div>
                        </div>
                        <div class="p-4 rounded-lg bg-gray-50 dark:bg-gray-900 text-center">
                            <div class="text-2xl font-bold text-gray-700 dark:text-gray-300">{{ $generationReport['skipped'] }}</div>
                            <div class="text-xs text-gray-500">スキップ（確定済）</div>
                        </div>
                        <div class="p-4 rounded-lg bg-gray-50 dark:bg-gray-900 text-center">
                            <div class="text-2xl font-bold text-gray-700 dark:text-gray-300">{{ $generationReport['total_residents'] }}</div>
                            <div class="text-xs text-gray-500">対象入居者</div>
                        </div>
                    </div>
                    <div class="mt-4">
                        <x-filament::button wire:click="goToStep(3)" color="success" icon="heroicon-o-arrow-right">次へ</x-filament::button>
                    </div>
                @endif
            </div>
        @endif

        {{-- Step 3: 口座振替依頼 / PDF ZIP / メール配信 --}}
        @if ($step === 3)
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Step 3: 請求書出力</h3>
                <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">
                    請求書PDF一括ZIPを生成します。口座振替依頼は「月次請求一覧」の一括操作から、メール配信は各請求書画面から実行できます。
                </p>
                <div class="flex gap-3">
                    <x-filament::button wire:click="generateOutputs" icon="heroicon-o-archive-box" :disabled="($this->closingState?->status ?? '') === 'closed'">
                        請求書PDF一括ZIP生成
                    </x-filament::button>
                    @if ($outputReport)
                        <x-filament::button wire:click="markAsSent" color="gray" icon="heroicon-o-paper-airplane">送付済にする</x-filament::button>
                        <x-filament::button wire:click="goToStep(4)" color="success" icon="heroicon-o-arrow-right">次へ</x-filament::button>
                    @endif
                </div>
            </div>
        @endif

        {{-- Step 4: 会計仕訳CSV & 月次クローズ --}}
        @if ($step === 4)
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Step 4: 会計仕訳CSVエクスポート & 月次クローズ</h3>
                <div class="flex flex-wrap gap-3 items-center">
                    <x-filament::button wire:click="exportAccountingCsv" color="gray" icon="heroicon-o-arrow-down-tray">
                        会計仕訳CSV出力
                    </x-filament::button>
                </div>

                <div class="mt-6 p-4 rounded-lg bg-danger-50 dark:bg-danger-950/30 border border-danger-200 dark:border-danger-800">
                    <label class="flex items-start gap-3 cursor-pointer">
                        <input type="checkbox" wire:model="confirmClosing" class="mt-1 rounded border-gray-300 text-danger-600 focus:ring-danger-500">
                        <span class="text-sm text-gray-800 dark:text-gray-200">
                            <strong>締め確定の確認:</strong> クローズ後は {{ $yearMonth }} の自費・請求データがロックされ、一般職員による修正はできなくなります。
                            （管理者による「緊急差戻し」のみ可能）
                        </span>
                    </label>
                    <div class="mt-3">
                        <x-filament::button wire:click="closeMonth" color="danger" icon="heroicon-o-lock-closed" :disabled="! $confirmClosing">
                            月次締めを確定する（ロック）
                        </x-filament::button>
                    </div>
                </div>

                {{-- Step 22: 緊急差戻し（管理者のみ） --}}
                @if (($this->closingState?->status ?? '') === 'closed')
                    <div class="mt-6 p-4 rounded-lg bg-amber-50 dark:bg-amber-950/30 border border-amber-200 dark:border-amber-800">
                        <p class="text-sm font-medium text-amber-800 dark:text-amber-200 mb-2">緊急差戻し（管理者のみ）</p>
                        <textarea wire:model="reopenReason" rows="2" placeholder="差戻し理由（5文字以上、操作ログに記録されます）"
                            class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 text-sm"></textarea>
                        <div class="mt-2">
                            <x-filament::button wire:click="reopenMonth" color="warning" icon="heroicon-o-arrow-uturn-left">
                                締めを差戻す（Reopen）
                            </x-filament::button>
                        </div>
                    </div>
                @endif
            </div>
        @endif
    </div>
</x-filament-panels::page>
