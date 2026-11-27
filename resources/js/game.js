// Solo game client. The server decides plate, score, time and word validity; this only sends
// actions and renders the answers.

// Skip key: Command on Apple devices, Control elsewhere.
const IS_APPLE = /Mac|iPhone|iPad/.test(navigator.userAgentData?.platform ?? navigator.platform);
const SKIP_KEY = IS_APPLE ? 'Meta' : 'Control';

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

    init() {
        this.$store.sound.preload();

        if (this.waiting) {
            setTimeout(() => {
                this.waiting = false;
                this.$nextTick(() => this.$refs.word?.focus());
            }, config.startsInMs);
        }

        if (this.remaining !== null) {
            // Count against a deadline, not ticks: background tabs throttle timers.
            // A race's clock starts at the green light.
            this.deadline = Date.now() + config.startsInMs + this.remaining * 1000;
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
            this.end();
        }
    },

    async submit() {
        const word = this.word.trim();

        if (word.length < 3) {
            this.feedback = { kind: 'warn', text: 'Escribe al menos 3 letras.' };
            return;
        }

        if (await this.send(config.urls.word, { word }, 'word')) {
            this.word = '';
        }
    },

    skip() {
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
        if (this.busy || this.over || this.waiting) {
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

        this.newPlate(data.chars.join(''));
        (data.achievements ?? []).forEach((achievement) => this.notify(achievement));
    },

    newPlate(plate) {
        const changed = plate !== this.plate;
        this.plate = plate;

        if (!changed) {
            return;
        }

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
