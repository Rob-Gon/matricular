// Quick reactions, the horn and the shout, shared by the starting grid (lobby.js) and the race screen
// (race.js). The server checks who may send them and how often, then broadcasts "lobby.reaction" to
// everyone, the sender included; this only shows the bubbles and plays the sounds.

const BUBBLE_MS = 3000;
// Lobby::SOUND_COOLDOWN_SECONDS. The button also waits for the player's own sound to finish.
const COOLDOWN_MS = 3000;
const TOO_FAST = {
    react: 'Más despacio con las reacciones.',
    horn: 'Espera unos segundos para volver a pitar.',
    shout: 'Espera unos segundos para volver a gritar.',
};

const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content ?? '';

export default (urls) => {
    // Per button: when its cooldown ends, whether the player's own sound is still playing, and the
    // cooldown timer. Kept out of Alpine's reactive state.
    const until = {};
    const playing = {};
    const timers = {};

    return {
        // Player id → { emoji, key }; a new key restarts the bubble's pop animation.
        bubbles: {},
        cooling: { horn: false, shout: false },
        reactionNotice: '',

        bubbleFor(id) {
            return this.bubbles[id] ? [this.bubbles[id]] : [];
        },

        onReaction({ id, emoji, horn, shout }) {
            if (emoji) {
                const key = `${id}-${Date.now()}`;
                this.bubbles[id] = { emoji, key };
                setTimeout(() => this.bubbles[id]?.key === key && delete this.bubbles[id], BUBBLE_MS);
                // The same soft pop for every reaction, a little higher or lower each time.
                this.$store.sound.play('pop', 0.8 + Math.random() * 0.5);
            }

            for (const [kind, seed] of [['horn', horn], ['shout', shout]]) {
                if (seed === null || seed === undefined) {
                    continue;
                }

                const mine = id === this.me;
                const ended = this.$store.sound.call(kind, seed, mine);
                if (mine) {
                    playing[kind] = true;
                    this.cooling[kind] = true;
                    ended.then(() => {
                        playing[kind] = false;
                        this.release(kind);
                    });
                }
            }
        },

        react(emoji) {
            this.post('react', { emoji });
        },

        // $kind: "horn" or "shout".
        async honk(kind) {
            if (this.cooling[kind]) {
                return;
            }

            this.hold(kind, COOLDOWN_MS);
            await this.post(kind, {});
        },

        // Keeps a button disabled for at least $ms from now.
        hold(kind, ms) {
            until[kind] = Math.max(until[kind] ?? 0, Date.now() + ms);
            this.cooling[kind] = true;
            clearTimeout(timers[kind]);
            timers[kind] = setTimeout(() => this.release(kind), until[kind] - Date.now());
        },

        // The button comes back once its cooldown is over and the player's own sound has ended.
        release(kind) {
            if (!playing[kind] && Date.now() >= (until[kind] ?? 0)) {
                this.cooling[kind] = false;
            }
        },

        async post(action, body) {
            this.reactionNotice = '';

            try {
                const response = await fetch(urls[action], {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf() },
                    body: JSON.stringify(body),
                });

                if (response.status === 429) {
                    this.reactionNotice = TOO_FAST[action];
                } else if (!response.ok) {
                    this.reactionNotice = 'No se ha podido enviar. Inténtalo de nuevo.';
                }
            } catch {
                this.reactionNotice = 'No hay conexión con el servidor.';
            }
        },
    };
};
