@props(['disabled' => false])

<input @disabled($disabled) {{ $attributes->merge(['class' => 'border-marca-linea focus:border-marca-oliva focus:ring-marca-oliva rounded-md shadow-sm']) }}>
