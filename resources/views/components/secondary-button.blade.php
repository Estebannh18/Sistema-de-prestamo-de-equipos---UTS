<button {{ $attributes->merge(['type' => 'button', 'class' => 'inline-flex items-center px-5 py-2.5 bg-white border border-marca-linea rounded-md font-semibold text-sm text-marca-noche hover:bg-marca-crema focus:outline-none focus:ring-2 focus:ring-marca-oliva focus:ring-offset-2 disabled:opacity-25 transition']) }}>
    {{ $slot }}
</button>
