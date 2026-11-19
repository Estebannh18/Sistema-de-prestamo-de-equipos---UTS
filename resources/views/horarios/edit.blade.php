@php
    use App\Models\Horario;

    $botonChico = 'inline-flex items-center gap-1.5 rounded-full border-2 border-marca-oliva bg-white px-3 py-1 font-display text-xs font-bold text-marca-oliva transition hover:bg-marca-oliva hover:text-white focus:outline-none focus-visible:ring-4 focus-visible:ring-marca-lima/50';
    $erroresHorario = collect($errors->getMessages())->filter(fn ($m, $k) => str_starts_with($k, 'bloques'))->flatten()->unique();
@endphp

<x-modulo titulo="Horarios" :pendiente="false">
    @if (session('status'))
        <p class="aviso-ok"><x-icono nombre="check" class="h-5 w-5 shrink-0" />{{ session('status') }}</p>
    @endif
    @if ($erroresHorario->isNotEmpty())
        <div class="aviso-error !items-start">
            <x-icono nombre="alerta" class="h-5 w-5 shrink-0" />
            <ul class="list-disc space-y-1 pl-4">
                @foreach ($erroresHorario as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <a href="{{ route('equipos.show', $equipo) }}" class="enlace inline-flex items-center gap-1 text-sm"><x-icono nombre="izquierda" class="h-4 w-4" />Volver a {{ $equipo->nombre }}</a>

    <div class="mt-5 flex flex-wrap items-start gap-4">
        <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-marca-limaclaro text-marca-oliva ring-2 ring-marca-lima">
            <x-icono :nombre="$equipo->categoria->iconoVisible()" class="h-6 w-6" />
        </span>
        <div class="min-w-0">
            <h2 class="font-display text-xl font-extrabold text-marca-noche">{{ $equipo->nombre }}</h2>
            <p class="text-sm text-marca-suave">{{ $equipo->categoria->nombre }} · {{ $equipo->codigo_inventario }}</p>
        </div>
    </div>

    <p class="mt-4 max-w-3xl text-sm text-marca-suave">
        Bloques de horas en que se puede solicitar, de lunes a sábado. Cada día puede tener bloques distintos.
        Un día sin bloques no se presta. Los préstamos ya hechos conservan su horario aunque cambies estos bloques.
    </p>

    <form method="POST" action="{{ route('horarios.update', $equipo) }}" class="mt-6" novalidate
          x-data="{
              dias: @js(Horario::DIAS),
              bloques: @js($bloques),
              institucionales: @js(Horario::BLOQUES_INSTITUCIONALES),
              agregar(dia) {
                  const ultimo = this.bloques[dia].at(-1);
                  this.bloques[dia].push({ inicio: ultimo ? ultimo.fin : '07:00', fin: '' });
              },
              quitar(dia, i) { this.bloques[dia].splice(i, 1) },
              usarInstitucionales(dia) { this.bloques[dia] = this.institucionales.map(([inicio, fin]) => ({ inicio, fin })) },
              copiarATodos(dia) {
                  if (! confirm('¿Copiar los bloques del ' + this.dias[dia].toLowerCase() + ' a los demás días? Se reemplazan los que tengan.')) return;
                  Object.keys(this.dias).forEach(d => { if (d != dia) this.bloques[d] = this.bloques[dia].map(b => ({ ...b })) });
              },
              get total() { return Object.values(this.bloques).reduce((suma, lista) => suma + lista.length, 0) },
          }">
        @csrf
        @method('PUT')

        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            @foreach (Horario::DIAS as $dia => $nombre)
                <section class="flex flex-col rounded-3xl border border-marca-linea bg-white p-4" aria-labelledby="dia-{{ $dia }}">
                    <div class="flex items-center justify-between gap-2">
                        <h3 id="dia-{{ $dia }}" class="flex items-center gap-2 font-display text-base font-extrabold text-marca-noche">
                            <span class="flex h-8 w-8 items-center justify-center rounded-xl bg-marca-limaclaro text-marca-oliva"><x-icono nombre="calendario" class="h-4 w-4" /></span>
                            {{ $nombre }}
                        </h3>
                        <span class="text-xs font-bold text-marca-suave" x-text="bloques[{{ $dia }}].length === 0 ? 'No se presta' : bloques[{{ $dia }}].length + (bloques[{{ $dia }}].length === 1 ? ' bloque' : ' bloques')"></span>
                    </div>

                    <ul class="mt-3 space-y-2">
                        <template x-for="(bloque, i) in bloques[{{ $dia }}]" :key="i">
                            <li class="flex items-center gap-2">
                                <input type="time" step="60" required x-model="bloque.inicio" :name="'bloques[{{ $dia }}][' + i + '][inicio]'"
                                       class="campo-uts w-full px-2 py-1.5 text-sm tabular-nums" aria-label="Inicio del bloque">
                                <span class="text-marca-suave" aria-hidden="true">–</span>
                                <input type="time" step="60" required x-model="bloque.fin" :name="'bloques[{{ $dia }}][' + i + '][fin]'"
                                       class="campo-uts w-full px-2 py-1.5 text-sm tabular-nums" aria-label="Fin del bloque">
                                <button type="button" x-on:click="quitar({{ $dia }}, i)" class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full text-rose-700 transition hover:bg-rose-50 focus:outline-none focus-visible:ring-4 focus-visible:ring-rose-200" aria-label="Quitar bloque">
                                    <x-icono nombre="cerrar" class="h-4 w-4" />
                                </button>
                            </li>
                        </template>
                    </ul>
                    <p class="mt-2 rounded-2xl border-2 border-dashed border-marca-linea px-3 py-4 text-center text-xs text-marca-suave" x-show="bloques[{{ $dia }}].length === 0">Este día no se presta.</p>

                    <div class="mt-auto flex flex-wrap gap-2 pt-4">
                        <button type="button" class="{{ $botonChico }}" x-on:click="agregar({{ $dia }})"><x-icono nombre="mas" class="h-3.5 w-3.5" />Agregar bloque</button>
                        <button type="button" class="{{ $botonChico }}" x-on:click="usarInstitucionales({{ $dia }})" title="6:00 a 21:30, en bloques de hora y media">Bloques institucionales</button>
                        <button type="button" class="enlace text-xs" x-on:click="copiarATodos({{ $dia }})">Copiar a todos los días</button>
                        <button type="button" class="enlace text-xs" x-on:click="bloques[{{ $dia }}] = []" x-show="bloques[{{ $dia }}].length">Vaciar</button>
                    </div>
                </section>
            @endforeach
        </div>

        <div class="mt-8 flex flex-wrap items-center gap-4 border-t border-marca-linea pt-6">
            <x-primary-button>Guardar horarios</x-primary-button>
            <a class="enlace text-sm" href="{{ route('equipos.show', $equipo) }}">Cancelar</a>
            <span class="text-sm text-marca-suave" x-text="total + (total === 1 ? ' bloque en la semana' : ' bloques en la semana')"></span>
        </div>
    </form>
</x-modulo>
