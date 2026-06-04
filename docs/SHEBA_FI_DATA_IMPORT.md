# Sheba-Fi data import (JSON)

Live scraping of [demo.shebafi.com](https://demo.shebafi.com/) is **not supported** (PHP session app, not our ASP.NET legacy API). Use a manual JSON export instead.

## Command

```bash
php artisan isp:import-sheba-fi-json /path/to/export.json --tenant=1
php artisan isp:import-sheba-fi-json /path/to/export.json --dry-run
```

## JSON format

```json
{
  "customers": [
    {
      "customer_code": "1001",
      "name": "Rahim Uddin",
      "phone": "01712345678",
      "package_name": "20 Mbps",
      "status": "active"
    }
  ]
}
```

- Matches existing rows by `customer_code` or last 10 digits of `phone`.
- `package_name` must match a package name in the tenant (optional).
- `import_source` is set to `sheba_fi_json` on new rows.

## Notes

- For **pay.anetbd.com** / ISP Digital portals, continue using `LEGACY_PORTAL_*` sync commands.
- Review imported subscribers before enabling auto-billing.
