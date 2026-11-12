@props(['nombre'])

@php
    // Iconos de trazo (24x24). Se dibujan con el color del texto.
    $trazos = [
        'inicio' => 'M3 10.5 12 3l9 7.5M5 9v11a1 1 0 0 0 1 1h4v-6h4v6h4a1 1 0 0 0 1-1V9',
        'usuarios' => 'M16 19v-1.5a3.5 3.5 0 0 0-3.5-3.5h-5A3.5 3.5 0 0 0 4 17.5V19M10 10.5a3 3 0 1 0 0-6 3 3 0 0 0 0 6ZM20 19v-1.5a3.5 3.5 0 0 0-2.5-3.35M15 4.65a3 3 0 0 1 0 5.7',
        'categorias' => 'M4 4h6v6H4zM14 4h6v6h-6zM4 14h6v6H4zM14 17h6M17 14v6',
        'equipos' => 'M4 5h16v10H4zM2 19h20M10 15v4M14 15v4',
        'prestamos' => 'M8 4h8l1 2h2a1 1 0 0 1 1 1v13a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V7a1 1 0 0 1 1-1h2zM8 12h8M8 16h5',
        'solicitud' => 'M14 3H6a1 1 0 0 0-1 1v16a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V8zM14 3v5h5M12 11v6M9 14h6',
        'reporte' => 'M4 20V10M10 20V4M16 20v-7M22 20H2',
        'seguridad' => 'M12 3 4.5 6v5.5c0 4.6 3.2 8.4 7.5 9.5 4.3-1.1 7.5-4.9 7.5-9.5V6zM9 12l2 2 4-4',
        'perfil' => 'M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8ZM4 21a8 8 0 0 1 16 0',
        'salir' => 'M15 4h3a1 1 0 0 1 1 1v14a1 1 0 0 1-1 1h-3M10 16l4-4-4-4M14 12H4',
        'buscar' => 'M11 18a7 7 0 1 0 0-14 7 7 0 0 0 0 14ZM20 20l-4-4',
        'mas' => 'M12 5v14M5 12h14',
        'editar' => 'M4 20h4L19 9l-4-4L4 16zM14 6l4 4',
        'eliminar' => 'M4 7h16M10 11v6M14 11v6M6 7l1 13h10l1-13M9 7V4h6v3',
        'ver' => 'M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12ZM12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6Z',
        'menu' => 'M4 6h16M4 12h16M4 18h16',
        'cerrar' => 'M6 6l12 12M18 6 6 18',
        'abajo' => 'm6 9 6 6 6-6',
        'derecha' => 'm9 6 6 6-6 6',
        'izquierda' => 'm15 6-6 6 6 6',
        'calendario' => 'M4 6h16v14H4zM4 10h16M8 3v4M16 3v4',
        'reloj' => 'M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18ZM12 7v5l3 2',
        'check' => 'm5 12 5 5L20 7',
        'alerta' => 'M12 9v4M12 17h.01M10.3 3.9 2.4 18a2 2 0 0 0 1.7 3h15.8a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0Z',
        'info' => 'M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18ZM12 11v5M12 8h.01',
        'filtro' => 'M4 5h16l-6 7v6l-4 2v-8z',
        'herramienta' => 'M14.7 6.3a4 4 0 0 0-5.4 5.4L4 17l3 3 5.3-5.3a4 4 0 0 0 5.4-5.4l-2.5 2.5-2.4-.6-.6-2.4z',
        'devolucion' => 'M9 14 4 9l5-5M4 9h11a5 5 0 0 1 0 10h-3',
        'salon' => 'M3 21h18M5 21V5a1 1 0 0 1 1-1h12a1 1 0 0 1 1 1v16M9 8h2M13 8h2M9 12h2M13 12h2M10 21v-4h4v4',
        'llave' => 'M15 9a3 3 0 1 0 0 .01M13.5 11.5 4 21M7 18l2 2M9 16l2 2',
        'descargar' => 'M12 4v11M7 10l5 5 5-5M5 20h14',
    ];
@endphp

<svg {{ $attributes->has('class') ? $attributes : $attributes->merge(['class' => 'h-5 w-5']) }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
    <path d="{{ $trazos[$nombre] ?? $trazos['info'] }}" />
</svg>
