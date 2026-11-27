@props([
    'title' => null,
    'description' => 'Juego de palabras con las letras de las matrículas españolas. Forma palabras con las tres letras antes de que se acabe la gasolina.',
    'noindex' => false,
])

@php
    $fullTitle = $title ? $title.' · Matricular' : 'Matricular: juego de palabras con matrículas';
@endphp

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $fullTitle }}</title>
    <meta name="description" content="{{ $description }}">
    @if ($noindex)
        <meta name="robots" content="noindex">
    @else
        <link rel="canonical" href="{{ url()->current() }}">
    @endif
    <meta name="theme-color" content="#265E57">
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="Matricular">
    <meta property="og:locale" content="es_ES">
    <meta property="og:title" content="{{ $fullTitle }}">
    <meta property="og:description" content="{{ $description }}">
    <meta property="og:url" content="{{ url()->current() }}">
    <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="32x32">
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    <link rel="preload" href="{{ Vite::asset('resources/fonts/chango-latin.woff2') }}" as="font" type="font/woff2" crossorigin>
    <link rel="preload" href="{{ Vite::asset('resources/fonts/big-shoulders-latin-var.woff2') }}" as="font" type="font/woff2" crossorigin>
    @vite(['resources/css/app.css', 'resources/js/site.js'])
</head>
<body class="grain vignette flex flex-col font-sans antialiased">
    {{-- Displacement filters for the ink "boil" animation, shared by every drawing on the page. --}}
    <svg width="0" height="0" class="absolute" aria-hidden="true" focusable="false">
        @foreach ([1, 4, 7] as $i => $seed)
            <filter id="ink-boil-{{ $i + 1 }}">
                <feTurbulence type="fractalNoise" baseFrequency="0.03" numOctaves="2" seed="{{ $seed }}" />
                <feDisplacementMap in="SourceGraphic" scale="3" />
            </filter>
        @endforeach
    </svg>

    <a href="#contenido" class="sr-only focus:not-sr-only focus:absolute focus:top-3 focus:left-3 focus:z-50 btn btn-sm btn-primary">Saltar al contenido</a>

    <header x-data="{ open: false }" class="relative mx-auto w-full max-w-6xl px-6 py-5">
        <div class="flex items-center justify-between gap-4">
            <a href="{{ route('home.view') }}" class="title-ink text-3xl no-underline">Matricular</a>

            <button
                type="button"
                class="btn btn-sm md:hidden"
                aria-controls="menu-principal"
                :aria-expanded="open.toString()"
                aria-expanded="false"
                @click="open = !open"
            >
                <span x-text="open ? 'Cerrar' : 'Menú'">Menú</span>
            </button>

            <nav
                id="menu-principal"
                aria-label="Principal"
                class="absolute top-full right-6 left-6 z-20 hidden md:static md:block"
                :class="open ? 'block!' : ''"
                @keydown.escape.window="open = false"
            >
                <ul class="paper flex flex-col gap-1 p-3 text-xl font-bold md:flex-row md:items-center md:gap-7 md:border-0 md:bg-transparent md:p-0 md:text-celluloid md:shadow-none">
                    @guest
                        <li><a class="block px-2 py-2 hover:text-mustard md:p-0" href="{{ route('guide.view') }}">Cómo se juega</a></li>
                        <li><a class="block px-2 py-2 hover:text-mustard md:p-0" href="{{ route('login.view') }}">Entrar</a></li>
                        <li><a class="btn btn-sm btn-primary mt-2 w-full md:mt-0 md:w-auto" href="{{ route('register.view') }}">Crear cuenta</a></li>
                    @else
                        <li><a class="block px-2 py-2 hover:text-mustard md:p-0" href="{{ route('menu.view') }}">Jugar</a></li>
                        <li><a class="block px-2 py-2 hover:text-mustard md:p-0" href="{{ route('scores.view') }}">Clasificación</a></li>
                        <li><a class="block px-2 py-2 hover:text-mustard md:p-0" href="{{ route('achievements.view') }}">Logros</a></li>
                        <li><a class="block px-2 py-2 hover:text-mustard md:p-0" href="{{ route('profile.view') }}">Perfil</a></li>
                        <li>
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button type="submit" class="block w-full cursor-pointer px-2 py-2 text-left font-bold hover:text-mustard md:p-0">Salir</button>
                            </form>
                        </li>
                    @endguest
                </ul>
            </nav>
        </div>
    </header>

    <x-flash />

    <main id="contenido" class="flex-1">
        {{ $slot }}
    </main>

    <footer class="bg-ink px-6 py-7 text-celluloid">
        <div class="mx-auto flex max-w-6xl flex-wrap items-center justify-between gap-x-8 gap-y-3 text-lg">
            <span>© {{ date('Y') }} Matricular</span>
            <ul class="flex flex-wrap gap-x-6 gap-y-2" aria-label="Enlaces">
                <li><a class="hover:text-mustard" href="{{ route('guide.view') }}">Cómo se juega</a></li>
                <li><a class="hover:text-mustard" href="{{ route('credits.view') }}">Créditos</a></li>
                <li><a class="hover:text-mustard" href="https://github.com/Rob-Gon" rel="me noopener">GitHub</a></li>
                <li><a class="hover:text-mustard" href="https://www.linkedin.com/in/roberto-gonzalez-web-dev/" rel="me noopener">LinkedIn</a></li>
                <li><a class="hover:text-mustard" href="https://www.instagram.com/robertognzlz_/" rel="me noopener">Instagram</a></li>
            </ul>
        </div>
    </footer>
</body>
</html>
