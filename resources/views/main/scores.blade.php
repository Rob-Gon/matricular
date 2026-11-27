@extends('layouts.master-layout')
@section('content')
    @php
        use App\Enums\GameModes;
        use App\Enums\CountdownModes;
    @endphp
    
    <div class="container scores-page">
        <div class="row justify-content-center">
            <div class="col-md-10">
                <div class="text-center mb-3">
                    <div id="scores-filters">
                        
                        <!-- Filtros de modo de juego -->
                        <span class="filter-icon filter-mode active" data-mode="" title="Todos los modos">
                            <i class="fas fa-list-ul text-secondary"></i>
                        </span>
                        <span class="filter-icon filter-mode" data-mode="{{ GameModes::CONTRARELOJ->value }}" title="Contrareloj">
                            <i class="fas fa-stopwatch text-warning"></i>
                        </span>
                        <span class="filter-icon filter-mode" data-mode="{{ GameModes::INFINITO->value }}" title="Infinito">
                            <i class="fas fa-infinity text-primary"></i>
                        </span>

                        <!-- Filtros de tiempo -->
                        <span id="filter-times-group" class="ms-3" style="display:none">
                            <span class="filter-icon filter-time active" data-time="" title="Todos los tiempos">
                                <i class="fas fa-clock text-secondary"></i>
                            </span>
                            <span class="filter-icon filter-time" data-time="{{ CountdownModes::TREINTA_SEGUNDOS->value }}" title="30 segundos">
                                <span class="badge bg-info">30s</span>
                            </span>
                            <span class="filter-icon filter-time" data-time="{{ CountdownModes::UN_MINUTO->value }}" title="1 minuto">
                                <span class="badge bg-info">1m</span>
                            </span>
                            <span class="filter-icon filter-time" data-time="{{ CountdownModes::DOS_MINUTOS->value }}" title="2 minutos">
                                <span class="badge bg-info">2m</span>
                            </span>
                            <span class="filter-icon filter-time" data-time="{{ CountdownModes::TRES_MINUTOS->value }}" title="3 minutos">
                                <span class="badge bg-info">3m</span>
                            </span>
                            <span class="filter-icon filter-time" data-time="{{ CountdownModes::CUATRO_MINUTOS->value }}" title="4 minutos">
                                <span class="badge bg-info">4m</span>
                            </span>
                            <span class="filter-icon filter-time" data-time="{{ CountdownModes::CINCO_MINUTOS->value }}" title="5 minutos">
                                <span class="badge bg-info">5m</span>
                            </span>
                        </span>
                    </div>
                </div>
                <div id="topScoresTableContainer">
                @include('main.partials.top-scores-table', ['topScores' => $topScores])
                </div>
                @include('main.partials.recent-scores-table', ['recentTopScores' => $recentTopScores])
            </div>
        </div>
    </div>
@endsection