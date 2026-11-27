// Lobby screen: the starting grid kept live over a Reverb presence channel. The server owns the state;
// every change arrives as a full "lobby.updated" snapshot and the grid animates from the old layout to
// the new one (FLIP): new cars drive up into their bay, the cars behind a freed bay move forward.

const MOVE_MS = 1000;
const MIN_PLAYERS = 2;
const ENTER_MS = 1700;

export default (config) => ({
    state: config.state,
    me: config.me,
    present: null,
    editing: false,
    form: {},
    errors: {},
    busy: false,
    copied: false,
    kickTarget: null,

    async init() {
        this.resetForm();

        // Echo and pusher-js only load on this page.
        await import('./echo');
        window.Echo.join(`lobby.${config.lobbyId}`)
            .here((users) => (this.present = users.map((user) => user.id)))
            .joining((user) => this.present?.push(user.id))
            .leaving((user) => (this.present = (this.present ?? []).filter((id) => id !== user.id)))
            .listen('.lobby.updated', (state) => this.apply(state));
    },

    destroy() {
        window.Echo?.leave(`lobby.${config.lobbyId}`);
    },

    get isHost() {
        return this.state.hostId === this.me;
    },

    get mine() {
        return this.state.players.find((player) => player.id === this.me);
    },

    get freeBays() {
        return Math.max(0, 16 - this.state.players.length);
    },

    get waitingFor() {
        return this.state.players.filter((player) => !player.ready).map((player) => player.name);
    },

    // The server checks the same conditions; this only enables the button and explains why it waits.
    get canStart() {
        return this.state.format !== 'first' && this.state.players.length >= MIN_PLAYERS && this.waitingFor.length === 0;
    },

    get startHint() {
        if (this.state.format === 'first') {
            return '«El primero gana» llega pronto: por ahora elige «Carrera» o «Rondas».';
        }

        if (this.state.players.length < MIN_PLAYERS) {
            return 'Comparte el código: hace falta al menos otro jugador.';
        }

        if (this.waitingFor.length) {
            return `Falta que estén listos: ${this.waitingFor.join(', ')}.`;
        }

        return this.isHost ? '¡Todos listos! Da la salida cuando quieras.' : '¡Todos listos! El anfitrión da la salida.';
    },

    status(player) {
        if (player.id === this.state.hostId) {
            return '👑 Anfitrión';
        }

        return player.ready ? 'Listo' : 'Esperando';
    },

    connected(player) {
        return this.present === null || this.present.includes(player.id);
    },

    async apply(next) {
        if (next.closed) {
            window.location.assign(`${config.urls.index}?cerrada=1`);
            return;
        }

        if (!next.players.some((player) => player.id === this.me)) {
            window.location.assign(`${config.urls.index}?expulsado=1`);
            return;
        }

        if (next.status === 'playing') {
            window.location.assign(config.urls.race);
            return;
        }

        const before = this.positions();
        this.state = next;
        if (!this.editing) {
            this.resetForm();
        }

        await this.$nextTick();
        this.animate(before);
    },

    positions() {
        return new Map([...this.$refs.grid.querySelectorAll('[data-player]')].map((el) => [el.dataset.player, el.getBoundingClientRect()]));
    },

    animate(before) {
        let moved = false;

        for (const el of this.$refs.grid.querySelectorAll('[data-player]')) {
            const old = before.get(el.dataset.player);
            const now = el.getBoundingClientRect();
            const keyframes = old
                ? [{ transform: `translate(${old.left - now.left}px, ${old.top - now.top}px)` }, { transform: 'none' }]
                : [{ transform: 'translateY(190px)', opacity: 0 }, { opacity: 1, offset: 0.3 }, { transform: 'none', opacity: 1 }];

            if (old && old.left === now.left && old.top === now.top) {
                continue;
            }

            moved = true;
            el.animate(keyframes, { duration: old ? MOVE_MS : ENTER_MS, easing: 'cubic-bezier(.25,.7,.3,1)' });
        }

        if (moved) {
            this.$store.sound.engine();
        }
    },

    async send(method, url, body) {
        this.busy = true;
        this.errors = {};

        let response;
        let data;
        try {
            response = await fetch(url, {
                method,
                headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': config.csrf },
                body: JSON.stringify(body),
            });
            data = await response.json().catch(() => ({}));
        } catch {
            this.errors = { general: 'No hay conexión con el servidor.' };
            return false;
        } finally {
            this.busy = false;
        }

        if (response.status === 422) {
            this.errors = Object.fromEntries(Object.entries(data.errors ?? {}).map(([key, messages]) => [key, messages[0]]));
            return false;
        }

        // Only a lobby snapshot may replace the grid; anything else is a server-side failure.
        if (!response.ok || !Array.isArray(data.players)) {
            this.errors = { general: 'No se ha podido guardar. Recarga la página e inténtalo de nuevo.' };
            return false;
        }

        await this.apply(data);
        return true;
    },

    start() {
        this.send('POST', config.urls.start, {});
    },

    toggleReady() {
        this.send('POST', config.urls.ready, { ready: !this.mine.ready });
    },

    pickColor(color) {
        this.send('POST', config.urls.color, { color });
    },

    askKick(player) {
        this.kickTarget = player;
        this.$refs.kickDialog.showModal();
    },

    async kick() {
        const player = this.kickTarget;
        this.$refs.kickDialog.close();
        if (player) {
            await this.send('POST', config.urls.kick, { player: player.id });
        }
    },

    resetForm() {
        const s = this.state.settings;
        this.form = {
            format: this.state.format,
            letter_set: s.letter_set,
            ordered: s.ordered ? '1' : '0',
            tie_resolution: s.tie_resolution,
            countdown_time: s.countdown_time ?? '2 minutos',
            custom_minutes: s.countdown_time === 'Personalizado' ? Math.round((s.countdown_seconds ?? 600) / 60) : 10,
            rounds: s.rounds ?? 10,
            round_seconds: s.round_seconds ?? 20,
            plates: s.plates ?? 15,
            plate_seconds: s.plate_seconds ?? 30,
            tie_window_ms: String(s.tie_window_ms ?? 150),
        };
    },

    async saveRules() {
        if (await this.send('PUT', config.urls.rules, this.form)) {
            this.editing = false;
        }
    },

    async copyCode() {
        try {
            await navigator.clipboard.writeText(this.state.code);
        } catch {
            // navigator.clipboard only exists on HTTPS and localhost; plain-HTTP hosts use the old API.
            const field = Object.assign(document.createElement('textarea'), { value: this.state.code, readOnly: true });
            field.className = 'fixed -left-[9999px]';
            document.body.append(field);
            field.select();
            const copied = document.execCommand('copy');
            field.remove();

            if (!copied) {
                this.errors = { general: `No se ha podido copiar. El código es ${this.state.code}.` };
                return;
            }
        }

        this.copied = true;
        setTimeout(() => (this.copied = false), 2000);
    },
});
