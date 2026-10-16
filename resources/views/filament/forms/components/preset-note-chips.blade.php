<div x-data="{
    noteField: '<?php echo e($noteField); ?>',
    presetNotes: <?php echo json_encode($presetNotes ?? [], JSON_UNESCAPED_UNICODE); ?>,
    setNote(value) {
        const current = this.$wire.get(this.noteField) || '';
        const separator = current && !current.endsWith(' ') ? ' ' : '';
        this.$wire.set(this.noteField, current + separator + value);
    }
}">
    <div class="mt-2 flex flex-wrap gap-1.5" x-show="presetNotes.length > 0">
        <span class="text-xs text-gray-500 dark:text-gray-400 mr-2 self-center">定型備考:</span>
        <template x-for="note in presetNotes" :key="note">
            <button
                type="button"
                @click="setNote(note)"
                class="inline-flex items-center px-2.5 py-1 text-xs font-medium rounded-lg bg-primary-50 dark:bg-primary-900/30 text-primary-700 dark:text-primary-300 hover:bg-primary-100 dark:hover:bg-primary-900/50 transition-colors"
                wire:loading.attr="disabled"
            >
                @{{ note }}
            </button>
        </template>
    </div>
</div>