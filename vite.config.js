import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/js/site.js',
                'resources/sass/app.scss',
                'resources/sass/components/_menu-button.scss',
                'resources/sass/components/_modal.scss',
                'resources/sass/main/_game.scss',
                'resources/sass/main/_home.scss',
                'resources/sass/main/_menu.scss',
                'resources/js/app.js',
                'resources/js/main/game-manager.js',
            ],
            refresh: true,
        }),
        tailwindcss(),
    ],
});
