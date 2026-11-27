@php
    use App\Enums\GameModes;
@endphp

<x-layouts.app title="Menú" description="Elige modo y arranca una partida de Matricular." noindex>
    <div class="px-6 pt-4 pb-24">
        <div class="mx-auto flex max-w-6xl flex-col gap-10">
            <div class="flex flex-wrap items-end justify-between gap-6">
                <div class="flex flex-col gap-3">
                    <h1 class="title-ink text-[clamp(2.75rem,7vw,4.5rem)] break-words">Hola, {{ auth()->user()->name }}</h1>
                    <p class="text-2xl">¿Qué viaje hacemos hoy?</p>
                </div>
                @if ($played > 0)
                    <div class="paper flex flex-col gap-2 px-5 py-4">
                        <x-odometer :value="$best" label="puntos, tu mejor marca" scale="0.8" />
                        <p class="text-lg font-bold">{{ $played }} {{ $played === 1 ? 'partida jugada' : 'partidas jugadas' }}</p>
                    </div>
                @endif
            </div>

            <ul class="grid gap-7 md:grid-cols-2" aria-label="Modos de juego">
                <li>
                    <a href="{{ route('game.setup', ['mode' => GameModes::CONTRARELOJ->value]) }}" class="paper group flex h-full flex-col items-start gap-4 p-6 no-underline sm:flex-row sm:items-center sm:gap-6 sm:p-7 transition-transform hover:-translate-1 motion-reduce:transition-none">
                        <svg aria-hidden="true" viewBox="0 0 220 132" class="w-20 shrink-0 sm:w-28 overflow-visible">
                            <path d="M14 122 A96 96 0 0 1 206 122 Z" fill="#FBF8EF" stroke="#000000" stroke-width="8" stroke-linejoin="round" />
                            <path d="M189.7 74 A92 92 0 0 1 199 118" fill="none" stroke="#C8361F" stroke-width="12" />
                            <path d="M104 118 L150 50 L116 118 Z" fill="#C8361F" stroke="#000000" stroke-width="5" stroke-linejoin="round" />
                            <circle cx="110" cy="118" r="14" fill="#E9A92B" stroke="#000000" stroke-width="6" />
                        </svg>
                        <span class="flex flex-col gap-1.5">
                            <span class="font-display text-2xl sm:text-3xl">Contrarreloj</span>
                            <span class="text-xl">Todas las palabras que puedas antes de que se acabe la gasolina: de 30 segundos a 5 minutos.</span>
                        </span>
                    </a>
                </li>
                <li>
                    <a href="{{ route('game.setup', ['mode' => GameModes::INFINITO->value]) }}" class="paper group flex h-full flex-col items-start gap-4 p-6 no-underline sm:flex-row sm:items-center sm:gap-6 sm:p-7 transition-transform hover:-translate-1 motion-reduce:transition-none">
                        <svg aria-hidden="true" viewBox="0 0 120 120" class="w-20 shrink-0 sm:w-28">
                            <polygon points="8,116 112,116 64,8 56,8" fill="#3A3A36" stroke="#000000" stroke-width="6" stroke-linejoin="round" />
                            <path d="M58 104 L59.5 84 M59 70 L59.6 58 M59.5 48 L59.8 40" stroke="#E9A92B" stroke-width="5" stroke-linecap="round" />
                        </svg>
                        <span class="flex flex-col gap-1.5">
                            <span class="font-display text-2xl sm:text-3xl">Sin límite</span>
                            <span class="text-xl">Sin reloj: juega a tu ritmo y termina cuando quieras.</span>
                        </span>
                    </a>
                </li>
            </ul>

            <nav aria-label="Más" class="flex flex-wrap gap-4">
                <a class="btn" href="{{ route('scores.view') }}">Clasificación</a>
                <a class="btn" href="{{ route('achievements.view') }}">Logros</a>
                <a class="btn" href="{{ route('guide.view') }}">Cómo se juega</a>
            </nav>
        </div>
    </div>
</x-layouts.app>
