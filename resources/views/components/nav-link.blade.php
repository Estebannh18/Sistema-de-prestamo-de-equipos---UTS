@props(['active'])

@php
$classes = ($active ?? false)
            ? 'inline-flex items-center gap-2 whitespace-nowrap rounded-full bg-marca-lima px-4 py-2 font-display text-sm font-bold text-marca-noche shadow-[0_3px_0_0_#7b8305] focus:outline-none transition'
            : 'inline-flex items-center gap-2 whitespace-nowrap rounded-full px-4 py-2 font-display text-sm font-semibold text-white/85 hover:bg-white/10 hover:text-white focus:outline-none focus-visible:ring-2 focus-visible:ring-marca-lima transition';
@endphp

<a {{ $attributes->merge(['class' => $classes]) }}>
    {{ $slot }}
</a>
