import { Howl, Howler } from 'howler';

// Sound player for the game: short effects plus a background playlist. Settings persist per browser.
// Browsers only allow audio after a user gesture; Howler unlocks the audio context on the first one,
// and the game starts the music from its first keypress or click.

const STORAGE_KEY = 'matricular.sound';
// horns: whether other players' horns and shouts sound (your own always do while effects are on).
const DEFAULTS = { effects: true, music: true, volume: 0.7, horns: true };
const MUSIC_LEVEL = 0.45; // background music sits under the effects
const FADE_MS = 1500;
// The finished-game page picks the track up where the game page left it.
const HANDOFF_KEY = 'matricular.music-handoff';
const HANDOFF_MAX_AGE_MS = 30_000;

const effectFiles = import.meta.glob('../sounds/sfx/*.{ogg,mp3}', { eager: true, query: '?url', import: 'default' });
const musicFiles = import.meta.glob('../sounds/music/*.{ogg,mp3}', { eager: true, query: '?url', import: 'default' });
// Multiplayer horns and shouts: any file dropped in these folders joins its set. Without files a horn
// is synthesised and a shout stays silent.
const callFiles = {
    horn: import.meta.glob('../sounds/horns/*.{ogg,mp3}', { eager: true, query: '?url', import: 'default' }),
    shout: import.meta.glob('../sounds/shouts/*.{ogg,mp3}', { eager: true, query: '?url', import: 'default' }),
};

// Groups "name.ogg" + "name.mp3" into { name: [ogg, mp3] }; Howler picks the first format the browser plays.
const bySound = (files) => {
    const sounds = {};
    for (const [path, url] of Object.entries(files)) {
        const name = path.split('/').pop().replace(/\.(ogg|mp3)$/, '');
        (sounds[name] ??= []).push(url);
    }
    for (const urls of Object.values(sounds)) {
        urls.sort((a, b) => Number(b.endsWith('.ogg')) - Number(a.endsWith('.ogg')));
    }
    return sounds;
};

// Howl instances live outside the Alpine store so they are never wrapped in reactive proxies.
// Effects are created on demand, so pages without the game download nothing.
let effects = null;
const loadEffects = () => {
    effects ??= Object.fromEntries(
        Object.entries(bySound(effectFiles)).map(([name, src]) => [name, new Howl({ src, preload: true })]),
    );
    return effects;
};
const calls = {};
const loadCalls = (kind) => {
    // Sorted by name, so the same number picks the same sound in every browser.
    calls[kind] ??= Object.entries(bySound(callFiles[kind]))
        .sort(([a], [b]) => a.localeCompare(b))
        .map(([, src]) => new Howl({ src, preload: true }));
    return calls[kind];
};
const HORN_SYNTH_MS = 600;
// A sound that never reports its end (audio still locked, a failed load) frees its button after this.
const CALL_MAX_MS = 6000;
// Synthesised horns when there are no files: two notes a third apart, like a real car horn.
const HORN_NOTES = [
    [349, 440],
    [392, 494],
    [311, 392],
    [440, 554],
];
const playlist = Object.values(bySound(musicFiles));
let track = null;
let trackIndex = Math.floor(Math.random() * Math.max(playlist.length, 1));

const readSettings = () => {
    try {
        return { ...DEFAULTS, ...JSON.parse(localStorage.getItem(STORAGE_KEY) ?? '{}') };
    } catch {
        return { ...DEFAULTS };
    }
};

