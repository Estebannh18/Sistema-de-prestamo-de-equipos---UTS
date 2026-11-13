@props(['mostrar' => true])

{{-- Asterisco rojo para marcar un campo obligatorio. Los lectores de pantalla leen «obligatorio». --}}
@if ($mostrar)<span {{ $attributes->merge(['class' => 'ms-0.5 text-rose-600']) }} aria-hidden="true">*</span><span class="sr-only"> (obligatorio)</span>@endif
