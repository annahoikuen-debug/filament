@props([
    'word' => '',
])

@php
    $definition = config('glossary.' . $word);
@endphp

@if ($definition)
    <abbr title="{{ $definition }}" class="underline decoration-dotted cursor-help decoration-primary-500 font-medium">
        {{ $slot->isNotEmpty() ? $slot : $word }}
    </abbr>
@else
    <span>{{ $slot->isNotEmpty() ? $slot : $word }}</span>
@endif
