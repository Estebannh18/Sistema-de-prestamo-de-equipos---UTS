<button {{ $attributes->merge(['type' => 'submit', 'class' => 'boton-principal text-sm']) }}>
    {{ $slot }}
</button>
