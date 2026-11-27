<x-layouts.app>
    <div>
        <section aria-labelledby="titulo" class="mx-auto flex max-w-6xl flex-col items-center px-6 pt-2 pb-24 text-center">
            <div class="relative flex aspect-[820/500] w-full max-w-[820px] items-end justify-center overflow-hidden rounded-[50%] border-8 border-ink bg-celluloid shadow-hard-lg">
                <svg class="absolute inset-0 size-full" viewBox="0 0 820 500" preserveAspectRatio="none" aria-hidden="true">
                    <rect x="0" y="210" width="820" height="290" fill="#CDB98A" />
                    <path d="M0 210 H820" stroke="#000000" stroke-width="5" />
                    <path d="M360 210 L460 210 L780 500 L40 500 Z" fill="#3A3A36" stroke="#000000" stroke-width="6" stroke-linejoin="round" />
                </svg>
                <x-car letters="MTR" digits="2026" drop class="relative mb-[4%] w-[73%]" />
            </div>

            <h1 id="titulo" class="title-ink relative -mt-10 text-[clamp(2.5rem,12vw,9.25rem)] md:-mt-14">Matricular</h1>
            <p class="mt-5 max-w-2xl text-[clamp(1.5rem,3vw,2.125rem)] font-bold leading-tight">
                Tres letras de matrícula. Tú pones la palabra antes de que se acabe la gasolina.
            </p>

            <div class="mt-9 flex flex-wrap items-center justify-center gap-7">
                @auth
                    <a class="btn btn-primary px-7 text-xl md:px-10 md:text-2xl" href="{{ route('menu.view') }}">Jugar</a>
                @else
                    <a class="btn btn-primary px-7 text-xl md:px-10 md:text-2xl" href="{{ route('register.view') }}">Crear cuenta y jugar</a>
                    <a class="text-xl font-bold hover:text-mustard" href="{{ route('login.view') }}">Ya tengo cuenta</a>
                @endauth
            </div>
        </section>
    </div>

    <section id="como" aria-labelledby="como-titulo" class="surface-light border-t-8 border-ink bg-celluloid px-6 pt-18 pb-22">
        <div class="mx-auto flex max-w-6xl flex-col gap-10">
            <h2 id="como-titulo" class="font-display text-[clamp(2.25rem,5vw,3.5rem)] leading-none">Cómo se juega</h2>
            <ol class="grid gap-9 [grid-template-columns:repeat(auto-fit,minmax(min(320px,100%),1fr))]">
                <li class="paper flex -rotate-1 flex-col gap-5 p-7">
                    <span class="flex size-14 items-center justify-center rounded-full border-4 border-ink bg-mustard font-display text-2xl" aria-hidden="true">1</span>
                    <div class="flex h-30 items-center">
                        <x-plate letters="BRC" digits="1932" scale="0.58" />
                    </div>
                    <p class="text-2xl">Mira las tres letras de la matrícula.</p>
                </li>
                <li class="paper flex rotate-[0.8deg] flex-col gap-5 p-7">
                    <span class="flex size-14 items-center justify-center rounded-full border-4 border-ink bg-mustard font-display text-2xl" aria-hidden="true">2</span>
                    <div class="flex h-30 items-center">
                        <span class="text-7xl font-black tracking-wide"><span class="text-signal">b</span>a<span class="text-signal">rc</span>o</span>
                    </div>
                    <p class="text-2xl">Escribe una palabra que las tenga todas. Mayúsculas y tildes dan igual; la ñ cuenta como letra propia.</p>
                </li>
                <li class="paper flex -rotate-[0.6deg] flex-col gap-5 p-7">
                    <span class="flex size-14 items-center justify-center rounded-full border-4 border-ink bg-mustard font-display text-2xl" aria-hidden="true">3</span>
                    <div class="flex h-30 items-center">
                        <x-odometer :value="10" />
                    </div>
                    <p class="text-2xl">Si existe, sumas 10 puntos y llega otra matrícula. Si no, pierdes 5.</p>
                </li>
            </ol>
            <a class="self-start text-xl font-bold underline hover:text-plate" href="{{ route('guide.view') }}">Leer la guía completa</a>
        </div>
    </section>
</x-layouts.app>
