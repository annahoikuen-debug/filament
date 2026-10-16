{{--
  Monthly Invoice Card View for Responsive Table
  Displays essential invoice information in a card layout
--}}
<div class="bg-white dark:bg-gray-800 rounded-lg shadow sm:p-4 p-3 mb-4 border border-gray-200 dark:border-gray-700">
    <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between">
        <div class="sm:mb-2">
            <div class="flex items-center space-x-2">
                <div class="flex-shrink-0">
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 dark:bg-gray-800 text-gray-800 dark:text-gray-200">
                        {{ $resident->room_number }}
                    </span>
                </div>
                <div>
                    <p class="text-sm font-medium text-gray-900 dark:text-white truncate max-w-xs">
                        {{ $resident->name }}
                    </p>
                </div>
            </div>
        </div>

        <div class="sm:ml-4 sm:border-l sm:border-gray-700 sm:pl-4 sm:mt-0 mt-2">
            <span class="px-2.5 py-0.5 rounded text-xs font-medium 
                {{ $status === 'Unbilled' ? 'bg-yellow-100 dark:bg-yellow-900 text-yellow-800' : 
                  (status === 'Billed' ? 'bg-blue-100 dark:bg-blue-900 text-blue-800' : 
                  (status === 'Paid' ? 'bg-green-100 dark:bg-green-900 text-green-800' : 
                  'bg-gray-100 dark:bg-gray-800 text-gray-800')) 
                }}">
                {{ ucfirst(__('filament-panels::resources/pages/list_monthly_invoices.fields.status.options.' . $status)) }}
            </span>
        </div>
    </div>

    <div class="mt-3 pt-2 border-t border-gray-200 dark:border-gray-700">
        <div class="flex flex-col sm:flex-row sm:justify-between sm:text-base">
            <div class="mb-2 sm:mb-0">
                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">請求年月</p>
                <p class="text-sm font-semibold text-gray-900 dark:text-white">{{ $billing_year_month }}</p>
            </div>
            <div class="mb-2 sm:mb-0">
                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">合計金額</p>
                <p class="text-sm font-semibold text-emerald-600 dark:text-emerald-400">¥{{ number_format($total_amount) }}</p>
            </div>
        </div>
    </div>

    @if ($paid_at)
        <div class="mt-2 pt-2 border-t border-gray-200 dark:border-gray-700">
            <p class="text-sm font-medium text-gray-500 dark:text-gray-400">入金日</p>
            <p class="text-sm font-semibold text-gray-900 dark:text-white">{{ $paid_at }}</p>
        </div>
    @endif
</div>