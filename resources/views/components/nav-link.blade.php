@props(['active'])

@php
$classes = ($active ?? false)
            ? 'inline-flex items-center whitespace-nowrap px-1 pt-1 border-b-2 border-marca-lima text-sm font-semibold leading-5 text-marca-noche focus:outline-none focus:border-marca-oliva transition duration-150 ease-in-out'
            : 'inline-flex items-center whitespace-nowrap px-1 pt-1 border-b-2 border-transparent text-sm font-medium leading-5 text-marca-suave hover:text-marca-noche hover:border-marca-linea focus:outline-none focus:text-marca-noche focus:border-marca-linea transition duration-150 ease-in-out';
@endphp

<a {{ $attributes->merge(['class' => $classes]) }}>
    {{ $slot }}
</a>
