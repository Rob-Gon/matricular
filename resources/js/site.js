import Alpine from 'alpinejs';
import game from './game';
import lobby from './lobby';
import sound from './sound';

Alpine.data('game', game);
Alpine.data('lobby', lobby);
Alpine.store('sound', sound());

// Live summary of the "Prepara el viaje" settings form.
Alpine.data('gameSetup', (initial) => ({
    ...initial,

    get isCountdown() {
        return this.mode === 'Contrareloj';
    },

    get plate() {
        return this.letterSet === 'simple' ? 'BRC' : 'ZRB';
    },

    get rule() {
        const letters = this.plate.split('').join(', ');

        return this.ordered === '1'
            ? `Las letras ${letters} tienen que salir en ese orden dentro de la palabra.`
            : `Vale cualquier palabra con ${letters}, en el orden que quieras.`;
    },

    get examples() {
        const ordered = this.ordered === '1';

        if (this.letterSet === 'simple') {
            return ordered
                ? [{ text: 'barco', ok: true }, { text: 'cabra: la C va antes de la B', ok: false }]
                : [{ text: 'barco', ok: true }, { text: 'cabra', ok: true }];
        }

        return ordered
            ? [{ text: 'zarabanda', ok: true }, { text: 'brazo: la B va antes de la Z', ok: false }]
            : [{ text: 'brazo', ok: true }, { text: 'zarabanda', ok: true }];
    },

    get timeSummary() {
        if (!this.isCountdown) {
            return 'Sin reloj.';
        }

        if (this.time === 'Personalizado') {
            const minutes = Number(this.minutes) || 0;
            return `Tienes ${minutes} ${minutes === 1 ? 'minuto' : 'minutos'}. Las partidas personalizadas tienen su propia clasificación y no cuentan para «Matriculando fuerte».`;
        }

        return `Tienes ${this.time}.`;
    },
}));

window.Alpine = Alpine;

Alpine.start();
