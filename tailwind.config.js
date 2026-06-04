import defaultTheme from 'tailwindcss/defaultTheme';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './resources/**/*.blade.php',
        './resources/**/*.js',
        './app/Filament/**/*.php',
    ],
    theme: {
        extend: {
            colors: {
                premium: {
                    purple: '#7c3aed',
                    violet: '#8b5cf6',
                    indigo: '#4f46e5',
                    blue: '#3b82f6',
                    cyan: '#06b6d4',
                    fuchsia: '#d946ef',
                },
            },
            fontFamily: {
                sans: ['Outfit', ...defaultTheme.fontFamily.sans],
            },
            backdropBlur: {
                premium: '20px',
                heavy: '32px',
            },
            borderRadius: {
                premium: '1.35rem',
                'premium-xl': '1.75rem',
            },
            boxShadow: {
                neon: '0 0 20px rgba(124, 58, 237, 0.45), 0 0 40px rgba(6, 182, 212, 0.25)',
                glass: '0 8px 32px -8px rgba(79, 70, 229, 0.18), 0 24px 64px -24px rgba(6, 182, 212, 0.12)',
            },
            animation: {
                'gradient-border': 'gradient-border 6s ease infinite',
                'mesh-drift': 'mesh-drift 24s ease-in-out infinite alternate',
            },
            keyframes: {
                'gradient-border': {
                    '0%, 100%': { backgroundPosition: '0% 50%' },
                    '50%': { backgroundPosition: '100% 50%' },
                },
                'mesh-drift': {
                    '0%': { opacity: '0.85', transform: 'scale(1)' },
                    '100%': { opacity: '1', transform: 'scale(1.02) translate(-1%, 0.5%)' },
                },
            },
        },
    },
    plugins: [],
    corePlugins: {
        preflight: false,
    },
};
