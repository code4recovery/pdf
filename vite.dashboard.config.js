import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import react from '@vitejs/plugin-react';

/**
 * The admin usage dashboard's build, kept apart from the public form's (vite.config.js):
 * its own entry point, output folder and dev-server hot file.
 */
export default defineConfig({
    plugins: [
        laravel({
            input: 'resources/js/dashboard.jsx',
            buildDirectory: 'build-dashboard',
            hotFile: 'public/dashboard.hot',
            refresh: true,
        }),
        react(),
    ],
    server: {
        port: 5174,
    },
});
