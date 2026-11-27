<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    @if(session('error'))
        <meta name="session-error" content="{{ session('error') }}">
    @endif

    <title>@yield('title', 'Matricular')</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js" defer></script>

    @vite(['resources/sass/app.scss', 'resources/sass/main/_home.scss', 'resources/js/app.js'])
    <link rel="icon" href="{{ asset('favicon.ico') }}">
</head>
<body>
    <div id="master">
        <main class="py-5">
            <div class="container">
                <div class="row justify-content-center">
                    <div class="col-md-8 text-center">
                        <div class="greeting-section mb-5">
                            <h1 class="display-4 mb-3">¡Buenas!</h1>
                            <h1 class="display-3 mb-0">
                                Bienvenido a <span class="game-title">MATRICULAR</span>
                            </h1>
                        </div>
                        
                        <div class="game-description-section mb-5">
                            <div class="card shadow-sm">
                                <div class="card-body">
                                    <h2 class="h4 mb-3">¿Cómo jugar?</h2>
                                    <p class="lead">
                                        Busca palabras que cuadren con las matrículas de los coches que te ponemos delante y aumentar tu puntuación!
                                    </p>
                                </div>
                            </div>
                        </div>

                        @if(Auth::guest())
                            <div class="navigation-section">
                                <div class="d-grid gap-3 d-sm-flex justify-content-sm-center">
                                    <a href="{{ route('login') }}" class="btn btn-primary btn-lg px-4 gap-3">
                                        Iniciar Sesión
                                    </a>
                                    <a href="{{ route('register') }}" class="btn btn-outline-secondary btn-lg px-4">
                                        Registrarse
                                    </a>
                                </div>
                            </div>
                        @else
                            <div class="navigation-section">
                                <div class="d-grid gap-3 d-sm-flex justify-content-sm-center">
                                    <a href="{{ route('menu.view') }}" class="btn btn-primary btn-lg px-4 gap-3">
                                        Menu Principal
                                    </a>
                                </div>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </main>
        <footer class="footer py-3 bg-light">
            <div class="container text-center">
                <div class="row align-items-center">
                    <div class="col-md-6">
                        <span class="text-muted">© {{ date('Y') }} Matricular. Todos los derechos reservados.</span>
                        <a href="{{ route('credits.view') }}" class="ms-2">Créditos</a>
                    </div>
                    <div class="col-md-6">
                        <div class="social-links">
                            <a href="https://github.com/Rob-Gon" target="_blank" class="social-link" title="GitHub">
                                <i class="fab fa-github"></i>
                            </a>
                            <a href="https://www.linkedin.com/in/roberto-gonzalez-web-dev/" target="_blank" class="social-link" title="LinkedIn">
                                <i class="fab fa-linkedin"></i>
                            </a>
                            <a href="https://www.instagram.com/robertognzlz_/" target="_blank" class="social-link" title="Instagram">
                                <i class="fab fa-instagram"></i>
                            </a>
                            <a href="mailto:robertobbgg@gmail.com" class="social-link" title="Email">
                                <i class="fas fa-envelope"></i>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </footer>
    </div>
</body>
</html>
