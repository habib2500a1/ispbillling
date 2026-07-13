# ISP Billing Admin System (bill.flixbd.xyz)

Single admin-operated ISP billing platform. **No separate “Code Pagol” product** — your site name, logo, and settings come from **Admin → Site Settings**.

| Item | Value |
|------|--------|
| Live | https://bill.flixbd.xyz |
| Git branch | `codepagol/main` (deploy branch name only) |
| Repo | https://github.com/habib2500a1/ispbillling |

## Admin fixes everything (no code)

| Task | Where |
|------|--------|
| Site name, logo, theme | **Admin Center → Site settings** (`/site-settings`) |
| Cron / auto billing / SMS jobs | **Automatic Processes** (`/automatic-processes`) |
| SMS templates | **SMS Setup** (`/sms-setup`) |
| Support tickets + staff assign | **Support Tickets** (`/support-tickets`) |
| All modules (NOC, billing, HR…) | **ISP Modules** (`/isp-os`) |
| After deploy: migrate + sync | **Admin Center → Run system sync** (`/admin-center`) |

Deploy container also runs `php artisan cpagol:post-deploy` on start (migrate, process seed, SMS catalog, cache).

## Git workflow (code never lost)

```bash
git checkout codepagol/main
git add .
git commit -m "describe change"
git push origin codepagol/main
```

## anetbd.com

`main` branch = anetbd multi-tenant SaaS — **do not merge into bill.flixbd deploy branch**.
