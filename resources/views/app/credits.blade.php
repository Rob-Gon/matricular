@extends('layouts.master-layout')

@section('title', 'Créditos · Matricular')

@section('content')
<div class="container pt-5 pb-5 mb-5">
    <h1 class="text-center mb-5">Créditos</h1>

    <section class="mb-4">
        <h2 class="h4">Diccionario</h2>
        <p>
            Las palabras válidas se generan a partir del diccionario ortográfico
            <a href="https://github.com/sbosio/rla-es" rel="noopener">RLA-ES</a> (Proyecto de Revisión Lingüística Automatizada en Español),
            versión 2.9, distribuido bajo licencia GNU GPL 3, GNU LGPL 3 o MPL 1.1, a elección.
        </p>
    </section>

    <section class="mb-4">
        <h2 class="h4">Categorías gramaticales</h2>
        <p>
            Las categorías gramaticales (sustantivo, adjetivo, verbo…) proceden de
            <a href="https://es.wiktionary.org" rel="noopener">Wikcionario en español</a>, cuyo contenido está disponible bajo la licencia
            <a href="https://creativecommons.org/licenses/by-sa/4.0/deed.es" rel="noopener">Creative Commons Atribución-CompartirIgual 4.0</a>.
            Los datos se extraen con <a href="https://github.com/tatuylonen/wiktextract" rel="noopener">Wiktextract</a>
            (<a href="https://kaikki.org" rel="noopener">kaikki.org</a>), de Tatu Ylonen.
            Matricular solo usa la palabra y su categoría, sin definiciones.
        </p>
    </section>

    <section>
        <h2 class="h4">Verificación de palabras</h2>
        <p>
            Las palabras que no están en el diccionario local se consultan en
            <a href="https://rae-api.com" rel="noopener">rae-api.com</a>, un servicio no oficial basado en el Diccionario de la lengua española.
            Matricular no está afiliado a la Real Academia Española.
        </p>
    </section>
</div>
@endsection
