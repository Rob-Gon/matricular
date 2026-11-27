import Alpine from 'alpinejs';
import game from './game';

Alpine.data('game', game);

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
        return this.isCountdown ? `Tienes ${this.time}.` : 'Sin reloj.';
    },
}));

window.Alpine = Alpine;

Alpine.start();
