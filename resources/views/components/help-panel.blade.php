@props([
    'title' => 'この画面の使い方',
    'collapsed' => false,
])

<div x-data="{ open: ! {{ $collapsed ? 'true' : 'false' }} }" class="mb-4 bg-blue-50 dark:bg-blue-950/30 border border-blue-200 dark:border-blue-800 rounded-xl overflow-hidden shadow-sm transition-all">
    <button type="button" @click="open = !open" class="w-full px-4 py-3 flex items-center justify-between text-left font-semibold text-blue-900 dark:text-blue-100 hover:bg-blue-100/50 dark:hover:bg-blue-900/40">
        <div class="flex items-center space-x-2">
            <svg class="w-5 h-5 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <span>{{ $title }}</span>
        </div>
        <svg :class="open ? 'rotate-180' : ''" class="w-4 h-4 text-blue-600 dark:text-blue-400 transition-transform duration-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
        </svg>
    </button>
    <div x-show="open" x-cloak class="px-4 pb-4 pt-1 text-sm text-blue-950 dark:text-blue-200 space-y-2 border-t border-blue-200/50 dark:border-blue-850">
        {{ $slot }}
    </div>
</div>
