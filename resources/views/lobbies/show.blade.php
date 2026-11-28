@php
    use App\Enums\CountdownModes;
    use App\Enums\LetterSets;
    use App\Enums\LobbyFormats;

    $config = [
        'lobbyId' => $lobby->id,
        'me' => auth()->id(),
        'state' => $state,
        'csrf' => csrf_token(),
        'urls' => [
            'index' => route('lobbies.index'),
            'ready' => route('lobbies.ready', $lobby),
            'color' => route('lobbies.color', $lobby),
            'rules' => route('lobbies.rules', $lobby),
            'kick' => route('lobbies.kick', $lobby),
            'start' => route('lobbies.start', $lobby),
            'race' => route('lobbies.race', $lobby),
        ],
    ];
@endphp

<x-layouts.app title="Parrilla de salida" description="Sala multijugador de Matricular." noindex>
    <div x-data="lobby(@js($config))" class="px-6 pt-4 pb-24">
        <div class="mx-auto flex max-w-6xl flex-col gap-8">
            <form method="POST" action="{{ route('lobbies.leave', $lobby) }}" class="self-start">
                @csrf
                <button type="submit" class="link-btn text-xl">Salir de la sala</button>
            </form>

            <div class="flex flex-wrap items-end justify-between gap-7">
                <h1 class="title-ink text-[clamp(2.5rem,6vw,4.5rem)]">Parrilla de salida</h1>
                <div class="paper surface-light flex flex-wrap items-center gap-5 px-5 py-4">
                    <div class="flex flex-col gap-2">
                        <span class="text-xl font-bold">Código de la sala</span>
                        <x-plate :letters="substr($lobby->code, 4)" :digits="substr($lobby->code, 0, 4)" scale="0.6" />
                    </div>
                    <div class="flex flex-col gap-2.5">
                        <button type="button" class="btn btn-primary btn-sm" @click="copyCode()">
                            <span x-text="copied ? '¡Copiado!' : 'Copiar código'">Copiar código</span>
                        </button>
                        <span class="flex items-center gap-2 text-lg" x-show="state.locked" @if (! $state['locked']) x-cloak @endif>
                            <svg aria-hidden="true" viewBox="0 0 24 24" class="size-5" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><rect x="5" y="11" width="14" height="10" rx="2" /><path d="M8 11V8a4 4 0 0 1 8 0v3" /></svg>
                            Con contraseña
                        </span>
                    </div>
                </div>
            </div>

            <p x-show="errors.general || errors.start" x-text="errors.general ?? errors.start" x-cloak role="alert" class="paper surface-light px-5 py-3 text-xl font-bold text-signal"></p>

            <div class="flex flex-wrap items-start gap-9">
                <section aria-labelledby="plazas" class="flex min-w-0 flex-[999_1_600px] flex-col gap-4">
                    <h2 id="plazas" class="font-display text-2xl"><span x-text="state.players.length">{{ count($state['players']) }}</span> de 16 plazas ocupadas</h2>

                    <ol x-ref="grid" aria-live="polite" class="grid grid-cols-[repeat(auto-fill,minmax(min(132px,100%),1fr))] overflow-hidden rounded-[18px] border-[5px] border-ink bg-asphalt p-3.5 shadow-hard-lg">
                        <template x-for="player in state.players" :key="player.id">
                            <li :data-player="player.id" class="relative flex min-h-40 flex-col items-center justify-center gap-2 border-l-4 border-dashed border-mustard px-2.5 py-3 text-center">
                                <button
                                    type="button"
                                    class="absolute top-1.5 right-1.5 flex size-8 items-center justify-center rounded-full border-[3px] border-ink bg-signal text-base font-black text-white"
                                    x-show="isHost && player.id !== me"
                                    :aria-label="'Expulsar a ' + player.name"
                                    @click="askKick(player)"
                                >✕</button>
                                <svg aria-hidden="true" viewBox="0 0 64 96" class="h-16 w-11" :class="connected(player) ? '' : 'opacity-40'">
                                    <rect x="4" y="12" width="10" height="20" rx="4" fill="#000000" /><rect x="50" y="12" width="10" height="20" rx="4" fill="#000000" />
                                    <rect x="4" y="64" width="10" height="20" rx="4" fill="#000000" /><rect x="50" y="64" width="10" height="20" rx="4" fill="#000000" />
                                    <rect x="9" y="4" width="46" height="88" rx="18" :fill="player.color" stroke="#000000" stroke-width="5" />
                                    <rect x="16" y="22" width="32" height="18" rx="6" fill="#9FC4BE" stroke="#000000" stroke-width="4" />
                                    <rect x="18" y="62" width="28" height="12" rx="4" fill="#9FC4BE" stroke="#000000" stroke-width="4" />
                                </svg>
                                <span class="max-w-full truncate text-xl font-black text-enamel" x-text="player.name + (player.id === me ? ' (tú)' : '')"></span>
                                <span
                                    class="rounded-full border-2 border-ink px-2.5 py-0.5 text-base font-bold text-ink"
                                    :class="player.ready || player.id === state.hostId ? 'bg-mustard' : 'bg-celluloid'"
                                    x-text="connected(player) ? status(player) : 'Desconectado'"
                                ></span>
                            </li>
                        </template>
                        <template x-for="bay in freeBays" :key="'free-' + bay">
                            <li class="flex min-h-40 items-center justify-center border-l-4 border-dashed border-mustard px-2.5 py-3 text-lg font-bold text-celluloid">Plaza libre</li>
                        </template>
                    </ol>

                    <div class="paper surface-light flex flex-wrap items-center justify-between gap-5 px-5 py-4">
                        <div role="radiogroup" aria-label="Color de tu coche" class="flex flex-wrap items-center gap-3">
                            <span class="mr-1 text-xl font-black">Tu coche</span>
                            @foreach ($colors as $hex => $name)
                                <button
                                    type="button"
                                    role="radio"
                                    class="size-12 rounded-full border-4 border-ink"
                                    style="background: {{ $hex }}"
                                    aria-label="{{ $name }}"
                                    :aria-checked="(mine?.color === @js($hex)).toString()"
                                    :class="mine?.color === @js($hex) ? 'shadow-[0_0_0_4px_#FBF8EF,0_0_0_8px_#000]' : ''"
                                    @click="pickColor(@js($hex))"
                                ></button>
                            @endforeach
                        </div>
                        <button type="button" class="btn" x-show="!isHost" :class="mine?.ready ? '' : 'btn-primary'" :disabled="busy" @click="toggleReady()">
                            <span x-text="mine?.ready ? 'Ya no estoy listo' : '¡Estoy listo!'">¡Estoy listo!</span>
                        </button>
                    </div>
                </section>

                <aside aria-labelledby="reglas" class="paper surface-light flex min-w-0 flex-[1_1_340px] flex-col gap-4 p-7">
                    <h2 id="reglas" class="font-display text-2xl">Reglas de la carrera</h2>
                    <dl class="grid grid-cols-[auto_minmax(0,1fr)] gap-x-4 gap-y-2.5 text-xl leading-snug">
                        <dt class="font-black">Formato</dt><dd x-text="state.formatLabel">{{ $state['formatLabel'] }}</dd>
                        <template x-for="rule in state.rules" :key="rule.term">
                            <div class="contents"><dt class="font-black" x-text="rule.term"></dt><dd x-text="rule.value"></dd></div>
                        </template>
                    </dl>
                    <button type="button" class="btn btn-sm self-start" x-show="isHost" @click="editing = true; resetForm(); $nextTick(() => $refs.rulesDialog.showModal())">Cambiar reglas</button>
                    <p class="text-lg leading-snug">Solo el anfitrión cambia las reglas y expulsa. Si se va, manda quien más tiempo lleve en la sala.</p>
                </aside>
            </div>

            <div class="flex flex-wrap items-center justify-end gap-5">
                <p class="text-xl font-bold" x-text="startHint"></p>
                <button type="button" class="btn" x-show="isHost" :class="canStart ? 'btn-primary' : ''" :disabled="busy || !canStart" @click="start()" x-text="{ race: 'Empezar carrera', rounds: 'Empezar las rondas', first: 'Empezar la partida' }[state.format]">Empezar carrera</button>
            </div>
        </div>

        {{-- Host only: confirm before kicking a player. --}}
        <dialog x-ref="kickDialog" aria-labelledby="kick-title" class="paper surface-light m-auto w-[min(28rem,calc(100vw-2rem))] p-7 backdrop:bg-black/60" @close="kickTarget = null">
            <div class="flex flex-col gap-5">
                <h2 id="kick-title" class="font-display text-3xl">¿Expulsar a <span x-text="kickTarget?.name"></span>?</h2>
                <p class="text-xl">Saldrá de la parrilla y no podrá volver a entrar en esta sala.</p>
                <div class="flex flex-wrap gap-4">
                    <button type="button" class="btn btn-danger" :disabled="busy" @click="kick()">Expulsar</button>
                    <button type="button" class="btn" @click="$refs.kickDialog.close()">Cancelar</button>
                </div>
            </div>
        </dialog>

        {{-- Host only: the rules of the next race. --}}
        <dialog x-ref="rulesDialog" aria-labelledby="rules-title" class="paper surface-light m-auto w-[min(40rem,calc(100vw-2rem))] p-7 backdrop:bg-black/60" @close="editing = false">
            <form class="flex flex-col gap-5" @submit.prevent="saveRules().then(() => editing || $refs.rulesDialog.close())">
                <h2 id="rules-title" class="font-display text-3xl">Reglas de la carrera</h2>

                <fieldset class="flex flex-col gap-2.5">
                    <legend class="mb-2 text-xl font-black">Formato</legend>
                    @foreach (LobbyFormats::cases() as $format)
                        <x-option name="format" :value="$format->value" model="form.format" :label="$format->shortLabel()" :hint="$format->description()" />
                    @endforeach
                </fieldset>

                <fieldset class="flex flex-wrap items-center gap-2.5" x-show="form.format === 'race'">
                    <legend class="float-left mr-2 text-xl font-black">Duración</legend>
                    @foreach (CountdownModes::cases() as $countdown)
                        <x-option variant="chip" name="countdown_time" :value="$countdown->value" model="form.countdown_time" :label="$countdown->shortLabel()" class="text-lg!" />
                    @endforeach
                    <label class="flex items-center gap-2 text-lg font-bold" x-show="form.countdown_time === 'Personalizado'">
                        <input type="number" min="1" max="{{ CountdownModes::MAX_CUSTOM_MINUTES }}" class="field-input w-24! text-center" x-model.number="form.custom_minutes"> minutos
                    </label>
                </fieldset>

                <div class="flex flex-wrap gap-5" x-show="form.format === 'rounds'">
                    <label class="field"><span class="field-label">Rondas (1–30)</span><input type="number" min="1" max="30" class="field-input w-32!" x-model.number="form.rounds"></label>
                    <label class="field"><span class="field-label">Segundos por ronda (10–300)</span><input type="number" min="10" max="300" class="field-input w-32!" x-model.number="form.round_seconds"></label>
                </div>

                <div class="flex flex-wrap gap-5" x-show="form.format === 'first'">
                    <label class="field"><span class="field-label">Matrículas (1–50)</span><input type="number" min="1" max="50" class="field-input w-32!" x-model.number="form.plates"></label>
                    <label class="field"><span class="field-label">Segundos por matrícula (10–120)</span><input type="number" min="10" max="120" class="field-input w-32!" x-model.number="form.plate_seconds"></label>
                    <label class="field"><span class="field-label">Margen para empates</span>
                        <select class="field-input" x-model="form.tie_window_ms">
                            <option value="0">Sin margen</option>
                            <option value="150">150 ms</option>
                            <option value="300">300 ms</option>
                        </select>
                    </label>
                </div>

                <fieldset class="flex flex-wrap items-center gap-2.5">
                    <legend class="float-left mr-2 text-xl font-black">Letras</legend>
                    <x-option variant="chip" name="letter_set" :value="LetterSets::SIMPLE->value" model="form.letter_set" label="Simples" class="text-lg!" />
                    <x-option variant="chip" name="letter_set" :value="LetterSets::FULL->value" model="form.letter_set" label="Todas" class="text-lg!" />
                    <x-option variant="chip" name="ordered" value="0" model="form.ordered" label="Cualquier orden" class="text-lg!" />
                    <x-option variant="chip" name="ordered" value="1" model="form.ordered" label="En orden" class="text-lg!" />
                </fieldset>

                <fieldset class="flex flex-wrap items-center gap-2.5">
                    <legend class="float-left mr-2 text-xl font-black">Empate final</legend>
                    <x-option variant="chip" name="tie_resolution" value="tiebreak" model="form.tie_resolution" label="Matrícula de desempate" class="text-lg!" />
                    <x-option variant="chip" name="tie_resolution" value="shared" model="form.tie_resolution" label="Comparten puesto" class="text-lg!" />
                </fieldset>

                <ul class="flex flex-col gap-1 text-lg font-bold text-signal" role="alert">
                    <template x-for="(message, field) in errors" :key="field"><li x-text="message"></li></template>
                </ul>
                <p class="text-lg">Al cambiar las reglas, todos tienen que volver a marcar que están listos.</p>
                <div class="flex flex-wrap gap-4">
                    <button type="submit" class="btn btn-primary" :disabled="busy">Guardar reglas</button>
                    <button type="button" class="btn" @click="$refs.rulesDialog.close()">Cancelar</button>
                </div>
            </form>
        </dialog>
    </div>
</x-layouts.app>
