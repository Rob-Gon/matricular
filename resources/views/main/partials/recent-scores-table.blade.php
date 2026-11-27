<div class="card">
    <div class="card-header bg-success text-white">
        <h3 class="mb-0">
            <i class="fas fa-clock me-2"></i>
            Mis Partidas Recientes
        </h3>
    </div>
    <div class="card-body">
        @if($recentTopScores->count() > 0)
            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead class="table-success">
                        <tr>
                            <th scope="col">#</th>
                            <th scope="col">Usuario</th>
                            <th scope="col">Puntuación</th>
                            <th scope="col">Modo</th>
                            <th scope="col">Letras</th>
                            <th scope="col">Fecha</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($recentTopScores as $index => $run)
                            <tr>
                                <td>{{ $index + 1 }}</td>
                                <td>
                                    <strong>{{ $run->user->name ?? 'John Doe' }}</strong>
                                </td>
                                <td>
                                    <span class="badge bg-success fs-6">
                                        {{ number_format($run->score) }}
                                    </span>
                                </td>
                                <td>
                                    <span class="badge bg-primary">
                                        {{ ucfirst($run->mode ?? 'Desconocido') }}
                                    </span>
                                </td>
                                <td>
                                    <span class="badge bg-warning text-dark">
                                        {{ $run->letter_set === App\Enums\LetterSets::SIMPLE ? 'Simples' : 'Todas' }}{{ $run->ordered ? ', en orden' : '' }}
                                    </span>
                                </td>
                                <td>
                                    <small class="text-muted">
                                        {{ $run->created_at ? $run->created_at->format('d/m/Y, H:i') : 'Desconocido' }}
                                    </small>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <div class="text-center py-4">
                <i class="fas fa-info-circle text-muted fa-3x mb-3"></i>
                <h4 class="text-muted">No tienes partidas recientes</h4>
                <p class="text-muted">Completa una partida para ver tus juegos recientes</p>
            </div>
        @endif
    </div>
</div>
