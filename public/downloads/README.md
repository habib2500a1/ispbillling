# Mobile APK downloads

APK files are **not** stored in git (too large). After deploy they live on the **same server** as the website:

| App | Download URL |
|-----|--------------|
| Radiant ISP | `https://YOUR-DOMAIN/downloads/isp-radiant.apk` |
| MFS Verify | `https://YOUR-DOMAIN/downloads/isp-mfs-verify.apk` |

Landing page, customer portal, and admin panel pick these up automatically when files exist in `public/downloads/`.

## One-time setup (GitHub Actions — recommended)

GitHub repo → **Settings → Secrets and variables → Actions**:

| Name | Type | Example |
|------|------|---------|
| `APP_URL` | Variable | `https://billing.yourisp.com` |
| `DEPLOY_PATH` | Variable | `/var/www/isp-platform` |
| `DEPLOY_SSH_HOST` | Secret | server IP |
| `DEPLOY_SSH_USER` | Secret | `root` |
| `DEPLOY_SSH_KEY` | Secret | SSH private key |

When you push to `main` (and `mobile/**` changed), workflow **Mobile APKs** builds both apps for `APP_URL` and SCPs them to the server.

Manual run: Actions → **Mobile APKs** → Run workflow.

## Server .env (website links = server, not GitHub)

```env
APP_URL=https://billing.yourisp.com
MOBILE_USE_GITHUB_RELEASES=false
# Do not set MOBILE_APK_URL — local public/downloads/*.apk is used first
```

Apply server mode:

```bash
./scripts/use-server-mobile-downloads.sh --write-env
php artisan config:cache
```

## Manual build on server (needs Flutter)

```bash
./scripts/deploy-mobile-apks.sh https://billing.yourisp.com
```

Or after each deploy:

```env
MOBILE_BUILD_ON_DEPLOY=1
```

Then `./scripts/post-deploy.sh` builds and publishes APKs.

## GitHub Releases (optional fallback)

Only needed if APK is **not** on the server:

```bash
UPLOAD_GITHUB=1 ./scripts/build-mobile-apk.sh https://billing.yourisp.com
UPLOAD_GITHUB=1 ./scripts/build-mfs-verify-apk.sh https://billing.yourisp.com
```
