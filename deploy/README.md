# Docker / NextDeploy deploy notes

এই ফোল্ডার ISP Platform কে [NextDeploy](https://github.com/masudranaxpert/NextDeploy) panel এ চালানোর জন্য।  
Panel compose path: **`docker-compose.yml`** (repo root)।

---

## Panel — অবশ্যই ঠিক রাখুন

### Domains tab

| Field | Value | ভুল (কখনো নয়) |
|-------|-------|----------------|
| Service | **`nginx`** | `app` |
| Port | **`80`** (container port) | `8023` (host test port) |

`8023` শুধু ব্রাউজারে `http://SERVER_IP:8023` test এর জন্য — Domains tab এ দেবেন না।

### Environment (`.env`)

- `DB_HOST=postgres` (127.0.0.1 নয়)
- `POSTGRES_USER=isp` — existing volume এ superuser; `DB_USERNAME=isp_app` OK
- `DB_PASSWORD` = `POSTGRES_PASSWORD` (একই)
- `ISP_ADMIN_EMAIL` — **একবারই** (duplicate লাইন দেবেন না)
- `ISP_LANDING_DOMAIN=yourdomain.com` — Caddy compose label (optional backup)
- `APP_URL=https://yourdomain.com` — production domain

Template: [`deploy/.env.nextdeploy.example`](.env.nextdeploy.example)

### Compose ports

```yaml
nginx:
  ports:
    - "8023:80"   # ✅
    # - "80:80"   # ❌ host 80 = NextDeploy Caddy
```

---

## Auto bootstrap (container start)

`deploy/docker-entrypoint.sh` চালায়:

1. `composer install` (vendor incomplete হলে)
2. `ensure-db-user.sh` — background (`isp_app` on `isp` volume)
3. `bootstrap-app.sh` — background (`migrate` + `isp:bootstrap-admin`)
4. `php-fpm` — **তৎক্ষণাৎ** start (502 এড়াতে)

Admin login: `ISP_ADMIN_EMAIL` / `ISP_ADMIN_PASSWORD` (Terminal: `php artisan isp:bootstrap-admin`)

---

## Architecture

```
Browser → Cloudflare → Caddy (NextDeploy) → nginx:80 → app:9000 (php-fpm)
Direct test: http://SERVER_IP:8023 → nginx:80 → app:9000
```

`app` + `nginx` দুটোই **`NextDeploy`** + **`isp`** network এ — Caddy ও fastcgi দুটোই কাজ করে।

---

## সমস্যা দ্রুত চেক

| লক্ষণ | কারণ | Fix |
|--------|------|-----|
| 502 domain | Domains `app:8023` বা `app:80` | `nginx` + `80` → Redeploy |
| 502 after redeploy | nginx পুরনো app IP | Restart `app` তারপর `nginx` |
| DB `isp_app` auth fail | Volume `isp` দিয়ে তৈরি | `POSTGRES_USER=isp`, redeploy rebuild |
| Login fail | duplicate `ISP_ADMIN_EMAIL` | একটা email, `isp:bootstrap-admin` |
| IP login fail | `SESSION_DOMAIN=.domain` + HTTP IP | domain দিয়ে login করুন |
| `80:80` bind error | Caddy already uses host 80 | `8023:80` রাখুন |

বিস্তারিত: [`docs/DEPLOY_NEXTDEPLOY.md`](../docs/DEPLOY_NEXTDEPLOY.md)
