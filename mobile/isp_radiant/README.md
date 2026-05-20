# RADIANT ISP Mobile App

Unified Android app for **Admin / Staff** and **Clients** — native API client for the Laravel ERP.

**Full enterprise architecture:** see [`docs/MOBILE_ARCHITECTURE.md`](../docs/MOBILE_ARCHITECTURE.md) (Customer, Collector, Technician, NOC, Admin phases).

## Features

- **Staff / Admin:** Dashboard with billing KPIs, tickets, tasks, zone chart, quick actions
- **Client:** Account summary, package, due/paid, live traffic, support shortcuts
- Secure token storage (Sanctum)
- Pro UI matching your demo (blue header, cards, charts)

## API endpoints used

| Role | Login | Dashboard |
|------|-------|-----------|
| Staff | `POST /api/v1/mobile/login` (`role: staff`, email + password) | `GET /api/v1/staff/dashboard` |
| Client | `POST /api/v1/mobile/login` (`role: customer`, phone/ID + password) | `GET /api/v1/customer/dashboard` |

- Token refresh: `POST /api/v1/auth/refresh` (staff), `POST /api/v1/customer/auth/refresh` (customer)

Legacy endpoints still work: `/api/v1/auth/login`, `/api/v1/customer/login`.

## Configure server URL

Edit `lib/config/app_config.dart` or build with:

```bash
flutter build apk --release --dart-define=API_BASE_URL=https://bill.flixbd.xyz/api/v1
```

## Build APK (on a machine with Android SDK)

```bash
cd mobile/isp_radiant
flutter pub get
flutter build apk --release --dart-define=API_BASE_URL=https://YOUR-DOMAIN/api/v1
```

Output: `build/app/outputs/flutter-apk/app-release.apk`

Or run the project script from repo root:

```bash
./scripts/build-mobile-apk.sh https://bill.flixbd.xyz
```

## Staff login

Use the same **email + password** as Filament admin (`/admin`).

## Client login

Use **customer code**, **phone**, or **PPP username** + **portal password** (same as customer portal).

## Requirements

- Flutter 3.16+
- Android SDK 34
- PHP API: Laravel Sanctum enabled, HTTPS recommended
