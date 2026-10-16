<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">
            <div class="flex items-center space-x-2">
                <svg class="w-5 h-5 text-primary-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                </svg>
                <span>月次請求 異常値・整合性チェック ({{ $yearMonth }})</span>
            </div>
        </x-slot>

        <div class="space-y-4">
            <div class="grid grid-cols-2 gap-4">
                <div class="p-3 rounded-lg border {{ $errorCount > 0 ? 'bg-red-50 dark:bg-red-950/30 border-red-200' : 'bg-gray-50 dark:bg-gray-800 border-gray-200' }}">
                    <span class="text-xs text-gray-500">重大エラー</span>
                    <div class="text-xl font-bold {{ $errorCount > 0 ? 'text-red-600' : 'text-gray-700' }}">{{ $errorCount }} 件</div>
                </div>
                <div class="p-3 rounded-lg border {{ $warningCount > 0 ? 'bg-yellow-50 dark:bg-yellow-950/30 border-yellow-200' : 'bg-gray-50 dark:bg-gray-800 border-gray-200' }}">
                    <span class="text-xs text-gray-500">確認推奨 (警告)</span>
                    <div class="text-xl font-bold {{ $warningCount > 0 ? 'text-yellow-600' : 'text-gray-700' }}">{{ $warningCount }} 件</div>
                </div>
            </div>

            @if ($errorCount === 0 && $warningCount === 0)
                <div class="p-3 text-sm text-green-700 bg-green-50 dark:bg-green-950/30 rounded-lg">
                    ✓ 検知された異常値はありません。請求整合性は良好です。
                </div>
            @else
                <div class="space-y-2">
                    <p class="text-xs font-semibold text-gray-500">主な検知事項（最大5件）:</p>
                    @foreach ($topIssues as $issue)
                        <div class="text-xs p-2 rounded bg-gray-50 dark:bg-gray-900 border border-gray-200 dark:border-gray-700">
                            <span class="font-bold text-gray-800 dark:text-gray-200">[{{ $issue['rule_label'] ?? '警告' }}]</span>
                            <span class="text-gray-600 dark:text-gray-400">{{ $issue['message'] ?? '' }}</span>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
