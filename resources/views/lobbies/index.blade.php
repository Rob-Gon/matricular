@php
    use App\Enums\LobbyFormats;
@endphp

<x-layouts.app title="Jugar con amigos" description="Crea una sala de Matricular o entra en una con su código." noindex>
    <div class="px-6 pt-4 pb-24">
        <div class="mx-auto flex max-w-6xl flex-col gap-10">
            <div class="flex flex-col gap-3">
                <a class="link-btn self-start text-xl" href="{{ route('menu.view') }}">Volver al menú</a>
                <h1 class="title-ink text-[clamp(2.5rem,7vw,4.5rem)]">Jugar con amigos</h1>
                <p class="text-2xl">Salas de 2 a 16 jugadores. Comparte el código y que arranque la carrera.</p>
            </div>

            @if ($notice)
                <p role="status" class="paper surface-light px-5 py-4 text-xl font-bold">{{ $notice }}</p>
            @endif

            @if ($current)
                <div class="paper flex flex-wrap items-center justify-between gap-4 p-6">
                    <p class="text-xl font-bold">Ya estás en la sala <x-plate :letters="substr($current->code, 4)" :digits="substr($current->code, 0, 4)" scale="0.4" class="mx-1 align-middle" />. Si creas o entras en otra, saldrás de ella.</p>
                    <a class="btn btn-primary" href="{{ route('lobbies.show', $current) }}">Volver a la sala</a>
                </div>
            @endif

            <div class="grid items-start gap-8 lg:grid-cols-2">
                <form method="POST" action="{{ route('lobbies.store') }}" class="paper surface-light flex flex-col gap-6 p-7">
                    @csrf
                    <h2 class="font-display text-3xl">Crea una sala</h2>
                    <fieldset class="flex flex-col gap-3">
                        <legend class="mb-3 text-xl font-black">Formato</legend>
                        @foreach (LobbyFormats::cases() as $format)
                            <x-option name="format" :value="$format->value" :checked="old('format', LobbyFormats::RACE->value) === $format->value" :label="$format->shortLabel()" :hint="$format->description()" />
                        @endforeach
                    </fieldset>
                    <x-field name="password" label="Contraseña (opcional)" type="password" autocomplete="new-password" hint="Solo podrán entrar quienes la sepan." />
                    <p class="text-lg">Después podrás ajustar duración, letras y demás reglas desde la sala.</p>
                    <button type="submit" class="btn btn-primary self-start">Crear sala</button>
                </form>

                <form method="POST" action="{{ route('lobbies.join') }}" class="paper surface-light flex flex-col gap-6 p-7">
                    @csrf
                    <h2 class="font-display text-3xl">Entra en una sala</h2>
                    <x-field name="code" label="Código de la sala" :value="$code" autocomplete="off" hint="Cuatro números y tres letras, como una matrícula: 4821 KTZ." required />
                    <x-field name="join_password" label="Contraseña, si la tiene" type="password" autocomplete="off" />
                    <button type="submit" class="btn btn-primary self-start">Entrar</button>
                </form>
            </div>
        </div>
    </div>
</x-layouts.app>
