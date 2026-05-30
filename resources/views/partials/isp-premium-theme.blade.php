{{--
    ISP Premium Glass theme — load on EVERY page (admin, portal, landing, auth).
    CSS rules live in public/css/isp-premium-glass.css (loaded last to override).
--}}
@php
    $premiumCssV = @filemtime(public_path('css/isp-premium-glass.css')) ?: 2;
    $motionJsV = @filemtime(public_path('js/isp-premium-motion.js')) ?: 2;
@endphp
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="{{ asset('css/isp-premium-glass.css') }}?v={{ $premiumCssV }}">
@if ($tailwind ?? false)
<script src="https://cdn.tailwindcss.com"></script>
<script data-cfasync="false">
    tailwind.config = {
        corePlugins: { preflight: false },
        theme: {
            extend: {
                colors: {
                    premium: {
                        purple: '#7c3aed',
                        indigo: '#4f46e5',
                        blue: '#3b82f6',
                        cyan: '#06b6d4',
                        violet: '#8b5cf6',
                        fuchsia: '#d946ef',
                    },
                },
                fontFamily: { sans: ['Outfit', 'ui-sans-serif', 'system-ui', 'sans-serif'] },
                backdropBlur: { premium: '20px', heavy: '32px' },
                borderRadius: { premium: '1.35rem', 'premium-xl': '1.75rem' },
                boxShadow: {
                    neon: '0 0 20px rgba(124, 58, 237, 0.45), 0 0 40px rgba(6, 182, 212, 0.25)',
                    glass: '0 8px 32px -8px rgba(79, 70, 229, 0.18)',
                },
            },
        },
    };
</script>
@endif
<script data-cfasync="false">
    document.documentElement.classList.add('isp-premium-theme');
</script>
@if ($motion ?? true)
<script src="{{ asset('js/isp-premium-motion.js') }}?v={{ $motionJsV }}" data-cfasync="false"></script>
@endif
