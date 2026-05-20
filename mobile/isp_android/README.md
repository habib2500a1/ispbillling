# ISP Management — Native Android (Kotlin)

MVVM + Retrofit + Material 3 app for **Client** and **Admin** panels.

## Open in Android Studio

1. Open folder `mobile/isp_android`
2. Sync Gradle
3. Run on device/emulator (min SDK 24)

## API base URL

Default: `https://bill.flixbd.xyz/api/v1/`

Change in `app/build.gradle.kts` → `buildConfigField("API_BASE_URL", ...)`.

## Authentication

| Spec | Laravel |
|------|---------|
| `POST /api/v1/login` | `POST /api/v1/login` (unified — email = admin, phone/ID = client) |
| `user_type: client` | customer Sanctum token |
| `user_type: admin` | staff Sanctum token |

## Client panel mapping

| Spec | Laravel |
|------|---------|
| Dashboard | `GET customer/dashboard` |
| Payments / bills | `GET customer/bills` |
| Pay | `POST customer/bills/{id}/pay` |
| Packages | `GET customer/packages`, `POST customer/packages/change` |
| Password | `POST customer/profile/password` |
| Tickets | `GET/POST customer/tickets` |
| Usage chart | `GET customer/usage/live` |

## Admin panel mapping

| Spec | Laravel |
|------|---------|
| Dashboard + zone chart | `GET staff/dashboard` |
| Bill receive | `POST collector/collections` |
| Monitoring | `GET staff/online-clients` (30s refresh) |
| Client list / detail | `GET staff/customers`, `GET staff/customers/{id}` |
| Tickets | `GET staff/tickets` |
| Tasks | `GET staff/tasks` |
| Expense | `POST collector/expenses` |
| Collection wallet | `GET collector/wallet` |

Some spec routes (`admin/billing/approve`, `admin/clients` POST) are not on the API yet — use Filament admin for create/approve flows.

## Build APK (CLI)

```bash
cd mobile/isp_android
./gradlew assembleRelease
# APK: app/build/outputs/apk/release/app-release.apk
```

Requires Android SDK + JDK 17.