export default () => ({
    ...readSettings(),
    hasMusic: playlist.length > 0,

    init() {
        Howler.volume(this.volume);

        // Synthesised effects use Howler's audio context, which stays suspended until a user gesture.
        // Pages without any Howl (the lobby) never get Howler's own unlock, so resume it here.
        const unlock = () => Howler.ctx?.state === 'suspended' && Howler.ctx.resume().catch(() => {});
        for (const type of ['pointerdown', 'keydown']) {
            window.addEventListener(type, unlock, { capture: true, passive: true });
        }
    },

    set(key, value) {
        this[key] = value;
        localStorage.setItem(STORAGE_KEY, JSON.stringify({ effects: this.effects, music: this.music, volume: this.volume, horns: this.horns }));

        if (key === 'volume') {
            Howler.volume(value);
        } else if (key === 'music') {
            value ? this.startMusic() : this.stopMusic();
        }
    },

    preload() {
        loadEffects();
    },

    // rate: playback speed, which also shifts the pitch (1 = as recorded).
    play(name, rate = 1) {
        if (!this.effects) {
            return;
        }

        const howl = loadEffects()[name];
        const id = howl?.play();
        if (id !== undefined && rate !== 1) {
            howl.rate(rate, id);
        }
    },

    // A short cartoon "vroom" synthesised with Web Audio, so it needs no sound file: a rough sawtooth
    // whose pitch revs up and settles, through a low-pass filter. Pitched high enough for laptop speakers.
    engine() {
        const ctx = Howler.ctx;
        // Until the first click or key press the context is suspended and the cars move silently.
        if (!this.effects || !ctx || ctx.state !== 'running' || this.volume <= 0) {
            return;
        }

        const now = ctx.currentTime;
        const out = ctx.createGain();
        const filter = ctx.createBiquadFilter();
        filter.type = 'lowpass';
        filter.frequency.setValueAtTime(900, now);
        filter.frequency.exponentialRampToValueAtTime(2200, now + 0.4);
        filter.frequency.exponentialRampToValueAtTime(1000, now + 1.1);
        out.gain.setValueAtTime(0.0001, now);
        out.gain.exponentialRampToValueAtTime(0.5 * this.volume, now + 0.08);
        out.gain.setValueAtTime(0.5 * this.volume, now + 0.7);
        out.gain.exponentialRampToValueAtTime(0.0001, now + 1.2);
        filter.connect(out).connect(ctx.destination);

        for (const [type, detune] of [['sawtooth', 0], ['square', -1200], ['sawtooth', 15]]) {
            const osc = ctx.createOscillator();
            osc.type = type;
            osc.detune.value = detune;
            osc.frequency.setValueAtTime(90, now);
            osc.frequency.exponentialRampToValueAtTime(230, now + 0.4);
            osc.frequency.exponentialRampToValueAtTime(120, now + 1.1);
            osc.connect(filter);
            osc.start(now);
            osc.stop(now + 1.25);
        }
    },

    // A player's horn or shout ($kind): $seed comes from the server, so everyone hears the same one.
    // Other players' horns and shouts can be muted in the sound menu. Returns a promise that settles
    // when the sound ends (right away when nothing plays), so the button waits for it.
    call(kind, seed, mine = false) {
        if (!this.effects || (!mine && !this.horns)) {
            return Promise.resolve();
        }

        const files = loadCalls(kind);
        if (files.length > 0) {
            const howl = files[seed % files.length];
            return new Promise((resolve) => {
                const id = howl.play();
                howl.once('end', resolve, id);
                howl.once('playerror', resolve, id);
                setTimeout(resolve, CALL_MAX_MS);
            });
        }

        const ctx = Howler.ctx;
        if (kind !== 'horn' || !ctx || ctx.state !== 'running' || this.volume <= 0) {
            return Promise.resolve();
        }

        const now = ctx.currentTime;
        const out = ctx.createGain();
        const filter = ctx.createBiquadFilter();
        filter.type = 'lowpass';
        filter.frequency.value = 1800;
        out.gain.setValueAtTime(0.0001, now);
        out.gain.exponentialRampToValueAtTime(0.35 * this.volume, now + 0.03);
        out.gain.setValueAtTime(0.35 * this.volume, now + 0.42);
        out.gain.exponentialRampToValueAtTime(0.0001, now + 0.55);
        filter.connect(out).connect(ctx.destination);

        for (const frequency of HORN_NOTES[seed % HORN_NOTES.length]) {
            const osc = ctx.createOscillator();
            osc.type = 'square';
            osc.frequency.value = frequency;
            osc.connect(filter);
            osc.start(now);
            osc.stop(now + HORN_SYNTH_MS / 1000);
        }

        return new Promise((resolve) => setTimeout(resolve, HORN_SYNTH_MS));
    },

    // Traffic-light beep, synthesised like the engine: short for each red lamp, long and high for green.
    beep(go = false) {
        const ctx = Howler.ctx;
        if (!this.effects || !ctx || ctx.state !== 'running' || this.volume <= 0) {
            return;
        }

        const now = ctx.currentTime;
        const length = go ? 0.7 : 0.25;
        const out = ctx.createGain();
        out.gain.setValueAtTime(0.0001, now);
        out.gain.exponentialRampToValueAtTime(0.3 * this.volume, now + 0.02);
        out.gain.setValueAtTime(0.3 * this.volume, now + length - 0.08);
        out.gain.exponentialRampToValueAtTime(0.0001, now + length);
        out.connect(ctx.destination);

        const osc = ctx.createOscillator();
        osc.type = 'square';
        osc.frequency.value = go ? 880 : 440;
        osc.connect(out);
        osc.start(now);
        osc.stop(now + length);
    },

    startMusic() {
        if (!this.music || playlist.length === 0 || track?.playing()) {
            return;
        }

        track ??= this.loadTrack();
        track.volume(0);
        track.play();
        track.fade(0, MUSIC_LEVEL, FADE_MS);
    },

    stopMusic() {
        const current = track;
        if (!current?.playing()) {
            return;
        }

        current.fade(current.volume(), 0, FADE_MS / 2);
        current.once('fade', () => current.pause());
    },

    // Called right before leaving the game page: remember the track and position so the next page
    // (the finished-game summary) carries on with the same music.
    handOffMusic() {
        if (!track?.playing()) {
            return;
        }

        sessionStorage.setItem(HANDOFF_KEY, JSON.stringify({ index: trackIndex, seek: track.seek(), at: Date.now() }));
    },

    resumeMusic() {
        let handoff = null;
        try {
            handoff = JSON.parse(sessionStorage.getItem(HANDOFF_KEY) ?? 'null');
        } catch {
            // A corrupt entry just means no music to carry on.
        }
        sessionStorage.removeItem(HANDOFF_KEY);

        if (!handoff || Date.now() - handoff.at > HANDOFF_MAX_AGE_MS || !this.music || !playlist[handoff.index]) {
            return;
        }

        trackIndex = handoff.index;
        track = this.loadTrack();
        track.seek(handoff.seek); // Howler queues the seek until the track has loaded
        // Browsers may block audio until the user interacts with this page; then it starts on the first click.
        track.once('playerror', () => track?.once('unlock', () => this.startMusic()));
        this.startMusic();
    },

    // The tracks are not loops: each one fades in, and the next starts when it ends.
    loadTrack() {
        const howl = new Howl({ src: playlist[trackIndex], html5: true });
        howl.once('end', () => {
            howl.unload();
            track = null;
            trackIndex = (trackIndex + 1) % playlist.length;
            this.startMusic();
        });

        return howl;
    },
});
