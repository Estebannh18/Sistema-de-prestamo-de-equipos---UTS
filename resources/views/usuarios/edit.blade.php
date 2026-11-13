<x-modulo titulo="Editar usuario" :pendiente="false">
    @if (session('status'))
        <p class="aviso-ok"><x-icono nombre="check" class="h-5 w-5 shrink-0" />{{ session('status') }}</p>
    @endif
    @if (session('error'))
        <p class="aviso-error"><x-icono nombre="alerta" class="h-5 w-5 shrink-0" />{{ session('error') }}</p>
    @endif

    @php($caja = 'rounded-2xl border border-marca-linea bg-marca-crema/60 px-4 py-3')
    <dl class="mb-8 grid gap-3 text-sm sm:grid-cols-2 lg:grid-cols-4">
        <div class="{{ $caja }}">
            <dt class="font-display text-xs font-bold uppercase tracking-wider text-marca-suave">Estado</dt>
            <dd class="mt-1">
                @if ($usuario->activo)
                    <span class="insignia insignia-disponible">Activo</span>
                @else
                    <span class="insignia insignia-rechazado">Inactivo</span>
                    <span class="mt-1 block text-xs text-marca-suave">No puede iniciar sesión</span>
                @endif
            </dd>
        </div>
        <div class="{{ $caja }}">
            <dt class="font-display text-xs font-bold uppercase tracking-wider text-marca-suave">Creado</dt>
            <dd class="mt-1 font-bold text-marca-noche">{{ $usuario->created_at?->format('d/m/Y') ?? '—' }}</dd>
        </div>
        <div class="{{ $caja }}">
            <dt class="font-display text-xs font-bold uppercase tracking-wider text-marca-suave">2FA</dt>
            <dd class="mt-1 font-bold text-marca-noche">{{ $usuario->two_factor_confirmed_at ? 'Activo' : ($usuario->two_factor_secret ? 'Iniciado, sin confirmar' : 'No activado') }}</dd>
        </div>
        <div class="{{ $caja }}">
            <dt class="font-display text-xs font-bold uppercase tracking-wider text-marca-suave">Préstamos activos</dt>
            <dd class="mt-1 font-bold text-marca-noche">{{ $prestamosActivos }}</dd>
            @if ($prestamosActivos > 0)
                <dd class="mt-1 text-xs text-marca-suave">Mientras los tenga, no se le puede cambiar el rol ni desactivar</dd>
            @endif
        </div>
    </dl>

    <form method="POST" action="{{ route('usuarios.update', $usuario) }}" novalidate>
        @csrf
        @method('PUT')
        @include('usuarios.formulario')
        <div class="mt-8 flex flex-wrap items-center gap-4 border-t border-marca-linea pt-6">
            <x-primary-button>Guardar cambios</x-primary-button>
            <a class="enlace text-sm" href="{{ route('usuarios.index') }}">Cancelar</a>
        </div>
    </form>

    @unless ($esPropio)
        <section class="mt-10 border-t border-marca-linea pt-6" aria-labelledby="acciones-cuenta">
            <h2 id="acciones-cuenta" class="font-display text-lg font-bold text-marca-noche">Acciones de la cuenta</h2>

            <div class="mt-4 grid gap-4 lg:grid-cols-2">
                <div class="flex flex-col justify-between gap-3 rounded-2xl border border-marca-linea p-5">
                    @if ($usuario->activo)
                        <div>
                            <p class="font-display font-bold text-marca-noche">Desactivar la cuenta</p>
                            <p class="mt-1 text-sm text-marca-suave">No borra nada: la persona deja de poder iniciar sesión y se cierran sus sesiones abiertas. Se puede volver a activar cuando se quiera.</p>
                        </div>
                        <form method="POST" action="{{ route('usuarios.desactivar', $usuario) }}" onsubmit="return confirm('¿Desactivar a {{ e($usuario->name) }}? Ya no podrá iniciar sesión.');">
                            @csrf
                            @method('PATCH')
                            <x-danger-button>Desactivar cuenta</x-danger-button>
                        </form>
                    @else
                        <div>
                            <p class="font-display font-bold text-marca-noche">Activar la cuenta</p>
                            <p class="mt-1 text-sm text-marca-suave">La cuenta está desactivada. Al activarla, la persona podrá iniciar sesión con su contraseña de siempre.</p>
                        </div>
                        <form method="POST" action="{{ route('usuarios.activar', $usuario) }}">
                            @csrf
                            @method('PATCH')
                            <x-secondary-button type="submit">Activar cuenta</x-secondary-button>
                        </form>
                    @endif
                </div>

                <div class="flex flex-col justify-between gap-3 rounded-2xl border border-marca-linea p-5">
                    <div>
                        <p class="font-display font-bold text-marca-noche">Restablecer el 2FA</p>
                        <p class="mt-1 text-sm text-marca-suave">
                            @if ($usuario->two_factor_secret)
                                Si perdió el celular con su aplicación autenticadora, restablécelo: entrará solo con su contraseña y podrá activarlo de nuevo desde su pestaña 2FA.
                            @else
                                Esta persona no tiene el 2FA activado, así que no hay nada que restablecer.
                            @endif
                        </p>
                    </div>
                    @if ($usuario->two_factor_secret)
                        <form method="POST" action="{{ route('usuarios.restablecer2fa', $usuario) }}" onsubmit="return confirm('¿Restablecer el 2FA de {{ e($usuario->name) }}?');">
                            @csrf
                            @method('PATCH')
                            <x-secondary-button type="submit">Restablecer 2FA</x-secondary-button>
                        </form>
                    @endif
                </div>
            </div>
        </section>
    @endunless
</x-modulo>
