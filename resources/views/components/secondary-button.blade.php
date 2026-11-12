<button {{ $attributes->merge(['type' => 'button', 'class' => 'inline-flex items-center justify-center gap-2 rounded-full border-2 border-marca-oliva bg-white px-6 py-2 font-display text-sm font-bold text-marca-oliva transition hover:bg-marca-oliva hover:text-white focus:outline-none focus-visible:ring-4 focus-visible:ring-marca-lima/50 disabled:opacity-25']) }}>
    {{ $slot }}
</button>
