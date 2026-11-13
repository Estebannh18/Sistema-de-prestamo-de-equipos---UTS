<x-modulo titulo="Usuarios" :pendiente="false">
    @if (session('status'))
        <p class="aviso-ok"><x-icono nombre="check" class="h-5 w-5 shrink-0" />{{ session('status') }}</p>
    @endif
    @if (session('error'))
        <p class="aviso-error"><x-icono nombre="alerta" class="h-5 w-5 shrink-0" />{{ session('error') }}</p>
    @endif

    <div class="mb-5 flex flex-wrap items-end justify-between gap-4">
        <form method="GET" action="{{ route('usuarios.index') }}" class="flex flex-wrap items-end gap-3">
            <div>
                <x-input-label for="buscar" value="Nombre, correo o documento" />
                <x-text-input id="buscar" name="buscar" type="search" class="mt-1 block w-64 max-w-full" :value="$buscar" maxlength="100" />
            </div>
            <div>
                <x-input-label for="rol" value="Rol" />
                <select id="rol" name="rol" class="mt-1 block w-44 campo-uts">
                    <option value="">Todos</option>
                    @foreach ($roles as $opcion)
                        <option value="{{ $opcion }}" @selected($rolActual === $opcion)>{{ ucfirst($opcion) }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <x-input-label for="programa" value="Programa" />
                <select id="programa" name="programa" class="mt-1 block w-64 max-w-full campo-uts">
                    <option value="">Todos</option>
                    @foreach ($programas as $sede => $programasSede)
                        <optgroup label="{{ $sede }}">
                            @foreach ($programasSede as $programa)
                                <option value="{{ $programa->id }}" @selected($programaActual === $programa->id)>{{ $programa->nombre }}</option>
                            @endforeach
                        </optgroup>
                    @endforeach
                </select>
            </div>
            <div>
                <x-input-label for="estado" value="Estado" />
                <select id="estado" name="estado" class="mt-1 block w-40 campo-uts">
                    <option value="">Todos</option>
                    <option value="activos" @selected($estadoActual === 'activos')>Activos</option>
                    <option value="inactivos" @selected($estadoActual === 'inactivos')>Inactivos</option>
                </select>
            </div>
            <x-secondary-button type="submit">Filtrar</x-secondary-button>
            @if ($buscar !== '' || $rolActual || $programaActual || $estadoActual)
                <a class="enlace text-sm" href="{{ route('usuarios.index') }}">Limpiar</a>
            @endif
        </form>

        <a class="boton-principal text-sm" href="{{ route('usuarios.create') }}">Crear usuario</a>
    </div>

    <p class="mb-3 text-sm text-marca-suave">
        {{ $usuarios->total() }} {{ $usuarios->total() === 1 ? 'usuario' : 'usuarios' }}
        @if ($buscar !== '' || $rolActual || $programaActual || $estadoActual) con este filtro @endif
    </p>

    <table class="tabla">
        <thead>
            <tr>
                <th>Nombre</th>
                <th>Correo</th>
                <th>Documento</th>
                <th>Programa o dependencia</th>
                <th>Rol</th>
                <th>Acciones</th>
            </tr>
        </thead>
        @php
            // Clases de las acciones de cada fila, con los colores del diseño actual.
            $claseIcono = 'inline-flex h-9 w-9 items-center justify-center rounded-full border-2 bg-white transition focus:outline-none focus-visible:ring-4 focus-visible:ring-marca-lima/60';
            $claseSwitch = 'relative inline-flex h-6 w-11 shrink-0 items-center rounded-full transition focus:outline-none focus-visible:ring-4 focus-visible:ring-marca-lima/60';
            $claseBolita = 'inline-block h-5 w-5 rounded-full bg-white shadow transition';
        @endphp
        <tbody>
            @forelse ($usuarios as $usuario)
                @php($esPropio = $usuario->is(Auth::user()))
                <tr>
                    <td>
                        {{ $usuario->name }}
                        @if ($esPropio)
                            <span class="insignia ms-1">Tú</span>
                        @endif
                        @unless ($usuario->activo)
                            <span class="insignia insignia-rechazado ms-1">Inactivo</span>
                        @endunless
                    </td>
                    <td>{{ $usuario->email }}</td>
                    <td class="whitespace-nowrap"><span class="text-marca-suave" title="{{ $usuario->nombreTipoDocumento() }}">{{ $usuario->tipo_documento }}</span> {{ $usuario->documento }}</td>
                    <td>
                        @if ($usuario->programa)
                            {{ $usuario->programa->nombre }}
                            <span class="block text-xs text-marca-suave">{{ \App\Models\Programa::etiquetaSede($usuario->programa->sede) }}</span>
                        @else
                            {{ $usuario->dependencia ?? '—' }}
                        @endif
                    </td>
                    <td><span class="insignia insignia-{{ $usuario->rol }}">{{ $usuario->rol }}</span></td>
                    <td class="whitespace-nowrap">
                        <div class="flex items-center gap-3">
                            {{-- Interruptor de estado: verde = activo. Al pulsarlo se desactiva o se activa la cuenta. --}}
                            @if ($esPropio)
                                <x-tooltip texto="No puedes desactivar tu propia cuenta">
                                    <span role="switch" aria-checked="true" aria-disabled="true" tabindex="0" aria-label="Tu cuenta está activa; no puedes desactivarla"
                                          class="{{ $claseSwitch }} cursor-not-allowed bg-marca-oliva opacity-40">
                                        <span class="{{ $claseBolita }} translate-x-5"></span>
                                    </span>
                                </x-tooltip>
                            @elseif ($usuario->activo)
                                <form method="POST" action="{{ route('usuarios.desactivar', $usuario) }}" onsubmit="return confirm('¿Desactivar a {{ e($usuario->name) }}? Ya no podrá iniciar sesión.');">
                                    @csrf
                                    @method('PATCH')
                                    <x-tooltip texto="Activo · clic para desactivar">
                                        <button type="submit" role="switch" aria-checked="true" aria-label="Desactivar a {{ $usuario->name }}" class="{{ $claseSwitch }} bg-marca-oliva hover:bg-marca-bosque">
                                            <span class="{{ $claseBolita }} translate-x-5"></span>
                                        </button>
                                    </x-tooltip>
                                </form>
                            @else
                                <form method="POST" action="{{ route('usuarios.activar', $usuario) }}">
                                    @csrf
                                    @method('PATCH')
                                    <x-tooltip texto="Inactivo · clic para activar">
                                        <button type="submit" role="switch" aria-checked="false" aria-label="Activar a {{ $usuario->name }}" class="{{ $claseSwitch }} bg-slate-300 hover:bg-slate-400">
                                            <span class="{{ $claseBolita }} translate-x-0.5"></span>
                                        </button>
                                    </x-tooltip>
                                </form>
                            @endif

                            <x-tooltip texto="Editar usuario">
                                <a href="{{ route('usuarios.edit', $usuario) }}" aria-label="Editar a {{ $usuario->name }}"
                                   class="{{ $claseIcono }} border-marca-oliva text-marca-oliva hover:bg-marca-oliva hover:text-white">
                                    <x-icono nombre="editar" class="h-4 w-4" />
                                </a>
                            </x-tooltip>

                            @if ($esPropio)
                                <x-tooltip texto="No puedes eliminar tu propia cuenta">
                                    <span role="button" aria-disabled="true" tabindex="0" aria-label="No puedes eliminar tu propia cuenta"
                                          class="{{ $claseIcono }} cursor-not-allowed border-rose-200 text-rose-300">
                                        <x-icono nombre="eliminar" class="h-4 w-4" />
                                    </span>
                                </x-tooltip>
                            @else
                                <form method="POST" action="{{ route('usuarios.destroy', $usuario) }}" onsubmit="return confirm('¿Eliminar a {{ e($usuario->name) }}? Esta acción no se puede deshacer.');">
                                    @csrf
                                    @method('DELETE')
                                    <x-tooltip texto="Eliminar usuario">
                                        <button type="submit" aria-label="Eliminar a {{ $usuario->name }}"
                                                class="{{ $claseIcono }} border-rose-300 text-rose-700 hover:border-rose-700 hover:bg-rose-700 hover:text-white">
                                            <x-icono nombre="eliminar" class="h-4 w-4" />
                                        </button>
                                    </x-tooltip>
                                </form>
                            @endif
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="6">No hay usuarios que coincidan con la búsqueda.</td></tr>
            @endforelse
        </tbody>
    </table>

    @if ($usuarios->hasPages())
        <nav class="mt-5 flex flex-wrap items-center justify-between gap-3" aria-label="Páginas de usuarios">
            <p class="text-sm text-marca-suave">Página {{ $usuarios->currentPage() }} de {{ $usuarios->lastPage() }}</p>
            @php($botonPagina = 'inline-flex items-center justify-center gap-2 rounded-full border-2 border-marca-oliva bg-white px-6 py-2 font-display text-sm font-bold text-marca-oliva transition hover:bg-marca-oliva hover:text-white focus:outline-none focus-visible:ring-4 focus-visible:ring-marca-lima/50')
            <div class="flex gap-3">
                @if ($usuarios->onFirstPage())
                    <x-secondary-button disabled>Anterior</x-secondary-button>
                @else
                    <a class="{{ $botonPagina }}" href="{{ $usuarios->previousPageUrl() }}" rel="prev">Anterior</a>
                @endif
                @if ($usuarios->hasMorePages())
                    <a class="{{ $botonPagina }}" href="{{ $usuarios->nextPageUrl() }}" rel="next">Siguiente</a>
                @else
                    <x-secondary-button disabled>Siguiente</x-secondary-button>
                @endif
            </div>
        </nav>
    @endif

    <section class="mt-8" aria-labelledby="permisos-titulo">
        <h2 id="permisos-titulo" class="font-display text-lg font-bold text-marca-noche">Qué puede hacer cada rol</h2>
        <p class="mt-1 text-sm text-marca-suave">Cada usuario tiene un solo rol. El rol define sus permisos en todo el sistema.</p>
        <div class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            @foreach ([
                'administrador' => ['Gestiona usuarios y sus roles', 'Gestiona categorías, equipos y salones', 'Atiende solicitudes y devoluciones', 'Consulta el reporte'],
                'funcionario' => ['Consulta equipos y salones', 'Atiende solicitudes y devoluciones', 'Consulta el reporte'],
                'docente' => ['Pide equipos y salones habilitados para docentes', 'Ve solo sus préstamos'],
                'estudiante' => ['Pide equipos y salones habilitados para estudiantes', 'Ve solo sus préstamos'],
            ] as $nombreRol => $permisos)
                <div class="rounded-2xl border border-marca-linea bg-marca-crema/60 p-4">
                    <span class="insignia insignia-{{ $nombreRol }}">{{ $nombreRol }}</span>
                    <ul class="mt-3 space-y-1.5 text-sm text-marca-tinta">
                        @foreach ($permisos as $permiso)
                            <li class="flex gap-2"><x-icono nombre="check" class="mt-0.5 h-4 w-4 shrink-0 text-marca-oliva" />{{ $permiso }}</li>
                        @endforeach
                    </ul>
                </div>
            @endforeach
        </div>
        <p class="mt-3 text-sm text-marca-suave">Todos pueden activar, si quieren, la autenticación en dos pasos (2FA) desde su pestaña.</p>
    </section>
</x-modulo>
