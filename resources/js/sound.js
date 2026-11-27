import { Howl, Howler } from 'howler';

// Sound player for the game: short effects plus a background playlist. Settings persist per browser.
// Browsers only allow audio after a user gesture; Howler unlocks the audio context on the first one,
// and the game starts the music from its first keypress or click.

const STORAGE_KEY = 'matricular.sound';
const DEFAULTS = { effects: true, music: true, volume: 0.7 };
const MUSIC_LEVEL = 0.45; // background music sits under the effects
const FADE_MS = 1500;

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
