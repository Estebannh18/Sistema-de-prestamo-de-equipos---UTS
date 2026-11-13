@php
    $esPropio = $esPropio ?? false;
    $rolInicial = old('rol', $usuario?->rol ?? '');
    $tipoInicial = old('tipo_documento', $usuario?->tipo_documento ?? 'CC');
    $programaInicial = old('programa_id', $usuario?->programa_id);
    $rolesConPrograma = \App\Http\Requests\UsuarioRequest::ROLES_CON_PROGRAMA;
    $conPrograma = in_array($rolInicial, $rolesConPrograma, true);
    $descripciones = [
        'administrador' => 'Gestiona usuarios, categorías, equipos y salones; atiende préstamos y consulta el reporte.',
        'funcionario' => 'Atiende solicitudes y devoluciones, y consulta equipos y el reporte.',
        'docente' => 'Pide equipos y salones habilitados para docentes y ve solo sus préstamos.',
        'estudiante' => 'Pide equipos y salones habilitados para estudiantes y ve solo sus préstamos.',
    ];
    $ayudasDocumento = [
        'CC' => 'Solo números, entre 6 y 10 dígitos.',
        'TI' => 'Solo números, de 10 u 11 dígitos. Solo para estudiantes.',
        'CE' => 'Solo números, entre 6 y 10 dígitos.',
        'PA' => 'Entre 6 y 12 letras o números, sin espacios.',
    ];
    $tituloSeccion = 'flex items-center gap-2 font-display text-base font-bold text-marca-noche';
    $iconoSeccion = 'flex h-8 w-8 items-center justify-center rounded-full bg-marca-limaclaro text-marca-oliva';
    $etiqueta = 'block font-display text-sm font-bold text-marca-noche';
    $ayuda = 'mt-1 text-xs text-marca-suave';
@endphp

