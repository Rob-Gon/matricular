// Solo game client. The server decides plate, score, time and word validity; this only sends
// actions and renders the answers.

// Skip key: Command on Apple devices, Control elsewhere.
const IS_APPLE = /Mac|iPhone|iPad/.test(navigator.userAgentData?.platform ?? navigator.platform);
const SKIP_KEY = IS_APPLE ? 'Meta' : 'Control';

// Plate requests retried every 300 ms: a clock a little ahead of the server's, or a "first wins"
// plate still waiting for a word being judged.
const SYNC_ATTEMPTS = 25;

const randomDigits = () => String(Math.floor(Math.random() * 10000)).padStart(4, '0');

const FAILURES = {
    419: 'La sesión ha caducado. Recarga la página para seguir jugando.',
    429: 'Vas demasiado rápido. Espera un momento y vuelve a intentarlo.',
};

export default (config) => ({
    plate: config.plate,
    digits: config.digits,
    score: config.score,
    remaining: config.remaining,
    word: '',
    busy: false,
    over: false,
    feedback: null,
    toasts: [],
    skipKey: SKIP_KEY,
    skipKeyLabel: IS_APPLE ? '⌘' : 'Ctrl',
    skipKeyAlone: false,
    // Multiplayer: no answers until the traffic light turns green.
    waiting: config.startsInMs > 0,
    // Multiplayer "rounds": the same plate for everyone, a new one each round.
    round: config.rounds?.current ?? 0,
    rounds: config.rounds?.count ?? 0,
    // Multiplayer "first wins": rounds that end when someone takes the plate; one try per plate.
    first: config.rounds?.first ?? false,
    locked: config.rounds?.locked ?? false,
    closedPlate: -1,

    init() {
        this.$store.sound.preload();

        if (this.waiting) {
            // The first plate is not in the page: the server hands it out at the green light.
            setTimeout(() => {
                this.waiting = false;
                this.syncPlate(0);
                this.$nextTick(() => this.$refs.word?.focus());
            }, config.startsInMs);
        }

        if (this.remaining !== null) {
            // Count against a deadline, not ticks: background tabs throttle timers.
            // A race's clock starts at the green light; in rounds the clock is the round's.
            this.deadline = this.rounds
                ? Date.now() + config.rounds.endsInMs
                : Date.now() + config.startsInMs + this.remaining * 1000;
            this.timer = setInterval(() => this.tick(), 250);
        }
    },

    destroy() {
        clearInterval(this.timer);
    },

    tick() {
        const before = this.remaining;
        this.remaining = Math.min(config.total ?? Infinity, Math.max(0, Math.ceil((this.deadline - Date.now()) / 1000)));

        // The fuel gauge ticks through the last ten seconds.
        if (this.remaining !== before && this.remaining > 0 && this.remaining <= 10) {
            this.$store.sound.play('tick');
        }

        if (this.remaining === 0) {
            if (this.first) {
                this.closePlate(this.round, config.rounds.pauseMs);
            } else {
                this.rounds && this.round < this.rounds - 1 ? this.nextRound() : this.end();
            }
        }
    },

    // "First wins": the plate stopped taking answers; the next one is fetched once the server deals it.
    closePlate(number, delayMs) {
        if (this.closedPlate >= number) {
            return;
        }

        this.closedPlate = number;
        this.locked = true;
        setTimeout(() => this.syncPlate(number + 1), delayMs);
    },

    // Broadcast by the server (see race.js): the plate closed, the next one is ready, or the match is over.
    onFirstPlate({ plate, next_in_ms: nextInMs, winnerName, mine, finished }) {
        if (finished) {
            this.end();
        } else if (nextInMs === null) {
            if (plate > this.round) {
                this.syncPlate(plate);
            }
        } else if (plate === this.round) {
            if (!mine) {
                this.feedback = { kind: 'warn', text: winnerName ? `¡${winnerName} se lleva la matrícula!` : 'Nadie ha sacado esta matrícula.' };
            }
            this.closePlate(plate, nextInMs);
        }
    },

    // Each "first wins" plate brings its own clock.
    startPlate(data) {
        if (data.round > this.round) {
            this.feedback = null;
        }

        this.round = data.round;
        this.locked = data.first.locked;
        this.deadline = Date.now() + data.first.ends_in_ms;
        this.remaining = Math.min(config.total, Math.ceil(data.first.ends_in_ms / 1000));
        // A disabled input loses focus.
        this.$nextTick(() => this.$refs.word?.focus());
    },

    nextRound() {
        this.round++;
        this.deadline += config.rounds.seconds * 1000;
        this.remaining = config.total;
        this.feedback = null;
        this.syncPlate(this.round);
    },

    // Asks the server for the plate to play now; retried briefly if this clock is a little ahead of it.
    async syncPlate(expectedRound, attempt = 0) {
        try {
            const response = await fetch(config.urls.sync, {
                method: 'POST',
                headers: { Accept: 'application/json', 'X-CSRF-TOKEN': config.csrf },
            });
            const data = await response.json().catch(() => ({}));

            if (data.game_over) {
                this.end();
                return;
            }

            if (response.ok && (data.round ?? expectedRound) >= expectedRound) {
                // A plate already dealt by an earlier answer is not dealt again.
                if (this.first && data.round < this.round) {
                    return;
                }

                this.score = data.score;
                if (this.first) {
                    this.startPlate(data);
                }
                this.newPlate(data.chars.join(''));
                return;
            }
        } catch {
            // Retried below.
        }

        if (attempt < SYNC_ATTEMPTS && !this.over) {
            setTimeout(() => this.syncPlate(expectedRound, attempt + 1), 300);
        } else {
            this.feedback = { kind: 'warn', text: 'No llega la matrícula nueva. Recarga la página.' };
        }
    },

    async submit() {
        const word = this.word.trim();

        if (word.length < 3) {
            this.feedback = { kind: 'warn', text: 'Escribe al menos 3 letras.' };
            return;
        }

        if (await this.send(config.urls.word, { word, round: this.round }, 'word')) {
            this.word = '';
        }
    },

    skip() {
        if (!config.canSkip) {
            return;
        }

        this.send(config.urls.plate, {}, 'skip');
    },

    // The skip key pressed and released on its own skips the plate; shortcuts such as Ctrl+Z do not.
    onKeydown(event) {
        // The first key press is the user gesture browsers require before playing audio.
        this.$store.sound.startMusic();

        if (event.key !== SKIP_KEY) {
            this.skipKeyAlone = false;
        } else if (!event.repeat) {
            this.skipKeyAlone = true;
        }
    },

    onKeyup(event) {
        if (event.key !== SKIP_KEY || !this.skipKeyAlone) {
            return;
        }

        this.skipKeyAlone = false;

        if (!this.$refs.confirmEnd?.open) {
            this.skip();
        }
    },

    onPointerdown() {
        this.skipKeyAlone = false;
        this.$store.sound.startMusic();
    },

    async send(url, body, action) {
        if (this.busy || this.over || this.waiting || this.locked) {
            return false;
        }

        this.busy = true;

        try {
            const response = await fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': config.csrf,
                },
                body: JSON.stringify(body),
            });
            const data = await response.json().catch(() => ({}));

            // 409: the run is already over on the server (time ran out).
            if (response.status === 409) {
                this.end();
                return false;
            }

            if (!response.ok) {
                this.$store.sound.play('warn');
                this.feedback = {
                    kind: 'warn',
                    text: FAILURES[response.status] ?? data.message ?? 'No se ha podido comprobar. Inténtalo de nuevo, no has perdido puntos.',
                };
                return false;
            }

            this.apply(data, action);
            return true;
        } catch {
            this.$store.sound.play('warn');
            this.feedback = { kind: 'warn', text: 'No hay conexión con el servidor. Inténtalo de nuevo, no has perdido puntos.' };
            return false;
        } finally {
            this.busy = false;
            this.$nextTick(() => this.$refs.word?.focus());
        }
    },

    apply(data, action) {
        const lostPoints = data.score < this.score;
        this.$store.sound.play(data.success ? 'correct' : action === 'skip' ? 'skip' : lostPoints ? 'wrong' : 'warn');

        this.feedback = { kind: data.success ? 'ok' : lostPoints ? 'fail' : 'warn', text: data.message };
        this.score = data.score;

        // A late answer to the previous round must not bring its plate back.
        if (!this.rounds || data.round === this.round) {
            this.newPlate(data.chars.join(''));
            if (data.first) {
                this.locked = data.first.locked;
            }
        }
        (data.achievements ?? []).forEach((achievement) => this.notify(achievement));
    },

    newPlate(plate) {
        const changed = plate !== this.plate;
        this.plate = plate;

        if (!changed) {
            return;
        }

        // Half a word typed for the old plate is useless for the new one (another player may have taken it).
        this.word = '';
        this.digits = randomDigits();

        // Restart the drop animation on the same element.
        const element = this.$refs.plate;
        element?.classList.remove('animate-plate-drop');
        void element?.offsetWidth;
        element?.classList.add('animate-plate-drop');
    },

    notify(achievement) {
        const toast = { id: `${achievement.key}-${Date.now()}`, name: achievement.name, icon: achievement.icon };
        this.toasts.push(toast);
        setTimeout(() => this.$store.sound.play('achievement'), 300);
        setTimeout(() => this.dismiss(toast.id), 6000);
    },

    dismiss(id) {
        this.toasts = this.toasts.filter((toast) => toast.id !== id);
    },

    async end() {
        if (this.over) {
            return;
        }

        this.over = true;
        clearInterval(this.timer);
        // The music carries on into the summary page.
        this.$store.sound.handOffMusic();

        try {
            await fetch(config.urls.end, {
                method: 'POST',
                headers: { Accept: 'application/json', 'X-CSRF-TOKEN': config.csrf },
            });
        } catch {
            // The summary page closes the run itself when its time is up.
        }

        window.location.assign(config.urls.summary);
    },
});
