import { createInertiaApp } from '@inertiajs/react';
import { createRoot } from 'react-dom/client';

/**
 * Entry point for the admin usage dashboard (login and /usage pages). It is built separately from
 * the public form's app.jsx (see vite.dashboard.config.js) so dashboard code and libraries never
 * reach the public page.
 */
createInertiaApp({
    resolve: (name) => {
        const pages = import.meta.glob('./Dashboard/Pages/**/*.jsx');
        return pages[`./Dashboard/Pages/${name}.jsx`]();
    },
    setup({ el, App, props }) {
        createRoot(el).render(<App {...props} />);
    },
});
