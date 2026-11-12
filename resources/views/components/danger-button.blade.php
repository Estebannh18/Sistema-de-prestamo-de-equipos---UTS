<button {{ $attributes->merge(['type' => 'submit', 'class' => 'inline-flex items-center justify-center gap-2 rounded-full bg-rose-700 px-6 py-2.5 font-display text-sm font-bold text-white shadow-[0_4px_0_0_#881337] transition hover:-translate-y-0.5 hover:bg-rose-800 focus:outline-none focus-visible:ring-4 focus-visible:ring-rose-300']) }}>
    {{ $slot }}
</button>
