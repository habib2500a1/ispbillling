# Reseller Partner Mobile SDK

Generated from [`public/docs/reseller-openapi.yaml`](../../public/docs/reseller-openapi.yaml) (OpenAPI 3.0).

Interactive docs: `/docs/reseller-api.html`

## Regenerate

```bash
./scripts/generate-reseller-mobile-sdk.sh
```

Requires Node.js (npx) and Java 17+.

## Dart (Flutter)

Output: `dart/generated/`

```dart
import 'package:isp_reseller_api/api.dart';

final api = DefaultApi(ApiClient(
  basePath: 'https://bill.flixbd.xyz/api/v1',
  authentication: HttpBearerAuth()..accessToken = sanctumToken,
));

final dashboard = await api.resellerDashboardGet();
final staff = await api.resellerStaffGet();
```

Add to `pubspec.yaml`:

```yaml
dependencies:
  isp_reseller_api:
    path: ../reseller_sdk/dart/generated
```

Also depends on generated `pubspec.yaml` (http, intl, etc.).

### API key (read-only)

```dart
final api = DefaultApi(ApiClient(
  basePath: 'https://bill.flixbd.xyz/api/v1',
  authentication: HttpBearerAuth()..accessToken = 'rsk_...',
));
// GET only — POST returns 405
```

## Kotlin (Android / Retrofit2)

Output: `kotlin/generated/`

- Package: `com.ispplatform.reseller.api`
- Build: open `kotlin/generated` as Gradle module or copy `src/main/kotlin` into your app.

```kotlin
val token = "..." // Sanctum from POST /reseller/login
val api = AuthApi(/* configure Retrofit with Bearer interceptor */)
```

See `kotlin/generated/README.md` for Retrofit setup.

## Auth summary

| Flow | Header |
|------|--------|
| Mobile app (writes) | `Authorization: Bearer {sanctum}` from `POST /api/v1/reseller/login` |
| Integration (reads) | `Authorization: Bearer rsk_...` or `X-Reseller-Api-Key: rsk_...` |

## Spec coverage (v1.2.0)

- Auth, dashboard, customers CRUD, payments
- Staff CRUD + permission options
- Wallet recharge (manual + PipraPay)
- Settlements list + submit
- API keys, enterprise reads (sub-resellers, due-account, …)
