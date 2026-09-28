import { useState } from 'react';

const STORAGE_KEY = 'c4r-theme';

function currentTheme() {
    return document.documentElement.getAttribute('data-bs-theme') === 'dark' ? 'dark' : 'light';
}

/**
 * Switches between light and dark themes and remembers the choice in this browser.
 * The root view (app.blade.php) reads the same storage key before first paint.
 */
export default function ThemeToggle() {
    const [theme, setTheme] = useState(currentTheme);
    const next = theme === 'dark' ? 'light' : 'dark';

    function toggle() {
        document.documentElement.setAttribute('data-bs-theme', next);

        try {
            localStorage.setItem(STORAGE_KEY, next);
        } catch (e) {
            // Storage can be unavailable (private browsing); the switch still applies for this page view.
            console.warn('Could not save theme preference', e);
        }

        setTheme(next);
    }

    return (
        <button type="button" className="btn btn-secondary btn-sm" onClick={toggle} aria-label={`Switch to ${next} mode`}>
            {theme === 'dark' ? '☀ Light' : '☾ Dark'}
        </button>
    );
}
