# cPanel / Webuzo release ZIPs

Build on a machine with PHP 8.2+ and Composer:

```bash
bash scripts/build-cpanel-release-zip.sh
```

Output:

| File | Use |
|------|-----|
| `isp-platform-cpanel-public_html.zip` | Unzip in `/home/user/` → `isp-app/` + `public_html/` |
| `isp-platform-cpanel-full.zip` | Unzip in `/home/user/` → `isp-platform/public` as docroot |

After unzip, open `https://your-domain.com/install` for the web setup wizard.

ZIP files are not committed to git (too large). Upload to GitHub Releases manually.
