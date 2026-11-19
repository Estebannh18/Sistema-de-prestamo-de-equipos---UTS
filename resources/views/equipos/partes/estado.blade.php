{{-- Estado del equipo o espacio. Si está prestado no se cambia aquí: cambia al registrar la devolución. --}}
<div class="{{ $clase ?? '' }}">
    <x-input-label for="estado" value="Estado" />
    @if ($equipo?->estado === 'prestado')
        <p class="mt-1 text-sm text-marca-suave">Prestado: cambia solo al registrar la devolución.</p>
    @else
        <select id="estado" name="estado" class="mt-1 block w-full campo-uts" required>
            @foreach (['disponible', 'mantenimiento'] as $opcion)
                <option value="{{ $opcion }}" @selected(old('estado', $equipo?->estado ?? 'disponible') === $opcion)>{{ $opcion }}</option>
            @endforeach
        </select>
    @endif
    <x-input-error :messages="$errors->get('estado')" class="mt-2" />
</div>
