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

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    
    @vite(['resources/sass/app.scss', 'resources/js/app.js'])
    <link rel="icon" href="{{ asset('favicon.ico') }}">
    @stack('styles')
</head>
<body @auth data-user-authenticated="true" @endauth>
    <div id="master">
        <nav class="navbar navbar-expand-md navbar-light bg-white shadow-sm">
            <div class="container">
                <a class="navbar-brand" href="{{ route('home.view') }}">
                    Matricular
                </a>

                <div class="collapse navbar-collapse" id="navbarSupportedContent">
                    <ul class="navbar-nav ms-auto">
                        @guest
                            <li class="nav-item">
                                <a class="nav-link" href="{{ route('login.view') }}"><i class="fas fa-user"></i> Iniciar Sesión</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="{{ route('register.view') }}"><i class="fas fa-user-plus"></i> Registrarse</a>
                            </li>
                        @else
                            <li class="nav-item">
                                <a class="nav-link" href="{{ route('menu.view') }}"><i class="fas fa-home"></i> Menu Principal</a>
                            </li>
                            <li class="nav-item dropdown">
                                <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                                    <i class="fas fa-user"></i> {{ Auth::user()->name }}
                                </a>
                                <div class="dropdown-menu dropdown-menu-end">
                                    <a class="dropdown-item" href="{{ route('scores.view') }}">
                                        <i class="fas fa-medal"></i> Puntuaciones
                                    </a>
                                    <a class="dropdown-item" href="{{ route('achievements.view') }}">
                                        <i class="fas fa-trophy"></i> Logros
                                    </a>
                                    <a class="dropdown-item" href="{{ route('profile.view') }}">
                                        <i class="fas fa-cog"></i> Mi Perfil
                                    </a>
                                    <form method="POST" action="{{ route('logout') }}" id="logout-form">
                                        @csrf
                                        <button type="button" class="dropdown-item" onclick="confirmLogout()">
                                            <i class="fas fa-sign-out-alt"></i> Cerrar Sesión
                                        </button>
                                    </form>
                                </div>
                            </li>
                        @endguest
                    </ul>
                </div>
            </div>
        </nav>

        @yield('content')

        <footer class="footer py-3 bg-light">
            <div class="container text-center">
                <div class="row align-items-center">
                    <div class="col-md-6">
                        <span class="text-muted">© {{ date('Y') }} Matricular. Todos los derechos reservados.</span>
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

    @stack('scripts')
</body>
</html>
