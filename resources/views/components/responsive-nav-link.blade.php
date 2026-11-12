@props(['active'])

@php
$classes = ($active ?? false)
            ? 'flex w-full items-center gap-3 rounded-xl bg-marca-lima px-4 py-2.5 text-start font-display text-base font-bold text-marca-noche focus:outline-none transition'
            : 'flex w-full items-center gap-3 rounded-xl px-4 py-2.5 text-start font-display text-base font-semibold text-white/85 hover:bg-white/10 hover:text-white focus:outline-none transition';
@endphp

<a {{ $attributes->merge(['class' => $classes]) }}>
    {{ $slot }}
</a>
