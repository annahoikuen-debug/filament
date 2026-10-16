<div x-data="{
    dateField: '<?php echo e($dateField); ?>',
    relativeDates: [
        { label: '今日', value: '<?php echo now()->format("Y-m-d"); ?>' },
        { label: '昨日', value: '<?php echo now()->subDay()->format("Y-m-d"); ?>' },
        { label: '一昨日', value: '<?php echo now()->subDays(2)->format("Y-m-d"); ?>' },
    ],
    setDate(value) {
        if (this.$wire) {
            this.$wire.set(this.dateField, value);
        }
    }
}">
    <div class="mt-2 flex flex-wrap gap-1.5" x-show="relativeDates.length > 0">
        <span class="text-xs text-gray-500 dark:text-gray-400 mr-2 self-center">クイック日付:</span>
        <template x-for="date in relativeDates" :key="date.value">
            <button
                type="button"
                @click="setDate(date.value)"
                class="inline-flex items-center px-2.5 py-1 text-xs font-medium rounded-lg bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-200 hover:bg-gray-200 dark:hover:bg-gray-700 transition-colors"
                wire:loading.attr="disabled"
            >
                @{{ date.label }}
            </button>
        </template>
    </div>
</div>