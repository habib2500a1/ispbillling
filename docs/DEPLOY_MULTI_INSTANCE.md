# Multi-instance deploy (optional — many clones on one server)

> **Default (easy):** one server, one `.env`, one domain — use `bash scripts/deploy-from-env.sh` or `install-git-deploy-hook.sh`. No `deploy/instances.json` required.

Each client domain gets **its own app directory + `.env` + database**.  
One `git push` on the server can deploy **all instances** without mixing user data.

## Quick setup (advanced only)

```bash
# 1) Copy config and add your 10 domains
cp deploy/instances.example.json deploy/instances.json
nano deploy/instances.json

# 2) Provision a new client (first time only)
bash scripts/provision-instance.sh \
  --id=radiant \
  --url=https://bill.flixbd.xyz \
  --path=/var/www/instances/bill.flixbd.xyz \
  --db=isp_radiant

# Edit DB password in that .env, then:
cd /var/www/instances/bill.flixbd.xyz
php artisan migrate --force
php artisan isp:post-deploy

# 3) Auto-deploy all instances after git pull
bash scripts/install-multi-instance-deploy-hook.sh
git pull origin main
```

## `deploy/instances.json`

| Field | Meaning |
|-------|---------|
| `id` | Short name (logs) |
| `enabled` | `true` to include in deploy-all |
| `path` | Absolute path to that app's git clone |
| `app_url` | Canonical `APP_URL` |
| `landing_domain` | Host for tenant resolution |
| `previous_urls` | Old domains that must still open the app |
| `db_database` | Documentation only — set `DB_DATABASE` in that `.env` |

## Deploy commands

```bash
# All enabled instances
bash scripts/deploy-all-instances.sh

# One instance
php artisan isp:deploy-all-instances --id=radiant

# Sync URL only (keeps old domain working via APP_PREVIOUS_URLS)
bash scripts/sync-instance-url.sh --path=/var/www/instances/bill.flixbd.xyz \
  --url=https://bill.flixbd.xyz --remember-old
```

## Old domain still works after new domain

When you change `app_url`, deploy runs with `--remember-old`:

- Previous URL is stored in `.env` → `APP_PREVIOUS_URLS`
- Laravel accepts requests on **current + previous** hosts
- Links inside the app use the host you opened in the browser

## Architecture

```
/var/www/isp-platform/          ← main git repo (deploy scripts)
/var/www/instances/
  bill.flixbd.xyz/              ← clone #1 + .env + DB isp_radiant
  client2.com/                  ← clone #2 + .env + DB isp_client2
  ...
```

Each clone is a full Laravel app. **No shared customers/invoices between clones.**
