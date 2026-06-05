{{-- Inline fallback when reseller-portal-pro.css is blocked or OPcache served stale HTML. Keep in sync with public/css/reseller-portal-pro.css shell rules. --}}
<style id="rsl-critical">
:root{--rsl-font:'Plus Jakarta Sans',system-ui,sans-serif;--rsl-sidebar-w:260px;--rsl-teal-600:#0d9488;--rsl-teal-500:#14b8a6;--rsl-page-bg:#f1f5f9;--rsl-content-bg:#f1f5f9;--rsl-surface:#fff;--rsl-border:#e2e8f0;--rsl-text:#0f172a;--rsl-text-muted:#64748b;--rsl-accent-muted:rgba(13,148,136,.12);--rsl-sidebar-bg:linear-gradient(180deg,#0f172a 0%,#134e4a 45%,#0f766e 100%);--rsl-appbar-bg:rgba(255,255,255,.92);--rsl-dock-bg:rgba(255,255,255,.96)}
*,*::before,*::after{box-sizing:border-box}
body.rsl-page{margin:0;min-height:100vh;font-family:var(--rsl-font);font-size:.9375rem;line-height:1.55;color:var(--rsl-text);background:var(--rsl-page-bg);-webkit-font-smoothing:antialiased}
body.rsl-page::before{display:none!important}
svg{max-width:100%;height:auto}
.rsl-app{display:grid;min-height:100vh;min-height:100dvh;grid-template-columns:1fr;grid-template-rows:auto 1fr;background:var(--rsl-content-bg)}
@media(min-width:1024px){.rsl-app{grid-template-columns:var(--rsl-sidebar-w) 1fr;grid-template-rows:1fr}}
.rsl-sidebar{display:none}
@media(min-width:1024px){.rsl-sidebar{display:flex;flex-direction:column;position:sticky;top:0;height:100vh;width:var(--rsl-sidebar-w);background:var(--rsl-sidebar-bg);z-index:40}}
.rsl-sidebar-link{display:flex;align-items:center;gap:.65rem;padding:.55rem .75rem;color:rgba(241,245,249,.9);text-decoration:none;font-size:.8125rem;font-weight:600;border-radius:.5rem}
.rsl-sidebar-link--active{background:rgba(20,184,166,.22);color:#fff}
.rsl-app-content{display:flex;flex-direction:column;min-width:0;min-height:0}
.rsl-appbar{position:sticky;top:0;z-index:30;background:var(--rsl-appbar-bg);border-bottom:1px solid var(--rsl-border);backdrop-filter:blur(12px)}
.rsl-appbar-inner{display:flex;align-items:center;justify-content:space-between;gap:.75rem;padding:.65rem 1rem;max-width:80rem;margin:0 auto}
.rsl-main{flex:1;padding:1rem 1rem 5rem;max-width:80rem;margin:0 auto;width:100%}
@media(min-width:1024px){.rsl-main{padding:1.25rem 1.5rem 1.5rem}}
@media(max-width:1023.98px){.rsl-dock.rsl-only-mobile,.rsl-only-mobile.rsl-dock,nav.rsl-dock{display:flex!important;flex-direction:row!important;position:fixed;bottom:0;left:0;right:0;z-index:50;background:var(--rsl-dock-bg);border-top:1px solid var(--rsl-border);padding:.35rem .25rem calc(.35rem + env(safe-area-inset-bottom))}}
@media(min-width:1024px){.rsl-dock,.rsl-dock.rsl-only-mobile,.rsl-only-mobile.rsl-dock,nav.rsl-dock{display:none!important}}
.rsl-dock-link{flex:1;display:flex;flex-direction:column;align-items:center;gap:.15rem;font-size:.625rem;font-weight:700;color:var(--rsl-text-muted);text-decoration:none}
.rsl-mobile-nav[hidden]{display:none!important}
.rsl-appbar-start.rsl-only-mobile{display:flex!important;align-items:center;gap:.5rem;min-width:0;flex:1}
.rsl-appbar-start{display:flex;align-items:center;gap:.5rem;min-width:0;flex:1}
.rsl-mobile-menu-btn{width:2.75rem;height:2.75rem;border:1px solid var(--rsl-border);border-radius:.5rem;background:var(--rsl-surface)}
.rsl-dock-link svg,.rsl-pro-nav-icon{width:1.25rem;height:1.25rem;max-width:1.25rem}
.rsl-dock-label{max-width:100%;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.rsl-pro-ring,.rsl-dash-ring{width:4.5rem;height:4.5rem;max-width:4.5rem;position:relative;flex-shrink:0}
.rsl-pro-ring-svg,.rsl-dash-ring-svg{width:100%!important;height:100%!important;max-width:4.5rem!important}
.rsl-panel,.rsl-card{background:var(--rsl-surface);border:1px solid var(--rsl-border);border-radius:.75rem}
.rsl-tool-grid{display:grid;gap:.75rem;grid-template-columns:repeat(2,minmax(0,1fr))}
@media(min-width:1024px){.rsl-tool-grid{grid-template-columns:repeat(4,minmax(0,1fr))}}
.rsl-tool-card{display:flex;flex-direction:column;padding:1rem;border-radius:.75rem;background:var(--rsl-surface);border:1px solid var(--rsl-border);text-decoration:none;color:inherit;box-shadow:0 1px 2px rgba(15,23,42,.06)}
.rsl-tool-label{font-size:.875rem;font-weight:700;color:var(--rsl-text)}
.rsl-tool-desc{font-size:.75rem;color:var(--rsl-text-muted)}
.rsl-btn{display:inline-flex;align-items:center;justify-content:center;padding:.55rem 1rem;border-radius:.5rem;border:none;background:var(--rsl-teal-600);color:#fff;font-weight:700;font-size:.875rem;cursor:pointer;text-decoration:none}
.rsl-only-desktop{display:none!important}
@media(max-width:1023.98px){.rsl-only-mobile{display:block!important}}
@media(min-width:1024px){.rsl-only-desktop{display:block!important}.rsl-only-mobile,.rsl-only-mobile.rsl-dock,.rsl-dock,nav.rsl-dock{display:none!important}}
body.rsl-login-page{min-height:100vh;background:#0f172a}
.rsl-login-shell{position:relative;z-index:1;min-height:100vh;display:flex;flex-direction:column}
@media(min-width:1024px){.rsl-login-shell{flex-direction:row}}
.rsl-login-brand-panel{display:none}
@media(min-width:1024px){.rsl-login-brand-panel{display:flex;flex:0 0 45%;max-width:45%}}
.rsl-login-main{display:flex!important;flex-direction:column!important;align-items:center;justify-content:center;flex:1;width:100%;min-width:0;gap:1rem;padding:1.5rem 1.25rem}
.rsl-login-glass,.rsl-login-card{width:100%;max-width:420px}
.rsl-login-form{display:flex;flex-direction:column;gap:1rem}
.rsl-login-input,.rsl-login-submit{width:100%}
</style>
