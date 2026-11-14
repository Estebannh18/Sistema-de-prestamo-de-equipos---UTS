{{-- Campos de crear y editar noticia. Recibe $noticia (null al crear). --}}
@php
    $maxArchivos = \App\Models\Noticia::MAX_ADJUNTOS;
    $maxBytes = \App\Models\Noticia::MAX_KB_POR_ARCHIVO * 1024;
    $aceptados = collect([...\App\Models\Noticia::EXTENSIONES_IMAGEN, ...\App\Models\Noticia::EXTENSIONES_DOCUMENTO])->map(fn ($e) => ".{$e}")->implode(',');
    $actuales = $noticia?->adjuntos ?? collect();
    $quitarMarcados = array_map('intval', (array) old('quitar', []));
    $erroresArchivos = collect($errors->get('adjuntos'))->merge(collect($errors->get('adjuntos.*'))->flatten())->all();
    $etiqueta = 'block font-display text-sm font-bold text-marca-noche';
    $ayuda = 'mt-1 text-xs text-marca-suave';
@endphp

<div class="space-y-6"
     x-data="{
        texto: @js(old('contenido', $noticia?->contenido ?? '')),
        archivos: [],
        quitados: @js(count($quitarMarcados)),
        actuales: @js($actuales->count()),
        max: {{ $maxArchivos }},
        maxBytes: {{ $maxBytes }},
        elegir(evento) { this.archivos = Array.from(evento.target.files).map(f => ({ nombre: f.name, tamano: f.size })) },
        tamano(bytes) { return bytes >= 1048576 ? (bytes / 1048576).toFixed(1).replace('.', ',') + ' MB' : Math.max(1, Math.round(bytes / 1024)) + ' KB' },
        get total() { return this.actuales - this.quitados + this.archivos.length },
     }">
    <p class="text-sm text-marca-suave">Los campos marcados con <span class="font-bold text-rose-600">*</span> son obligatorios.</p>

    <div>
        <label for="titulo" class="{{ $etiqueta }}">Título<x-obligatorio /></label>
        <x-text-input id="titulo" name="titulo" type="text" class="mt-1 block w-full" :value="old('titulo', $noticia?->titulo)" required minlength="5" maxlength="150" />
        <p class="{{ $ayuda }}">Corto y claro. Por ejemplo: «Sala de informática 301C cerrada del 18 al 22 de noviembre».</p>
        <x-input-error :messages="$errors->get('titulo')" class="mt-2" />
    </div>

    <div>
        <label for="contenido" class="{{ $etiqueta }}">Texto de la noticia<x-obligatorio /></label>
        <textarea id="contenido" name="contenido" rows="8" class="campo-uts mt-1 block w-full" required minlength="10" maxlength="5000" x-model="texto">{{ old('contenido', $noticia?->contenido) }}</textarea>
        <div class="mt-1 flex flex-wrap justify-between gap-2 text-xs text-marca-suave">
            <p>Los saltos de línea se respetan tal como los escribas.</p>
            <p x-cloak><span x-text="texto.length"></span> / 5000</p>
        </div>
        <x-input-error :messages="$errors->get('contenido')" class="mt-2" />
    </div>

    @if ($actuales->isNotEmpty())
        <fieldset>
            <legend class="{{ $etiqueta }}">Archivos que ya tiene</legend>
            <p class="{{ $ayuda }}">Marca los que quieras quitar. Se borran al guardar los cambios.</p>
            <ul class="mt-3 grid gap-3 sm:grid-cols-2">
                @foreach ($actuales as $adjunto)
                    <li>
                        <label class="flex cursor-pointer items-center gap-3 rounded-2xl border border-marca-linea bg-marca-crema/60 px-3 py-2.5 transition has-[:checked]:border-rose-300 has-[:checked]:bg-rose-50">
                            @if ($adjunto->esImagen())
                                <img src="{{ route('noticias.adjunto', $adjunto) }}" alt="" class="h-12 w-12 shrink-0 rounded-xl object-cover">
                            @else
                                <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-marca-bosque text-white"><x-icono nombre="documento" class="h-5 w-5" /></span>
                            @endif
                            <span class="min-w-0 flex-1">
                                <span class="block truncate text-sm font-bold text-marca-noche">{{ $adjunto->nombre_original }}</span>
                                <span class="text-xs text-marca-suave">{{ $adjunto->extension() }} · {{ $adjunto->tamanoLegible() }}</span>
                            </span>
                            <span class="flex shrink-0 items-center gap-1.5 text-xs font-bold text-rose-700">
                                <input type="checkbox" name="quitar[]" value="{{ $adjunto->id }}" class="rounded border-rose-300 text-rose-700 focus:ring-rose-300"
                                       @checked(in_array($adjunto->id, $quitarMarcados, true)) x-on:change="quitados += $event.target.checked ? 1 : -1">
                                Quitar
                            </span>
                        </label>
                    </li>
                @endforeach
            </ul>
        </fieldset>
    @endif

    <div>
        <label for="adjuntos" class="{{ $etiqueta }}">{{ $actuales->isNotEmpty() ? 'Agregar fotos o documentos' : 'Fotos o documentos' }}</label>
        <input id="adjuntos" name="adjuntos[]" type="file" multiple accept="{{ $aceptados }}" x-on:change="elegir($event)"
               class="mt-1 block w-full rounded-xl border-2 border-dashed border-marca-linea bg-marca-crema/40 p-3 text-sm text-marca-noche file:mr-4 file:rounded-full file:border-0 file:bg-marca-oliva file:px-5 file:py-2 file:font-display file:text-sm file:font-bold file:text-white hover:file:bg-marca-bosque focus:border-marca-oliva focus:outline-none focus:ring-4 focus:ring-marca-lima/40">
        <p class="{{ $ayuda }}">
            Opcional: la noticia puede ser solo texto. Máximo {{ $maxArchivos }} archivos en total, de hasta 5 MB cada uno.
            Fotos JPG, PNG o WEBP; documentos PDF, Word, Excel o PowerPoint. Para elegir varios a la vez, mantén presionada la tecla Ctrl.
        </p>

        {{-- Vista previa de lo que se eligió, con avisos antes de enviar. El servidor vuelve a revisarlo todo. --}}
        <ul class="mt-3 space-y-1.5 text-sm" x-show="archivos.length" x-cloak>
            <template x-for="archivo in archivos" :key="archivo.nombre">
                <li class="flex items-center gap-2" :class="archivo.tamano > maxBytes ? 'text-rose-700' : 'text-marca-noche'">
                    <x-icono nombre="clip" class="h-4 w-4 shrink-0" />
                    <span class="truncate" x-text="archivo.nombre"></span>
                    <span class="shrink-0 text-xs text-marca-suave" x-text="tamano(archivo.tamano)"></span>
                    <span class="shrink-0 text-xs font-bold" x-show="archivo.tamano > maxBytes">· pesa más de 5 MB</span>
                </li>
            </template>
        </ul>
        <p class="mt-2 text-sm font-bold text-rose-700" x-show="total > max" x-cloak>
            Son <span x-text="total"></span> archivos y el máximo es {{ $maxArchivos }}. Quita alguno antes de guardar.
        </p>

        <x-input-error :messages="$erroresArchivos" class="mt-2" />
        @if ($errors->any())
            {{-- Por seguridad, el navegador no conserva los archivos elegidos cuando el formulario vuelve con errores. --}}
            <p class="mt-2 text-xs font-bold text-marca-oliva">Si habías elegido archivos, vuelve a elegirlos: no se guardan cuando el formulario tiene errores.</p>
        @endif
    </div>
</div>
