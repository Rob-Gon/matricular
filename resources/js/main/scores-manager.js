/**
     * Gestor de Puntuaciones
     * Gestiona la funcionalidad de la página de puntuaciones
 */

class ScoresManager {
    constructor() {
        this.init();
    }

    init() {
        this.setupEventListeners();
        this.animateScores();
    }

    setupEventListeners() {
        // Agregar evento de clic a las filas de la tabla para mejor UX
        document.addEventListener('DOMContentLoaded', () => {
            const tableRows = document.querySelectorAll('.scores-page tbody tr');
            
            tableRows.forEach(row => {
                row.addEventListener('click', () => {
                    // Remover clase activa de todas las filas
                    tableRows.forEach(r => r.classList.remove('table-active'));
                    // Agregar clase activa a la fila clickeada
                    row.classList.add('table-active');
                });
            });

            // Agregar efectos hover para las insignias
            const badges = document.querySelectorAll('.scores-page .badge');
            badges.forEach(badge => {
                badge.addEventListener('mouseenter', () => {
                    badge.style.transform = 'scale(1.1)';
                    badge.style.transition = 'transform 0.2s ease';
                });
                
                badge.addEventListener('mouseleave', () => {
                    badge.style.transform = 'scale(1)';
                });
            });
        });
    }

    animateScores() {
        // Animar números de puntuación al cargar la página
        document.addEventListener('DOMContentLoaded', () => {
            const scoreElements = document.querySelectorAll('.scores-page .badge.bg-success');
            
            scoreElements.forEach(element => {
                const finalScore = parseInt(element.textContent.replace(/,/g, ''));
                if (!isNaN(finalScore)) {
                    this.animateNumber(element, 0, finalScore, 1000);
                }
            });
        });
    }

    animateNumber(element, start, end, duration) {
        const startTime = performance.now();
        const startValue = start;
        const endValue = end;
        
        const animate = (currentTime) => {
            const elapsed = currentTime - startTime;
            const progress = Math.min(elapsed / duration, 1);
            
            // Función de suavizado para animación fluida
            const easeOutQuart = 1 - Math.pow(1 - progress, 4);
            const currentValue = Math.floor(startValue + (endValue - startValue) * easeOutQuart);
            
            element.textContent = currentValue.toLocaleString();
            
            if (progress < 1) {
                requestAnimationFrame(animate);
            }
        };
        
        requestAnimationFrame(animate);
    }

    // Método para actualizar puntuaciones (podría usarse para actualizaciones en tiempo real)
    refreshScores() {
        // Esto podría hacer una llamada AJAX para obtener puntuaciones actualizadas
        console.log('Actualizando puntuaciones...');
        // La implementación para actualizaciones en tiempo real podría ir aquí
    }

    // Método para filtrar puntuaciones por dificultad
    filterByDifficulty(difficulty) {
        const rows = document.querySelectorAll('.scores-page tbody tr');
        
        rows.forEach(row => {
            const difficultyCell = row.querySelector('td:nth-child(5)'); // Ajustar índice según sea necesario
            if (difficultyCell) {
                const rowDifficulty = difficultyCell.textContent.trim().toLowerCase();
                if (difficulty === 'all' || rowDifficulty === difficulty.toLowerCase()) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            }
        });
    }

    // Método para ordenar puntuaciones
    sortScores(column, direction = 'desc') {
        const table = document.querySelector('.scores-page table');
        const tbody = table.querySelector('tbody');
        const rows = Array.from(tbody.querySelectorAll('tr'));
        
        rows.sort((a, b) => {
            const aValue = this.getCellValue(a, column);
            const bValue = this.getCellValue(b, column);
            
            if (direction === 'asc') {
                return aValue > bValue ? 1 : -1;
            } else {
                return aValue < bValue ? 1 : -1;
            }
        });
        
        // Reordenar filas en la tabla
        rows.forEach(row => tbody.appendChild(row));
    }

    getCellValue(row, columnIndex) {
        const cell = row.querySelector(`td:nth-child(${columnIndex + 1})`);
        if (!cell) return 0;
        
        const text = cell.textContent.trim();
        const number = parseInt(text.replace(/,/g, ''));
        return isNaN(number) ? text : number;
    }
}

