# Mobile APK — automatic (zero manual setup)

Server reads **`APP_URL` from `.env`** and builds APKs automatically. You do not configure GitHub secrets or mobile env vars.

| When | What happens |
|------|----------------|
| Install wizard finishes | Background build for your domain |
| `git pull` / deploy | Rebuild if domain changed or APK missing |
| `APP_URL` changes in `.env` | Next deploy rebuilds for new domain |

Download links (same server):

- `https://YOUR-DOMAIN/downloads/isp-radiant.apk`
- `https://YOUR-DOMAIN/downloads/isp-mfs-verify.apk`

## Requirements (one of)

- **Flutter** on server, or
- **Docker** (NextDeploy/VPS) — uses `ghcr.io/cirruslabs/flutter:stable`

## Manual trigger

```bash
bash scripts/auto-mobile-after-deploy.sh
# or
php artisan isp:rebuild-mobile-apks
```

Logs: `storage/logs/auto-mobile-deploy.log`

## `.env` (auto-set by installer)

```env
APP_URL=https://your-new-domain.com
MOBILE_USE_GITHUB_RELEASES=false
```

No `MOBILE_APK_URL`, no GitHub Actions variables required.
