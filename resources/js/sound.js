import { Howl, Howler } from 'howler';

// Sound player for the game: short effects plus a background playlist. Settings persist per browser.
// Browsers only allow audio after a user gesture; Howler unlocks the audio context on the first one,
// and the game starts the music from its first keypress or click.

const STORAGE_KEY = 'matricular.sound';
const DEFAULTS = { effects: true, music: true, volume: 0.7 };
const MUSIC_LEVEL = 0.45; // background music sits under the effects
const FADE_MS = 1500;
// The finished-game page picks the track up where the game page left it.
const HANDOFF_KEY = 'matricular.music-handoff';
const HANDOFF_MAX_AGE_MS = 30_000;

const effectFiles = import.meta.glob('../sounds/sfx/*.{ogg,mp3}', { eager: true, query: '?url', import: 'default' });
const musicFiles = import.meta.glob('../sounds/music/*.{ogg,mp3}', { eager: true, query: '?url', import: 'default' });

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
        localStorage.setItem(STORAGE_KEY, JSON.stringify({ effects: this.effects, music: this.music, volume: this.volume }));

        if (key === 'volume') {
            Howler.volume(value);
        } else if (key === 'music') {
            value ? this.startMusic() : this.stopMusic();
        }
    },

    preload() {
        loadEffects();
    },

    play(name) {
        if (this.effects) {
            loadEffects()[name]?.play();
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
