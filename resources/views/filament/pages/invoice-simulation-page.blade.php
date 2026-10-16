<x-filament-panels::page>
    <div class="space-y-6">
        <x-help-panel title="請求シミュレーションについて">
            <p>入居日や退去日、家賃設定を変更した場合の請求金額をデータベースに保存せずに即座に試算できます。</p>
            <p>※ 実際の請求データ（MonthlyInvoice）は一切作成されません。</p>
        </x-help-panel>

        <form wire:submit="simulate">
            {{ $this->form }}

            <div class="mt-4 flex justify-end">
                <x-filament::button type="submit" color="primary" icon="heroicon-o-calculator" size="lg">
                    試算を実行する
                </x-filament::button>
            </div>
        </form>

        @if ($hasCalculated)
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow border border-gray-200 dark:border-gray-700 p-6 space-y-4">
                <h3 class="text-lg font-bold text-gray-900 dark:text-white border-b border-gray-200 dark:border-gray-700 pb-2">
                    試算結果
                </h3>

                <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                    <div class="bg-gray-50 dark:bg-gray-900 p-4 rounded-lg">
                        <span class="text-xs text-gray-500">在籍日数</span>
                        <div class="text-xl font-bold text-gray-900 dark:text-white">{{ $resultLivingDays }} / {{ $resultDaysInMonth }} 日</div>
                    </div>
                    <div class="bg-gray-50 dark:bg-gray-900 p-4 rounded-lg">
                        <span class="text-xs text-gray-500">試算家賃</span>
                        <div class="text-xl font-bold text-gray-900 dark:text-white">¥{{ number_format($resultRent) }}</div>
                    </div>
                    <div class="bg-gray-50 dark:bg-gray-900 p-4 rounded-lg">
                        <span class="text-xs text-gray-500">試算管理費</span>
                        <div class="text-xl font-bold text-gray-900 dark:text-white">¥{{ number_format($resultManagementFee) }}</div>
                    </div>
                    <div class="bg-primary-50 dark:bg-primary-950/40 p-4 rounded-lg border border-primary-200 dark:border-primary-800">
                        <span class="text-xs font-semibold text-primary-700 dark:text-primary-300">合計試算額（税込）</span>
                        <div class="text-2xl font-bold text-primary-600 dark:text-primary-400">¥{{ number_format($resultTotalAmount) }}</div>
                    </div>
                </div>
            </div>
        @endif
    </div>
</x-filament-panels::page>
