# Mobile APK downloads

APK files live on **this server** — not in git. After GitHub push, CI builds for your `APP_URL` and uploads here.

| App | Download URL |
|-----|--------------|
| Radiant ISP | `https://YOUR-DOMAIN/downloads/isp-radiant.apk` |
| MFS Verify | `https://YOUR-DOMAIN/downloads/isp-mfs-verify.apk` |

Landing page, portal, and admin use these links automatically when files exist in `public/downloads/`.

## Automatic (recommended) — GitHub push → build → this server

**Flow:** `git push main` → workflow **Mobile APKs** → build with `APP_URL` → SCP to `public/downloads/` → website serves `${APP_URL}/downloads/*.apk`

### One-time GitHub setup

Repo → **Settings → Secrets and variables → Actions**:

| Name | Type | Example (anetbd.com) |
|------|------|---------------------|
| `APP_URL` | **Variable** | `https://anetbd.com` |
| `DEPLOY_PATH` | **Variable** | `/var/www/html` (NextDeploy) or `/var/www/isp-platform` |
| `DEPLOY_SSH_HOST` | **Secret** | `204.136.10.31` |
| `DEPLOY_SSH_USER` | **Secret** | `root` |
| `DEPLOY_SSH_KEY` | **Secret** | SSH private key (full PEM) |

### Server `.env`

```env
APP_URL=https://anetbd.com
MOBILE_USE_GITHUB_RELEASES=false
MOBILE_CI_DEPLOY=true
```

`MOBILE_CI_DEPLOY=true` stops the server from overwriting CI APKs with old GitHub Release sync.

Manual run: **Actions → Mobile APKs → Run workflow**.

## Manual build on server (needs Flutter)

```bash
./scripts/deploy-mobile-apks.sh https://anetbd.com
```

## GitHub Releases (optional backup only)

Not used for website links when `MOBILE_USE_GITHUB_RELEASES=false` and APK exists in `public/downloads/`.
