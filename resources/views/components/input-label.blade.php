@props(['value'])

<label {{ $attributes->merge(['class' => 'block font-display font-bold text-sm text-marca-noche']) }}>
    {{ $value ?? $slot }}
</label>
