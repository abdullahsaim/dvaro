// Dark/light color-mode management (CLAUDE.md: per-user preference).
//
// Source of truth on load is the `dark` class already set on <html> by the
// synchronous anti-FOUC script in app.blade.php — so there is never a flash of
// the wrong theme. This composable layers reactive state, an explicit toggle,
// localStorage caching and silent server persistence on top of that.
//
// Persistence model (last-write-wins with a server fallback):
//  - localStorage is the immediate driver and is read by the inline head script.
//  - A logged-in user's saved profile value (shared as the `colorMode` Inertia
//    prop) is adopted ONLY on a browser that has no local preference yet (first
//    visit / new device). On a known device the local cache wins, so a freshly
//    made toggle is never clobbered by a stale server value.
//  - Every explicit change writes localStorage AND PUTs to the per-guard
//    endpoint (url provided by the colorMode prop) so it follows the user.

import { ref } from 'vue';
import { usePage } from '@inertiajs/vue3';

const STORAGE_KEY = 'color-mode';
const VALID = ['light', 'dark', 'system'];

// ── Module-level singleton state: every component shares one source of truth ──
let hadLocalAtInit = false;

function prefersDark() {
    return window.matchMedia('(prefers-color-scheme: dark)').matches;
}

function readInitialMode() {
    try {
        const stored = localStorage.getItem(STORAGE_KEY);
        if (VALID.includes(stored)) {
            hadLocalAtInit = true;
            return stored;
        }
    } catch (e) { /* storage disabled — fall through to system */ }
    return 'system';
}

const mode = ref(readInitialMode());
const isDark = ref(document.documentElement.classList.contains('dark'));

function effectiveDark(m) {
    return m === 'dark' || (m === 'system' && prefersDark());
}

function apply(m) {
    const dark = effectiveDark(m);
    document.documentElement.classList.toggle('dark', dark);
    isDark.value = dark;
}

// While in 'system' mode, track live OS theme changes. Bound once.
let mediaListenerBound = false;
function bindMediaListener() {
    if (mediaListenerBound) return;
    mediaListenerBound = true;
    try {
        window
            .matchMedia('(prefers-color-scheme: dark)')
            .addEventListener('change', () => {
                if (mode.value === 'system') apply('system');
            });
    } catch (e) { /* older browsers without addEventListener on MQL */ }
}

export function useColorMode() {
    const page = usePage();

    function persist(m) {
        try {
            localStorage.setItem(STORAGE_KEY, m);
        } catch (e) { /* ignore */ }

        // Push to the authenticated user's profile (silent XHR). The endpoint
        // url + current value arrive via the shared `colorMode` Inertia prop;
        // absent on public/guest pages, where we only cache locally.
        const pref = page.props.colorMode;
        if (pref?.url && window.axios) {
            window.axios.put(pref.url, { color_mode: m }).catch(() => {});
        }
    }

    function setMode(m) {
        if (!VALID.includes(m)) return;
        mode.value = m;
        apply(m);
        persist(m);
    }

    // Explicit toggle resolves to a concrete light/dark (drops 'system').
    function toggle() {
        setMode(isDark.value ? 'light' : 'dark');
    }

    // Adopt the server-saved preference on a fresh browser only (see header).
    function syncFromServer() {
        if (hadLocalAtInit) return;
        const serverValue = page.props.colorMode?.value;
        if (VALID.includes(serverValue) && serverValue !== mode.value) {
            setMode(serverValue);
        }
    }

    bindMediaListener();

    return { mode, isDark, toggle, setMode, syncFromServer };
}
