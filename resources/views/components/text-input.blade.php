@props(['disabled' => false])

<input @disabled($disabled) {{ $attributes->merge(['class' => 'campo-uts']) }}>
