<div class="card my-5">
    <div class="card-header bg-primary text-white">
        <h2 class="mb-0">
            <i class="fas fa-trophy me-2"></i>
            <span class="text-white">Mayores Puntuaciones</span>
        </h2>
    </div>
    <div class="card-body">
        @if($topScores->count() > 0)
            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead class="table-dark">
                        <tr>
                            <th scope="col">#</th>
                            <th scope="col">Usuario</th>
                            <th scope="col">Email</th>
                            <th scope="col">Mayor Puntuación</th>
                            <th scope="col">Total de Partidas</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($topScores as $index => $score)
                            <tr class="{{ $index < 3 ? 'table-warning' : '' }}">
                                <td>
                                    @if($index < 3)
                                        <i class="fas fa-medal text-warning me-1"></i>
                                    @endif
                                    {{ $index + 1 }}
                                </td>
                                <td>
                                    <strong>{{ $score->name }}</strong>
                                </td>
                                <td>{{ $score->email }}</td>
                                <td>
                                    <span class="badge bg-success fs-6">
                                        {{ number_format($score->highest_score ?? 0) }}
                                    </span>
                                </td>
                                <td>
                                    <span class="badge bg-info">
                                        {{ $score->total_runs ?? 0 }}
                                    </span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <div class="text-center py-4">
                <i class="fas fa-info-circle text-muted fa-3x mb-3"></i>
                <h4 class="text-muted">No hay puntuaciones disponibles todavía</h4>
                <p class="text-muted">Comienza a jugar para ver las puntuaciones más altas</p>
            </div>
        @endif
    </div>
</div> 