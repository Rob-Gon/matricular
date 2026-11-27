// Multiplayer race around the solo game screen: the traffic-light start and the live scoreboard.
// The server sets the start and end times and every score; this only displays them.

const RED = '#C8361F';
const GREEN = '#3FAE5A';
const GO_SHOWN_MS = 900;

// Results page: until every player has crossed the line, reload when the race closes.
export const raceResults = (config) => ({
    async init() {
        if (config.finished) {
            return;
        }

        // Fallback for a missed broadcast: the server closes the race once its clock has run out.
        this.reloadTimer = setTimeout(() => window.location.reload(), config.reloadInMs);

        await import('./echo');
        window.Echo.join(`lobby.${config.lobbyId}`).listen('.lobby.updated', (state) => {
            if (state.closed) {
                window.location.assign(`${config.urls.index}?cerrada=1`);
            } else if (state.status !== 'playing') {
                window.location.reload();
            }
        });
    },

    destroy() {
        clearTimeout(this.reloadTimer);
        window.Echo?.leave(`lobby.${config.lobbyId}`);
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
