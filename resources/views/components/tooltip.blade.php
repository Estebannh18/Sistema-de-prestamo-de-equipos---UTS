@props(['texto', 'alinear' => 'centro'])

{{--
    Texto de ayuda que aparece encima del elemento al pasar el mouse o al enfocarlo con el teclado.
    El botón que va dentro debe tener su propio aria-label: el tooltip es solo visual.
    alinear="derecha" lo pega al borde derecho del elemento, para los que están al final de una fila
    y, centrados, se saldrían del contenedor.
--}}
@php
    $posicion = $alinear === 'derecha' ? 'right-0' : 'left-1/2 -translate-x-1/2';
    $flecha = $alinear === 'derecha' ? 'right-3' : 'left-1/2 -translate-x-1/2';
@endphp
<span class="group relative inline-flex">
    {{ $slot }}
    <span aria-hidden="true" class="pointer-events-none absolute bottom-full {{ $posicion }} z-20 mb-2 translate-y-1 whitespace-nowrap rounded-lg bg-marca-noche px-2.5 py-1.5 font-display text-xs font-bold text-white opacity-0 shadow-lg transition duration-150 group-hover:translate-y-0 group-hover:opacity-100 group-focus-within:translate-y-0 group-focus-within:opacity-100">
        {{ $texto }}
        <span class="absolute {{ $flecha }} top-full border-[5px] border-transparent border-t-marca-noche"></span>
    </span>
</span>
