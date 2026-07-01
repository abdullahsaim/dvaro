import defaultTheme from 'tailwindcss/defaultTheme';

/** @type {import('tailwindcss').Config} */
export default {
    // Dark/light mode is a per-user preference toggled via the `dark` class
    // on the <html> element (applied synchronously by the anti-FOUC script in
    // app.blade.php before first paint).
    darkMode: 'class',
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.vue',
        './resources/js/**/*.js',
    ],
    theme: {
        extend: {
            fontFamily: {
                // Inter is loaded in app.blade.php (preconnected Google Fonts)
                // with a full system fallback chain so text renders instantly.
                sans: ['Inter', 'Figtree', ...defaultTheme.fontFamily.sans],
            },

            // ── DESIGN TOKENS ──────────────────────────────────────────────
            // Tokens are ADDITIVE: Tailwind's default palette/scales are left
            // intact so existing (un-restyled) module pages stay pixel-stable.
            // The new design system uses the tokens below exclusively.
            colors: {
                // Monochrome backbone. Named `ink` so it never silently shadows
                // Tailwind's built-in `neutral`. near-white (50) → near-black (950).
                ink: {
                    50: '#fafafa',
                    100: '#f4f4f5',
                    200: '#e4e4e7',
                    300: '#d4d4d8',
                    400: '#a1a1aa',
                    500: '#71717a',
                    600: '#52525b',
                    700: '#3f3f46',
                    800: '#27272a',
                    900: '#18181b',
                    950: '#09090b',
                },

                // Accent = PURE MONOCHROME this session. Aliased to the ink scale
                // so interactive elements (focus rings, links) read as ink, and a
                // future switch to a single hue is a one-object change here.
                accent: {
                    50: '#fafafa',
                    100: '#f4f4f5',
                    200: '#e4e4e7',
                    300: '#d4d4d8',
                    400: '#a1a1aa',
                    500: '#71717a',
                    600: '#52525b',
                    700: '#3f3f46',
                    800: '#27272a',
                    900: '#18181b',
                    950: '#09090b',
                },

                // Status colors — MUTED / desaturated, badges only (vehicle,
                // invoice, agreement, lead, maintenance statuses). Never used as
                // a UI accent. Each palette carries light-bg + dark-bg stops.
                success: {
                    50: '#eef4f0',
                    100: '#d7e7dd',
                    500: '#5f8d6e',
                    600: '#4f7a5d',
                    700: '#3f6149',
                    900: '#1f3327',
                },
                warning: {
                    50: '#f6f1e7',
                    100: '#ece0c8',
                    500: '#b08a4a',
                    600: '#9a7639',
                    700: '#7d5f2e',
                    900: '#3f2f18',
                },
                danger: {
                    50: '#f6ecec',
                    100: '#ecd6d6',
                    500: '#b05a5a',
                    600: '#9a4747',
                    700: '#7d3a3a',
                    900: '#3f1d1d',
                },
                info: {
                    50: '#eceff4',
                    100: '#d6dded',
                    500: '#5a6f9a',
                    600: '#4a5d85',
                    700: '#3a4a6d',
                    900: '#1d2638',
                },

                // Existing indigo brand — KEPT this session (some un-restyled
                // pages reference brand-*). Retires once pages migrate to `ink`.
                brand: {
                    50: '#eef2ff',
                    100: '#e0e7ff',
                    200: '#c7d2fe',
                    300: '#a5b4fc',
                    400: '#818cf8',
                    500: '#6366f1',
                    600: '#4f46e5',
                    700: '#4338ca',
                    800: '#3730a3',
                    900: '#312e81',
                    950: '#1e1b4b',
                },
            },

            // New type tokens only (defaults untouched → no page regressions).
            fontSize: {
                '2xs': ['0.6875rem', { lineHeight: '0.875rem', letterSpacing: '0.02em' }],
                display: ['2.5rem', { lineHeight: '2.75rem', letterSpacing: '-0.025em' }],
            },

            // Named radii for the system (defaults left intact).
            borderRadius: {
                control: '0.5rem',
                card: '0.75rem',
            },

            // Subtle, layered shadows — no heavy drops (defaults left intact).
            boxShadow: {
                subtle: '0 1px 2px 0 rgb(0 0 0 / 0.04)',
                card: '0 1px 3px 0 rgb(0 0 0 / 0.06), 0 1px 2px -1px rgb(0 0 0 / 0.05)',
                pop: '0 4px 16px -4px rgb(0 0 0 / 0.12), 0 2px 6px -2px rgb(0 0 0 / 0.08)',
            },

            // Collapsed-sidebar width (expanded uses w-64).
            spacing: {
                18: '4.5rem',
            },

            transitionTimingFunction: {
                smooth: 'cubic-bezier(0.4, 0, 0.2, 1)',
            },

            keyframes: {
                'fade-in': {
                    from: { opacity: '0' },
                    to: { opacity: '1' },
                },
                'fade-in-up': {
                    from: { opacity: '0', transform: 'translateY(6px)' },
                    to: { opacity: '1', transform: 'translateY(0)' },
                },
                'scale-in': {
                    from: { opacity: '0', transform: 'scale(0.97)' },
                    to: { opacity: '1', transform: 'scale(1)' },
                },
                'slide-in-right': {
                    from: { transform: 'translateX(100%)' },
                    to: { transform: 'translateX(0)' },
                },
            },
            animation: {
                'fade-in': 'fade-in 0.2s ease-out',
                'fade-in-up': 'fade-in-up 0.25s ease-out',
                'scale-in': 'scale-in 0.15s ease-out',
                'slide-in-right': 'slide-in-right 0.25s cubic-bezier(0.4, 0, 0.2, 1)',
            },
        },
    },
    plugins: [],
};
