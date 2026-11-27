@php
    use App\Enums\CountdownModes;
    use App\Enums\GameModes;
    use App\Enums\LetterSets;
    use App\Models\Run;

    $initial = [
        'letterSet' => LetterSets::SIMPLE->value,
        'ordered' => '0',
        'mode' => $mode->value,
        'time' => CountdownModes::UN_MINUTO->value,
    ];
@endphp

<x-layouts.app title="Prepara el viaje" description="Elige las letras, el orden y el modo antes de arrancar la partida." noindex>
    <div class="px-6 pt-4 pb-24">
        <div class="mx-auto flex max-w-6xl flex-col gap-9">
            <a class="link-btn self-start text-xl" href="{{ route('menu.view') }}">Volver al menú</a>
            <h1 class="title-ink text-[clamp(2.75rem,7vw,4.5rem)]">Prepara el viaje</h1>

            <form
                method="POST"
                action="{{ route('game.start') }}"
                x-data="gameSetup(@js($initial))"
                class="flex flex-wrap items-start gap-12"
            >
                @csrf

                <div class="flex min-w-0 flex-[999_1_32rem] flex-col gap-8">
                    <fieldset class="flex flex-col gap-3.5">
                        <legend class="mb-3.5 font-display text-3xl">Letras</legend>
                        <div class="grid gap-4 sm:grid-cols-2">
                            <x-option name="letter_set" :value="LetterSets::SIMPLE->value" model="letterSet" :checked="$initial['letterSet'] === LetterSets::SIMPLE->value" label="Simples" hint="Sin K, V, W, X, Y ni Z" />
                            <x-option name="letter_set" :value="LetterSets::FULL->value" model="letterSet" :checked="$initial['letterSet'] === LetterSets::FULL->value" label="Todas" hint="Las 20 letras de las matrículas" />
                        </div>
                    </fieldset>

                    <fieldset class="flex flex-col gap-3.5">
                        <legend class="mb-3.5 font-display text-3xl">Orden de las letras</legend>
                        <div class="grid gap-4 sm:grid-cols-2">
                            <x-option name="ordered" value="0" model="ordered" :checked="$initial['ordered'] === '0'" label="En cualquier orden" hint="barco y cabra valen para BRC" />
                            <x-option name="ordered" value="1" model="ordered" :checked="$initial['ordered'] === '1'" label="Como en la matrícula" hint="Las letras deben aparecer en ese orden" />
                        </div>
                    </fieldset>

                    <fieldset class="flex flex-col gap-3.5">
                        <legend class="mb-3.5 font-display text-3xl">Modo</legend>
                        <div class="grid gap-4 sm:grid-cols-2">
                            <x-option name="mode" :value="GameModes::CONTRARELOJ->value" model="mode" :checked="$initial['mode'] === GameModes::CONTRARELOJ->value" label="Contrarreloj" hint="Todas las palabras que puedas a tiempo" />
                            <x-option name="mode" :value="GameModes::INFINITO->value" model="mode" :checked="$initial['mode'] === GameModes::INFINITO->value" label="Sin límite" hint="Juega hasta que decidas parar" />
                        </div>
                    </fieldset>

                    {{-- Only countdown games use it; the server ignores it otherwise. --}}
                    <fieldset class="flex flex-wrap items-center gap-3" x-show="isCountdown">
                        <legend class="float-left mr-2 text-2xl font-black">Duración</legend>
                        @foreach (CountdownModes::cases() as $countdown)
                            <x-option variant="chip" name="countdown_time" :value="$countdown->value" model="time" :checked="$initial['time'] === $countdown->value" :label="$countdown->shortLabel()" />
                        @endforeach
                    </fieldset>
                </div>

                <aside aria-labelledby="resumen" class="paper flex min-w-0 flex-[1_1_22rem] flex-col gap-6 p-8">
                    <h2 id="resumen" class="font-display text-3xl">Así será tu partida</h2>
                    <div class="flex min-h-24 items-center">
                        <x-plate letters="BRC" digits="1932" scale="0.66" x-show="letterSet === 'simple'" />
                        <x-plate letters="ZRB" digits="4807" scale="0.66" x-show="letterSet === 'full'" x-cloak />
                    </div>
                    <p class="text-2xl leading-snug" x-text="rule"></p>
                    <ul class="flex flex-col gap-2.5">
                        <template x-for="example in examples" :key="example.text">
                            <li class="flex items-center gap-3 text-2xl font-bold">
                                <x-sign type="ok" label="Vale" class="size-8" x-show="example.ok" />
                                <x-sign type="fail" label="No vale" class="size-8" x-show="!example.ok" />
                                <span x-text="example.text"></span>
                            </li>
                        </template>
                    </ul>
                    <p class="text-xl leading-snug">
                        <span x-text="timeSummary"></span>
                        Cada acierto suma {{ Run::POINTS_CORRECT }} y cada fallo resta {{ Run::POINTS_PENALTY }}.
                    </p>
                    <button type="submit" class="btn btn-primary self-start text-2xl">Arrancar</button>
                </aside>
            </form>
        </div>
    </div>
</x-layouts.app>
