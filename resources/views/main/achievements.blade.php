<x-layouts.app title="Logros" description="Tus logros de Matricular: los que ya tienes y los que te quedan." noindex>
    <div class="px-6 pt-4 pb-24">
        <div class="mx-auto flex max-w-6xl flex-col gap-10">
            <div class="flex flex-col gap-3">
                <h1 class="title-ink text-[clamp(2.75rem,7vw,4.5rem)]">Logros</h1>
                <p class="text-2xl">Has desbloqueado {{ $unlocked->count() }} de {{ $achievements->count() }}</p>
            </div>

            @if ($achievements->isEmpty())
                <p class="text-xl">Todavía no hay logros disponibles.</p>
            @else
                <ul class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($achievements as $achievement)
                        @php
                            $owned = $unlocked->get($achievement->id);
                            $concealed = $achievement->hidden && ! $owned;
                        @endphp
                        <li @class(['paper flex h-full min-w-0 items-center gap-4 p-5 sm:gap-5 sm:p-6', 'bg-celluloid! shadow-none!' => ! $owned])>
                            <x-sticker :locked="! $owned" :tilt="$loop->odd ? -8 : 6" class="{{ $owned ? '' : 'text-muted' }}" />
                            <div class="flex min-w-0 flex-col gap-1">
                                <h2 class="font-display text-xl break-words hyphens-auto">
                                    <span class="sr-only">{{ $owned ? 'Desbloqueado:' : 'Bloqueado:' }}</span>
                                    {{ $concealed ? '???' : $achievement->name }}
                                </h2>
                                <p class="text-lg leading-snug">
                                    {{ $concealed ? 'Logro oculto. Sigue jugando para descubrirlo.' : $achievement->description }}
                                </p>
                                @if ($owned)
                                    <p class="text-base font-bold">
                                        Desbloqueado el <x-local-time :value="$owned->pivot->unlocked_at" :time="false" />
                                    </p>
                                @endif
                            </div>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </div>
</x-layouts.app>
