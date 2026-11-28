// Multiplayer race around the solo game screen: the traffic-light start and the live scoreboard.
// The server sets the start and end times and every score; this only displays them.

const RED = '#C8361F';
const GREEN = '#3FAE5A';
const GO_SHOWN_MS = 900;

// Results page: reloads when the race closes or a tiebreak starts or ends, and runs the tiebreak
// countdown and answers. The server decides the winner.
export const raceResults = (config) => ({
    tiebreakOn: (config.tiebreak?.startsInMs ?? 0) <= 0,
    tiebreakCountdown: Math.ceil((config.tiebreak?.startsInMs ?? 0) / 1000),
    tiebreakSeconds: config.tiebreak?.seconds ?? 0,
    tiebreakWord: '',
    tiebreakMessage: '',
    busy: false,

    async init() {
        if (config.tiebreak) {
            this.runTiebreakClock();
        }

        if (!config.watching) {
            return;
        }

        // Fallback for a missed broadcast: the server closes the race or the tiebreak once its time is up.
        this.reloadTimer = setTimeout(() => window.location.reload(), config.reloadInMs);

        await import('./echo');
        window.Echo.join(`lobby.${config.lobbyId}`)
            .listen('.race.tiebreak', () => window.location.reload())
            .listen('.lobby.updated', (state) => {
                if (state.closed) {
                    window.location.assign(`${config.urls.index}?cerrada=1`);
                } else if (state.status !== 'playing') {
                    window.location.reload();
                }
            });
    },

    destroy() {
        clearTimeout(this.reloadTimer);
        clearInterval(this.tiebreakTimer);
        window.Echo?.leave(`lobby.${config.lobbyId}`);
    },

    // Counted against fixed times, not ticks, like the race clock.
    runTiebreakClock() {
        const startAt = Date.now() + config.tiebreak.startsInMs;
        const endAt = Date.now() + config.tiebreak.seconds * 1000;
        const step = () => {
            const now = Date.now();
            this.tiebreakCountdown = Math.max(1, Math.ceil((startAt - now) / 1000));
            this.tiebreakSeconds = Math.max(0, Math.ceil((endAt - Math.max(now, startAt)) / 1000));

            // The plate is only sent once the tiebreak has started.
            if (!this.tiebreakOn && now >= startAt) {
                clearInterval(this.tiebreakTimer);
                window.location.reload();
            }
        };

        if (this.tiebreakOn) {
            this.$nextTick(() => this.$refs.tiebreakWord?.focus());
        }

        step();
        this.tiebreakTimer = setInterval(step, 200);
    },

    async answerTiebreak() {
        const word = this.tiebreakWord.trim();
        if (this.busy || word.length < 3) {
            this.tiebreakMessage = word.length < 3 ? 'Escribe al menos 3 letras.' : this.tiebreakMessage;
            return;
        }

        this.busy = true;
        try {
            const response = await fetch(config.urls.tiebreak, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': config.csrf },
                body: JSON.stringify({ word }),
            });
            const data = await response.json().catch(() => ({}));
            this.tiebreakMessage = data.message ?? 'No se ha podido comprobar. Inténtalo de nuevo.';

            if (response.ok && data.success) {
                this.$store.sound.play('correct');
            } else {
                this.tiebreakWord = '';
                this.$store.sound.play('warn');
            }
        } catch {
            this.tiebreakMessage = 'No hay conexión con el servidor.';
        } finally {
            this.busy = false;
        }
    },
});

export default (config) => ({
    standings: config.standings,
    me: config.me,
    // null: no traffic light; 0–3: red lamps lit; 'go': green.
    lights: null,

    async init() {
        if (config.startsInMs > 0) {
            this.startLights(Date.now() + config.startsInMs);
        }

        // Echo and pusher-js only load on lobby pages.
        await import('./echo');
        window.Echo.join(`lobby.${config.lobbyId}`)
            .listen('.race.scored', ({ id, score }) => this.updateScore(id, score))
            // "First wins": handed to the game screen, with the winner's name from the scoreboard.
            .listen('.race.plate', (data) => {
                const detail = { ...data, mine: data.winner === this.me, winnerName: this.standings.find((row) => row.id === data.winner)?.name ?? null };
                window.dispatchEvent(new CustomEvent('first-plate', { detail }));
            })
            .listen('.lobby.updated', (state) => {
                if (state.closed) {
                    window.location.assign(`${config.urls.lobby}?cerrada=1`);
                } else if (!state.players.some((player) => player.id === this.me)) {
                    window.location.assign(`${config.urls.lobby}?expulsado=1`);
                }
            });
    },

    destroy() {
        clearInterval(this.lightsTimer);
        window.Echo?.leave(`lobby.${config.lobbyId}`);
    },

    get progress() {
        const mine = this.standings.find((row) => row.id === this.me);
        return mine ? `Vas ${mine.position}.º de ${this.standings.length}` : '';
    },

    get lightsWord() {
        if (this.lights === 'go') {
            return '¡Ya!';
        }

        return this.lights === 0 ? 'Preparados' : String(4 - this.lights);
    },

    lampOn(lamp) {
        return this.lights === 'go' || (this.lights ?? 0) >= lamp;
    },

    lampStyle(lamp) {
        if (!this.lampOn(lamp)) {
            return { background: '#2B2B28', boxShadow: 'inset 0 6px 0 rgba(255,255,255,.06)' };
        }

        const go = this.lights === 'go';
        return { background: go ? GREEN : RED, boxShadow: `0 0 34px 10px ${go ? 'rgba(63,174,90,.7)' : 'rgba(200,54,31,.75)'}` };
    },

    // One lamp a second for the last three seconds, then green. Counted against the start time, not ticks.
    startLights(startAt) {
        const step = () => {
            const left = startAt - Date.now();
            const next = left <= 0 ? 'go' : Math.min(3, Math.max(0, 4 - Math.ceil(left / 1000)));

            if (next !== this.lights) {
                this.lights = next;
                if (next === 'go' || next > 0) {
                    this.$store.sound.beep(next === 'go');
                }
            }

            if (next === 'go') {
                clearInterval(this.lightsTimer);
                setTimeout(() => (this.lights = null), GO_SHOWN_MS);
            }
        };

        step();
        this.lightsTimer = setInterval(step, 100);
    },

    updateScore(id, score) {
        const row = this.standings.find((entry) => entry.id === id);
        if (!row) {
            return;
        }

        row.score = score;
        // Best first; equal scores share the position (1, 1, 3).
        this.standings.sort((a, b) => b.score - a.score || a.id - b.id);
        this.standings.forEach((entry, i) => {
            const previous = this.standings[i - 1];
            entry.position = previous && previous.score === entry.score ? previous.position : i + 1;
        });
    },
});
