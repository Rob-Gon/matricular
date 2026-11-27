<x-layouts.app
    title="Cómo se juega"
    description="Reglas de Matricular: qué palabras valen, cómo se puntúa, los ajustes de letras y orden, y los modos contrarreloj y sin límite."
>
    <div class="px-6 pt-4 pb-24">
        <article class="mx-auto flex max-w-3xl flex-col gap-12">
            <header class="flex flex-col gap-5">
                <h1 class="title-ink text-[clamp(3rem,8vw,5.5rem)]">Cómo se juega</h1>
                <p class="text-2xl font-bold">Cada matrícula trae tres letras. Escribe palabras que las contengan todas, tantas como puedas.</p>
            </header>

            <section aria-labelledby="matricula" class="paper flex flex-col gap-4 p-7">
                <h2 id="matricula" class="font-display text-3xl">La matrícula</h2>
                <x-plate letters="BRC" digits="1932" scale="0.6" class="self-start" />
                <p class="text-xl">Las letras son las de las matrículas españolas: consonantes sin Ñ ni Q. Los números no cuentan, solo decoran.</p>
            </section>

            <section aria-labelledby="validas" class="paper flex flex-col gap-4 p-7">
                <h2 id="validas" class="font-display text-3xl">Qué palabras valen</h2>
                <ul class="flex list-disc flex-col gap-2 pl-6 text-xl">
                    <li>Tienen al menos 3 letras y existen en el diccionario.</li>
                    <li>Contienen las tres letras de la matrícula. Si una letra se repite, la palabra también la necesita repetida: con <strong>SSL</strong> vale «salsa», pero no «sal».</li>
                    <li>Mayúsculas y tildes dan igual: «camion» y «CAMIÓN» cuentan como «camión».</li>
                    <li>La ñ es una letra propia: «nino» no es «niño», y una ñ no cubre una N de la matrícula.</li>
                    <li>Cada palabra puntúa una sola vez por partida.</li>
                </ul>
            </section>

            <section aria-labelledby="puntos" class="paper flex flex-col gap-4 p-7">
                <h2 id="puntos" class="font-display text-3xl">Puntos</h2>
                <dl class="grid grid-cols-[auto_minmax(0,1fr)] items-center gap-x-6 gap-y-4 text-xl">
                    <dt><x-sign type="ok" class="size-12" /></dt>
                    <dd>Palabra válida: <strong>+10</strong> y llega otra matrícula.</dd>
                    <dt><x-sign type="fail" class="size-12" /></dt>
                    <dd>Palabra que no vale: <strong>−5</strong>.</dd>
                    <dt><x-sign type="warn" label="Pasamatrícula" class="size-12" /></dt>
                    <dd>Pasamatrícula, si no se te ocurre nada: <strong>−5</strong> y cambias de matrícula.</dd>
                </dl>
            </section>

            <section aria-labelledby="ajustes" class="paper flex flex-col gap-5 p-7">
                <h2 id="ajustes" class="font-display text-3xl">Ajustes de la partida</h2>
                <div class="flex flex-col gap-2">
                    <h3 class="text-2xl font-black">Letras simples o todas</h3>
                    <p class="text-xl">Con letras simples no salen K, V, W, X, Y ni Z, que dan pocas palabras. Con todas, prepárate para matrículas como <strong>KLM</strong>: «kilómetro».</p>
                </div>
                <div class="flex flex-col gap-2">
                    <h3 class="text-2xl font-black">En cualquier orden o como en la matrícula</h3>
                    <p class="text-xl">En cualquier orden, con <strong>BRC</strong> valen «barco» y «cabra». Como en la matrícula, las letras deben aparecer en ese orden: «barco» vale, «cabra» no.</p>
                </div>
                <p class="text-xl">Solo se reparten matrículas que tienen al menos 10 palabras posibles con tus ajustes.</p>
            </section>

            <section aria-labelledby="modos" class="paper flex flex-col gap-4 p-7">
                <h2 id="modos" class="font-display text-3xl">Modos</h2>
                <ul class="flex list-disc flex-col gap-2 pl-6 text-xl">
                    <li><strong>Contrarreloj:</strong> 30 segundos, o de 1 a 5 minutos. La aguja de la gasolina marca el tiempo que queda.</li>
                    <li><strong>Contrarreloj personalizado:</strong> elige tú los minutos, hasta una hora. Tiene su propia clasificación y no cuenta para el logro de los 500 puntos.</li>
                    <li><strong>Sin límite:</strong> juega a tu ritmo y termina cuando quieras.</li>
                </ul>
            </section>

            <section aria-labelledby="teclado" class="paper flex flex-col gap-4 p-7">
                <h2 id="teclado" class="font-display text-3xl">Con teclado</h2>
                <ul class="flex list-disc flex-col gap-2 pl-6 text-xl">
                    <li><strong>Intro</strong> comprueba la palabra que has escrito.</li>
                    <li><strong>Control</strong> (<strong>Comando</strong> en Mac), pulsada sola y sin otra tecla, hace Pasamatrícula. Los atajos como Control + Z no cuentan.</li>
                </ul>
            </section>

            <div class="flex flex-wrap items-center gap-7">
                @auth
                    <a class="btn btn-primary" href="{{ route('menu.view') }}">Jugar</a>
                @else
                    <a class="btn btn-primary" href="{{ route('register.view') }}">Crear cuenta y jugar</a>
                @endauth
            </div>
        </article>
    </div>
</x-layouts.app>