<div x-data="{ rol: @js($rolInicial), conPrograma: @js($rolesConPrograma), descripciones: @js($descripciones), tipo: @js($tipoInicial), ayudas: @js($ayudasDocumento) }" class="space-y-8">
    <p class="text-sm text-marca-suave">Los campos marcados con <span class="font-bold text-rose-600">*</span> son obligatorios.</p>

    {{-- 1. Datos personales --}}
    <fieldset>
        <legend class="{{ $tituloSeccion }}">
            <span class="{{ $iconoSeccion }}"><x-icono nombre="perfil" class="h-4 w-4" /></span>
            Datos personales
        </legend>

        <div class="mt-4 grid gap-x-6 gap-y-5 lg:grid-cols-2">
            <div>
                <label for="name" class="{{ $etiqueta }}">Nombre completo<x-obligatorio /></label>
                <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" :value="old('name', $usuario?->name)" required maxlength="100" autocomplete="name" />
                <p class="{{ $ayuda }}">Nombre y apellido, solo con letras.</p>
                <x-input-error :messages="$errors->get('name')" class="mt-2" />
            </div>

            <div>
                <label for="email" class="{{ $etiqueta }}">Correo<x-obligatorio /></label>
                <x-text-input id="email" name="email" type="email" class="mt-1 block w-full" :value="old('email', $usuario?->email)" required maxlength="255" autocomplete="email" />
                <p class="{{ $ayuda }}">Con él inicia sesión. No puede repetirse.</p>
                <x-input-error :messages="$errors->get('email')" class="mt-2" />
            </div>

            <div>
                <div class="grid gap-4 sm:grid-cols-[minmax(0,15rem)_1fr]">
                    <div>
                        <label for="tipo_documento" class="{{ $etiqueta }}">Tipo de documento<x-obligatorio /></label>
                        <select id="tipo_documento" name="tipo_documento" class="mt-1 block w-full campo-uts" required x-model="tipo">
                            @foreach ($tiposDocumento as $codigo => $nombre)
                                <option value="{{ $codigo }}" @selected($tipoInicial === $codigo)>{{ $nombre }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="documento" class="{{ $etiqueta }}">Número de documento<x-obligatorio /></label>
                        <x-text-input id="documento" name="documento" type="text" class="mt-1 block w-full" :value="old('documento', $usuario?->documento)" required maxlength="20" autocomplete="off" x-bind:inputmode="tipo === 'PA' ? 'text' : 'numeric'" />
                    </div>
                </div>
                <p class="{{ $ayuda }}"><span x-text="ayudas[tipo]">Solo números.</span> Si escribes puntos o espacios, se quitan solos. El número no puede repetirse.</p>
                <x-input-error :messages="$errors->get('tipo_documento')" class="mt-2" />
                <x-input-error :messages="$errors->get('documento')" class="mt-2" />
            </div>

            <div>
                <label for="telefono" class="{{ $etiqueta }}">Teléfono</label>
                <x-text-input id="telefono" name="telefono" type="tel" inputmode="numeric" class="mt-1 block w-full" :value="old('telefono', $usuario?->telefono)" maxlength="15" autocomplete="tel" />
                <p class="{{ $ayuda }}">Opcional. Fijo o celular, entre 7 y 10 dígitos, sin indicativo.</p>
                <x-input-error :messages="$errors->get('telefono')" class="mt-2" />
            </div>
        </div>
    </fieldset>

    {{-- 2. Rol en el sistema --}}
    <fieldset class="border-t border-marca-linea pt-6">
        <legend class="{{ $tituloSeccion }} float-left w-full">
            <span class="{{ $iconoSeccion }}"><x-icono nombre="usuarios" class="h-4 w-4" /></span>
            Rol en el sistema
        </legend>

        <div class="clear-both grid gap-x-6 gap-y-5 pt-4 lg:grid-cols-2">
            <div>
                @if ($esPropio)
                    <span class="{{ $etiqueta }}">Rol</span>
                    <input type="hidden" name="rol" value="{{ $usuario->rol }}">
                    <p class="mt-2 flex flex-wrap items-center gap-2 text-sm text-marca-suave">
                        <span class="insignia insignia-{{ $usuario->rol }}">{{ $usuario->rol }}</span>
                        No puedes cambiar tu propio rol. Si hace falta, pídeselo a otro administrador.
                    </p>
                @else
                    <label for="rol" class="{{ $etiqueta }}">Rol<x-obligatorio /></label>
                    <select id="rol" name="rol" class="mt-1 block w-full campo-uts" required x-model="rol">
                        <option value="" disabled @selected($rolInicial === '')>Elige un rol</option>
                        @foreach ($roles as $opcion)
                            <option value="{{ $opcion }}" @selected($rolInicial === $opcion)>{{ ucfirst($opcion) }}</option>
                        @endforeach
                    </select>
                    <p class="{{ $ayuda }}" x-show="rol" x-text="descripciones[rol]" x-cloak></p>
                    <p class="{{ $ayuda }}">Cada usuario tiene un solo rol.</p>
                @endif
                <x-input-error :messages="$errors->get('rol')" class="mt-2" />
            </div>

            {{-- Estudiantes y docentes eligen su programa de la lista oficial; el personal escribe su dependencia. --}}
            <div>
                <p class="{{ $ayuda }} lg:pt-6" x-show="! rol" @style(['display: none' => $rolInicial !== ''])>
                    Elige primero el rol: los estudiantes y docentes eligen su programa académico; el personal, su dependencia.
                </p>

                <div x-show="conPrograma.includes(rol)" @style(['display: none' => ! $conPrograma])>
                    <label for="programa_id" class="{{ $etiqueta }}">Programa académico<x-obligatorio /></label>
                    <select id="programa_id" name="programa_id" class="mt-1 block w-full campo-uts" x-bind:required="conPrograma.includes(rol)" x-bind:disabled="! conPrograma.includes(rol)" @disabled(! $conPrograma)>
                        <option value="">Elige un programa</option>
                        @foreach ($programas as $sede => $programasSede)
                            <optgroup label="{{ \App\Models\Programa::etiquetaSede($sede) }}">
                                @foreach ($programasSede as $programa)
                                    <option value="{{ $programa->id }}" @selected((string) $programaInicial === (string) $programa->id)>{{ $programa->nombre }}</option>
                                @endforeach
                            </optgroup>
                        @endforeach
                    </select>
                    <p class="{{ $ayuda }}">Las carreras de la oferta académica de las UTS, agrupadas por sede. Si la misma carrera está en varias sedes, elige la sede donde estudia o enseña.</p>
                    <x-input-error :messages="$errors->get('programa_id')" class="mt-2" />
                </div>

                <div x-show="rol && ! conPrograma.includes(rol)" @style(['display: none' => $rolInicial === '' || $conPrograma])>
                    <label for="dependencia" class="{{ $etiqueta }}">Dependencia</label>
                    <x-text-input id="dependencia" name="dependencia" type="text" class="mt-1 block w-full" :value="old('dependencia', $usuario?->dependencia)" maxlength="150" x-bind:disabled="conPrograma.includes(rol)" />
                    <p class="{{ $ayuda }}">Opcional. Oficina o área donde trabaja, por ejemplo «Almacén» o «Laboratorios».</p>
                    <x-input-error :messages="$errors->get('dependencia')" class="mt-2" />
                </div>
            </div>
        </div>
    </fieldset>

    {{-- 3. Contraseña --}}
    <fieldset class="border-t border-marca-linea pt-6">
        <legend class="{{ $tituloSeccion }} float-left w-full">
            <span class="{{ $iconoSeccion }}"><x-icono nombre="llave" class="h-4 w-4" /></span>
            {{ $usuario ? 'Cambiar contraseña' : 'Contraseña' }}
        </legend>

        <div class="clear-both pt-2">
            <p class="text-xs text-marca-suave">
                Mínimo 8 caracteres, con al menos una letra y un número. No puede ser igual al documento.
                @if ($usuario)
                    Déjala en blanco para conservar la actual.
                @endif
            </p>

            <div class="mt-4 grid gap-x-6 gap-y-5 lg:grid-cols-2">
                <div>
                    <label for="password" class="{{ $etiqueta }}">{{ $usuario ? 'Nueva contraseña' : 'Contraseña' }}<x-obligatorio :mostrar="! $usuario" /></label>
                    <x-text-input id="password" name="password" type="password" class="mt-1 block w-full" autocomplete="new-password" maxlength="72" :required="! $usuario" />
                    <x-input-error :messages="$errors->get('password')" class="mt-2" />
                </div>

                <div>
                    <label for="password_confirmation" class="{{ $etiqueta }}">Confirmar contraseña<x-obligatorio :mostrar="! $usuario" /></label>
                    <x-text-input id="password_confirmation" name="password_confirmation" type="password" class="mt-1 block w-full" autocomplete="new-password" maxlength="72" :required="! $usuario" />
                </div>
            </div>
        </div>
    </fieldset>
</div>
