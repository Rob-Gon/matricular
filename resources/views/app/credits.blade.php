<x-layouts.app
    title="Créditos"
    description="Fuentes y licencias de Matricular: diccionario RLA-ES, categorías de Wikcionario y tipografías."
>
    <div class="px-6 pt-4 pb-24">
        <article class="mx-auto flex max-w-3xl flex-col gap-10">
            <h1 class="title-ink text-[clamp(3rem,8vw,5.5rem)]">Créditos</h1>

            <section aria-labelledby="diccionario" class="paper flex flex-col gap-3 p-7 text-xl">
                <h2 id="diccionario" class="font-display text-3xl">Diccionario</h2>
                <p>
                    Las palabras válidas se generan a partir del diccionario ortográfico
                    <a class="underline hover:text-plate" href="https://github.com/sbosio/rla-es" rel="noopener">RLA-ES</a> (Proyecto de Revisión Lingüística Automatizada en Español),
                    versión 2.9, distribuido bajo licencia GNU GPL 3, GNU LGPL 3 o MPL 1.1, a elección.
                </p>
            </section>

            <section aria-labelledby="categorias" class="paper flex flex-col gap-3 p-7 text-xl">
                <h2 id="categorias" class="font-display text-3xl">Categorías gramaticales</h2>
                <p>
                    Las categorías gramaticales (sustantivo, adjetivo, verbo…) proceden de
                    <a class="underline hover:text-plate" href="https://es.wiktionary.org" rel="noopener">Wikcionario en español</a>, cuyo contenido está disponible bajo la licencia
                    <a class="underline hover:text-plate" href="https://creativecommons.org/licenses/by-sa/4.0/deed.es" rel="noopener">Creative Commons Atribución-CompartirIgual 4.0</a>.
                    Los datos se extraen con <a class="underline hover:text-plate" href="https://github.com/tatuylonen/wiktextract" rel="noopener">Wiktextract</a>
                    (<a class="underline hover:text-plate" href="https://kaikki.org" rel="noopener">kaikki.org</a>), de Tatu Ylonen.
                    Matricular solo usa la palabra y su categoría, sin definiciones.
                </p>
            </section>

            <section aria-labelledby="verificacion" class="paper flex flex-col gap-3 p-7 text-xl">
                <h2 id="verificacion" class="font-display text-3xl">Verificación de palabras</h2>
                <p>
                    Las palabras que no están en el diccionario local se consultan en
                    <a class="underline hover:text-plate" href="https://rae-api.com" rel="noopener">rae-api.com</a>, un servicio no oficial basado en el Diccionario de la lengua española.
                    Matricular no está afiliado a la Real Academia Española.
                </p>
            </section>

            <section aria-labelledby="tipografias" class="paper flex flex-col gap-3 p-7 text-xl">
                <h2 id="tipografias" class="font-display text-3xl">Tipografías</h2>
                <p>
                    <a class="underline hover:text-plate" href="https://fonts.google.com/specimen/Chango" rel="noopener">Chango</a>, de Fontstage, y
                    <a class="underline hover:text-plate" href="https://fonts.google.com/specimen/Big+Shoulders" rel="noopener">Big Shoulders</a>, de Patric King,
                    ambas bajo la <a class="underline hover:text-plate" href="https://openfontlicense.org" rel="noopener">SIL Open Font License 1.1</a>.
                </p>
            </section>

            <section aria-labelledby="sonido" class="paper flex flex-col gap-3 p-7 text-xl">
                <h2 id="sonido" class="font-display text-3xl">Sonido</h2>
                <p>
                    Efectos de «Interface Sounds», de <a class="underline hover:text-plate" href="https://kenney.nl" rel="noopener">Kenney</a>,
                    de dominio público (<a class="underline hover:text-plate" href="https://creativecommons.org/publicdomain/zero/1.0/deed.es" rel="noopener">CC0 1.0</a>).
                </p>
            </section>
        </article>
    </div>
</x-layouts.app>
