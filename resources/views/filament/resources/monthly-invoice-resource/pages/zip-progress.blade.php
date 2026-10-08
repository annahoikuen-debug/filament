<x-filament-panels::page>
    <div class="max-w-2xl mx-auto">
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-gray-900">ZIP生成進捗</h1>
            <p class="text-gray-600 mt-1">ジョブID: {{ $jobId }}</p>
        </div>

        <div class="bg-white rounded-lg shadow p-6">
            <div class="mb-4">
                <div class="flex justify-between text-sm mb-2">
                    <span class="font-medium text-gray-700">{{ $progress['message'] }}</span>
                    <span class="font-mono text-gray-900">{{ $progress['percent'] }}%</span>
                </div>
                <div class="w-full bg-gray-200 rounded-full h-3">
                    <div class="bg-blue-600 h-3 rounded-full transition-all duration-300"
                         style="width: {{ $progress['percent'] }}%"></div>
                </div>
            </div>

            <div class="text-sm text-gray-500 mb-4">
                ステータス:
                <span class="font-medium ml-2"
                      :class="{
                          'text-blue-600': progress.status === 'processing',
                          'text-green-600': progress.status === 'completed',
                          'text-red-600': progress.status === 'failed',
                          'text-gray-600': progress.status === 'starting',
                      }">
                    {{ $progress['status'] }}
                </span>
                @if($progress['updated_at'])
                    <span class="ml-4">更新: {{ \Carbon\Carbon::parse($progress['updated_at'])->format('H:i:s') }}</span>
                @endif
            </div>

            @if($isFailed())
                <div class="bg-red-50 border border-red-200 rounded-lg p-4 mb-4">
                    <p class="text-red-700">エラーが発生しました: {{ $progress['message'] }}</p>
                    <x-filament::button wire:click="$refresh" class="mt-2" color="danger">
                        再試行
                    </x-filament::button>
                </div>
            @elseif($isCompleted())
                <div class="bg-green-50 border border-green-200 rounded-lg p-4 mb-4">
                    <p class="text-green-700 font-medium">ZIP生成が完了しました！</p>
                    <x-filament::button wire:click="$refresh" class="mt-2" color="success" icon="heroicon-o-arrow-down-tray" :href="route('invoices.zip-progress', ['jobId' => $jobId])">
                        ZIPファイルをダウンロード
                    </x-filament::button>
                </div>
            @else
                <div class="text-center text-gray-500">
                    <svg class="animate-spin h-8 w-8 mx-auto text-blue-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    <p class="mt-2">処理中です... ページを離れずにお待ちください</p>
                </div>
            @endif
        </div>

        <x-filament::button wire:click="$refresh" class="mt-4" variant="secondary">
            進捗を再読み込み
        </x-filament::button>
    </div>

    <script>
        // Auto-refresh every 2 seconds while processing
        document.addEventListener('livewire:load', () => {
            const interval = setInterval(() => {
                if (window.Livewire) {
                    const component = @this;
                    if (component.progress.status === 'processing' || component.progress.status === 'starting') {
                        component.loadProgress();
                    } else {
                        clearInterval(interval);
                    }
                }
            }, 2000);
        });
    </script>
</x-filament-panels::page>