// Inicializar gestor de puntuaciones cuando se carga el DOM
document.addEventListener('DOMContentLoaded', () => {
    new ScoresManager();

    // Filtros AJAX para topScores
    let selectedMode = '';
    let selectedDifficulty = '';
    let selectedTime = '';

    function updateFiltersUI() {
        // Modo
        document.querySelectorAll('.filter-mode').forEach(el => {
            el.classList.toggle('active', el.getAttribute('data-mode') === selectedMode || (selectedMode === '' && el.getAttribute('data-mode') === ''));
        });
        // Dificultad
        document.querySelectorAll('.filter-difficulty').forEach(el => {
            el.classList.toggle('active', el.getAttribute('data-difficulty') === selectedDifficulty || (selectedDifficulty === '' && el.getAttribute('data-difficulty') === ''));
        });
        // Tiempo
        document.querySelectorAll('.filter-time').forEach(el => {
            el.classList.toggle('active', el.getAttribute('data-time') === selectedTime || (selectedTime === '' && el.getAttribute('data-time') === ''));
        });
    }

    function fetchTopScores() {
        const params = new URLSearchParams();
        if (selectedMode) params.append('mode', selectedMode);
        if (selectedDifficulty) params.append('difficulty', selectedDifficulty);
        if (selectedMode === 'Contrareloj' && selectedTime) params.append('time', selectedTime);
        fetch(`/scores/filter?${params.toString()}`)
            .then(res => res.json())
            .then(data => {
                renderTopScoresTable(data.topScores);
            });
    }

    function renderTopScoresTable(topScores) {
        let html = `<div class="card my-5">
    <div class="card-header bg-primary text-white">
        <h2 class="mb-0">
            <i class="fas fa-trophy me-2"></i>
            <span class="text-white">Mayores Puntuaciones</span>
        </h2>
    </div>
    <div class="card-body">`;
        if (topScores.length > 0) {
            html += `<div class="table-responsive"><table class="table table-striped table-hover"><thead class="table-dark"><tr><th scope="col">#</th><th scope="col">Usuario</th><th scope="col">Email</th><th scope="col">Mayor Puntuación</th><th scope="col">Total de Partidas</th></tr></thead><tbody>`;
            topScores.forEach((score, index) => {
                html += `<tr class="${index < 3 ? 'table-warning' : ''}">`;
                html += `<td>${index < 3 ? '<i class="fas fa-medal text-warning me-1"></i>' : ''}${index + 1}</td>`;
                html += `<td><strong>${score.name}</strong></td>`;
                html += `<td>${score.email}</td>`;
                html += `<td><span class="badge bg-success fs-6">${Number(score.highest_score ?? 0).toLocaleString()}</span></td>`;
                html += `<td><span class="badge bg-info">${score.total_runs ?? 0}</span></td>`;
                html += `</tr>`;
            });
            html += `</tbody></table></div>`;
        } else {
            html += `<div class="text-center py-4"><i class="fas fa-info-circle text-muted fa-3x mb-3"></i><h4 class="text-muted">No hay puntuaciones disponibles todavía</h4><p class="text-muted">Comienza a jugar para ver las puntuaciones más altas</p></div>`;
        }
        html += `</div></div>`;
        document.getElementById('topScoresTableContainer').innerHTML = html;
    }

    // Modo
    document.querySelectorAll('.filter-mode').forEach(el => {
        el.addEventListener('click', function() {
            selectedMode = this.getAttribute('data-mode');
            // Mostrar/ocultar filtros de tiempo
            const timesGroup = document.getElementById('filter-times-group');
            if (selectedMode === 'Contrareloj') {
                $(timesGroup).fadeIn();
            } else {
                $(timesGroup).fadeOut();
                selectedTime = '';
            }
            updateFiltersUI();
            fetchTopScores();
        });
    });
    // Dificultad
    document.querySelectorAll('.filter-difficulty').forEach(el => {
        el.addEventListener('click', function() {
            selectedDifficulty = this.getAttribute('data-difficulty');
            updateFiltersUI();
            fetchTopScores();
        });
    });
    // Tiempo
    document.querySelectorAll('.filter-time').forEach(el => {
        el.addEventListener('click', function() {
            selectedTime = this.getAttribute('data-time');
            updateFiltersUI();
            fetchTopScores();
        });
    });

    // Inicializar UI
    updateFiltersUI();
});

// Exportar para uso en otros módulos
if (typeof module !== 'undefined' && module.exports) {
    module.exports = ScoresManager;
}
