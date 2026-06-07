# cPanel / Webuzo release ZIPs

ZIP files are **not** in git (too large — vendor included). They are built automatically on GitHub.

## Download (ready-made)

**Latest release:**

https://github.com/habib2500a1/ispbillling/releases/latest

| Asset | Use |
|-------|-----|
| `isp-platform-cpanel-public_html.zip` | Unzip in `/home/user/` → `isp-app/` + `public_html/` |
| `isp-platform-cpanel-full.zip` | Unzip in `/home/user/` → document root = `isp-platform/public` |

After unzip: open `https://your-domain.com/install` (web wizard).

## Auto-build on GitHub

Workflow: [`.github/workflows/cpanel-release-zip.yml`](../.github/workflows/cpanel-release-zip.yml)

| Trigger | Release tag |
|---------|-------------|
| Push to `main` (app/code changes) | `cpanel-latest` (rolling) |
| Push tag `cpanel-v1.0.0` | versioned release |
| Manual: Actions → **cPanel Release ZIP** | custom tag |

## Build locally

```bash
bash scripts/build-cpanel-release-zip.sh
# → releases/isp-platform-cpanel-public_html.zip
# → releases/isp-platform-cpanel-full.zip
```

Upload to GitHub Releases manually:

```bash
gh auth login
./scripts/github-release-cpanel-zip.sh
# or versioned:
./scripts/github-release-cpanel-zip.sh cpanel-v1.0.0
```
