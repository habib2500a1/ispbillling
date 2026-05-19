# Production deployment checklist

## Server

- [ ] PHP 8.2+, PostgreSQL, Redis
- [ ] `composer install --no-dev --optimize-autoloader`
- [ ] `php artisan migrate --force`
- [ ] `php artisan config:cache` (after `.env` final)
- [ ] `php artisan route:cache`
- [ ] Cron: `* * * * * cd /var/www/isp-platform && php artisan schedule:run >> storage/logs/scheduler.log 2>&1`
- [ ] Queue: `QUEUE_HEAVY_JOBS_ENABLED=true` + Horizon or `php artisan queue:work redis --sleep=3`
- [ ] Copy `deploy/laravel-horizon.service.example` → `/etc/systemd/system/laravel-horizon.service` and enable

## SMS (KhudeBarta)

- [ ] `NOTIFICATIONS_SMS_ENABLED=true`, API key, secret, approved Caller ID
- [ ] DLR URL in portal: `https://YOUR-DOMAIN/webhooks/sms/khudebarta/dlr`
- [ ] Test: `php artisan isp:test-sms 017XXXXXXXX`

## Payments

- [ ] bKash: Admin → Payment gateway settings
- [ ] Nagad / SSLCommerz / Rocket tabs filled
- [ ] `APP_URL` matches public HTTPS domain (callbacks)

## Accounting

- [ ] Accounting hub → GL auto-post (invoice + payment)
- [ ] Chart of accounts seeded for tenant

## MikroTik

- [ ] All routers enabled, poll automatic process on
- [ ] Dashboard: MikroTik fleet health widget

## Security

- [ ] `.env` not web-readable, `APP_DEBUG=false`
- [ ] `TRUSTED_PROXIES` set to load balancer / CDN IPs (not `*` in production)
- [ ] Webhook secrets set (`PAYMENT_WEBHOOK_SECRET`, `ROCKET_WEBHOOK_SECRET`, `ISP_SUPPORT_WEBHOOK_SECRET`, `NETFLOW_WEBHOOK_SECRET`, `OPTICAL_WEBHOOK_SECRET`)
- [ ] Security headers active (`SecurityHeaders` middleware on web + API)

## Network auto-suspend & session integrity

- [ ] `NETWORK_AUTO_SUSPEND_ENABLED=true` (if billing policy requires line-off on overdue)
- [ ] `NETWORK_AUTO_SUSPEND_GRACE_DAYS` / `NETWORK_AUTO_SUSPEND_MIN_BALANCE` tuned for your policy
- [ ] `NETWORK_SESSION_INTEGRITY_ENABLED=true` + scheduled `isp:mikrotik-session-integrity`
- [ ] `NETWORK_INTEGRITY_AUTO_SUSPEND_OVERDUE=false` unless ops approves auto kick on ghost sessions
- [ ] Rocket: `ROCKET_AUTO_VERIFY`, `ROCKET_VERIFY_URL` (optional bank API)
- [ ] Telegram: `NOTIFICATIONS_TELEGRAM_ENABLED=true` + bot token for ops alerts

## Post-deploy smoke

- [ ] Admin login, dashboard loads (⌘K → “Dashboard hub” for NOC/Billing/Support role boards)
- [ ] Dark / light theme toggle (topbar + mobile dock)
- [ ] Sidebar: Dashboard, Billing → Collection report, Payments → Reconciliation
- [ ] HR / Inventory: reach via ⌘K (“HR & payroll”, “Inventory hub”) — not duplicate sidebar children
- [ ] `/pay` test payment (sandbox)
- [ ] One SMS reminder dry-run: `php artisan isp:send-invoice-due-reminders --dry-run`
- [ ] `php artisan isp:production-audit`

## Automated audit

```bash
php artisan isp:production-audit
php artisan test
```

See `docs/PRODUCTION_AUDIT_PROMPT.md` for full agent checklist.  
See `docs/ENTERPRISE_AUDIT_REPORT.md` for architecture grade and roadmap.
