# Legacy Portal Cutover — pay.anetbd.com সরানো

প্রজেক্ট শেষে যখন **native billing/collection** একমাত্র source of truth হয়, তখন `pay.anetbd.com` (legacy portal) থেকে sync/import করার সব কোড **এক কমান্ডে** সরানো যায়।

**Command:** `php artisan isp:remove-legacy-portal`  
**Manifest:** `app/Support/LegacyPortalRemovalManifest.php`  
**Shell wrapper:** `scripts/remove-legacy-portal.sh`

---

## কখন চালাবেন (cutover checklist)

নিচের সব项 ঠিক আছে কিনা নিশ্চিত করুন — তারপর `--force` চালান।

| # | চেক |
|---|------|
| 1 | সব billing, invoice, collection **native admin / mobile / desk** দিয়ে চলছে |
| 2 | Staff performance, today collection, dashboard KPI **legacy sync ছাড়াই** সঠিক |
| 3 | `pay.anetbd.com` বন্ধ বা redirect করা হয়েছে (staff আর সেখানে collect করছে না) |
| 4 | শেষবার full backup নেওয়া (DB + `.env`) |
| 5 | Staging-এ `--force` dry-run নয় — একবার test করে নেওয়া (optional কিন্তু recommended) |
| 6 | Git working tree clean — cutover পর commit করার plan আছে |

---

## কমান্ড

### Preview (কিছু delete হবে না)

```bash
php artisan isp:remove-legacy-portal
```

- কোন file delete হবে না
- `would delete:` তালিকা দেখাবে
- শেষে **mixed files** list দেখাবে — cutover পর manually review করতে হবে

### আসলে delete + patch

```bash
php artisan isp:remove-legacy-portal --force
```

Confirmation prompt আসে। Non-interactive (CI/deploy script):

```bash
php artisan isp:remove-legacy-portal --force --no-interaction
```

### Shell wrapper (একই কাজ)

```bash
./scripts/remove-legacy-portal.sh              # preview
./scripts/remove-legacy-portal.sh --force      # delete + patch
```

---

## Command কী করবে

| Step | কাজ |
|------|-----|
| **~68 file delete** | Sync commands, importers, `SessionClient`, mirror, probes, legacy tests |
| **Scheduler off** | `bootstrap/app.php` থেকে legacy cron jobs সরায় |
| **Config stub** | `config/legacy_portal.php` → সব disabled; `config/isp_digital.php` → same stub |
| **Stubs** | `LegacyPortalDashboardSummaryProvider`, `LegacyPortalPassword` (no-op) |
| **`.env.example`** | `LEGACY_PORTAL_*` ও related keys সরায় |
| **Mixed files list** | Terminal-এ print — manual cleanup/reminder |

### Scheduler entries যা সরানো হয়

- `isp:sync-legacy-portal-daily` (daily import)
- `isp:sync-legacy-portal-collections` (every N minutes)
- `isp:mirror-legacy-portal` (raw mirror)

---

## Optional flags

### `--include-data-helpers` (advanced)

পুরনো imported data পড়ার helper class-ও delete করে:

```bash
php artisan isp:remove-legacy-portal --force --include-data-helpers
```

**সতর্ক:** শুধু তখনই ব্যবহার করুন যখন কোডবেসে `import_source=legacy_portal` বা legacy meta logic আর লাগবে না বলে নিশ্চিত।

Delete হবে:

- `LegacyPortalSource`
- `LegacyPortalBillNotes`
- `LegacyPortalPackageSpeed`
- `BillingPortalLabel`
- `PaymentCollectionSource`

### `--drop-mirror-tables`

Mirror DB tables drop (confirmation prompt):

```bash
php artisan isp:remove-legacy-portal --force --drop-mirror-tables
```

Rollback migration: `2026_06_26_010000_create_legacy_portal_mirror_tables.php`

---

## Default-এ যা **রেখে** দেয়

Historical imported customer/payment data পড়ার জন্য helper-গুলো **রাখা হয়** (যতক্ষণ `--include-data-helpers` না দেন):

| File | কেন রাখা |
|------|----------|
| `LegacyPortalSource` | `import_source=legacy_portal` চেনা |
| `PaymentCollectionSource` | collection source attribution |
| `LegacyPortalPackageSpeed` | পুরনো package speed meta |
| `LegacyPortalBillNotes` | legacy bill notes |
| `BillingPortalLabel` | UI label helper |

**DB data delete হয় না** — শুধু sync/import **কোড** সরানো হয়।

---

## Cutover পর করণীয়

```bash
php artisan optimize:clear
php artisan config:cache
php artisan route:cache   # production
php artisan view:cache    # production
```

Production `.env` থেকে legacy keys সরান (command শুধু `.env.example` patch করে):

```
LEGACY_PORTAL_URL
LEGACY_PORTAL_USERNAME
LEGACY_PORTAL_PASSWORD
LEGACY_PORTAL_SYNC_PASSWORD
LEGACY_PORTAL_DAILY_SYNC_ENABLED
LEGACY_PORTAL_COLLECTIONS_SYNC_EVERY_MINUTES
... (full list: LegacyPortalRemovalManifest::envKeys())
```

Git commit:

```bash
git add -A
git status   # mixed files review
git commit -m "Remove legacy portal integration after cutover"
```

Verify:

```bash
php artisan list | grep legacy    # legacy commands থাকা উচিত নয় (--force পর)
grep -r "sync-legacy-portal" bootstrap/app.php   # scheduler comment only
```

---

## Mixed files — manually review

Command `--force` চালানোর পরও এই file-গুলোতে legacy reference থাকতে পারে। Manifest থেকে তালিকা:

| File | সাধারণত কী করতে হবে |
|------|---------------------|
| `bootstrap/app.php` | Scheduler comment verify |
| `app/Services/Mobile/StaffBillingKpiResolver.php` | Legacy KPI branch সরান |
| `app/Services/Dashboard/BillingDashboardMetricsService.php` | Remote refresh / sync hint সরান |
| `app/Filament/Widgets/TodaySnapshotWidget.php` | "Sync from portal" UI সরান |
| `app/Filament/Pages/StaffPerformanceReport.php` | Legacy sync action সরান |
| `app/Services/Billing/CollectionDeskReportService.php` | Portal source toggle সরান |
| `app/Services/Billing/PackagePriceResolver.php` | Legacy price fallback review |
| `app/Services/Billing/BillCollectionSearchService.php` | Portal search path review |
| `app/Services/Billing/InvoiceGenerator.php` | Legacy align logic review |
| `app/Services/Network/NetworkAccessCoordinator.php` | Restore-from-portal path review |
| `app/Support/CustomerBalanceDue.php` | Legacy due snapshot review |
| `app/Support/CustomerAccountScopes.php` | Import source scope review |
| `database/seeders/AutomaticProcessSeeder.php` | Legacy automatic process slug review |
| `.env.example` | Already patched; re-check deploy templates |

Manifest update করতে: `app/Support/LegacyPortalRemovalManifest.php` → `mixedIntegrationFiles()`

---

## Delete হওয়া file-এর ধরন (manifest summary)

| Category | উদাহরণ |
|----------|---------|
| Artisan commands | `SyncLegacyPortalCollectionsCommand`, `ImportLegacyPortalFullCommand`, `MirrorLegacyPortalCommand`, … |
| Import services | `LegacyPortalSessionClient`, `LegacyPortalBillingImporter`, `LegacyPortalCollectionReconcileService`, … |
| Admin UI | `LegacyPortalSyncStatusPage` |
| Models | `LegacyPortalMirrorRecord`, `LegacyPortalMirrorRun` |
| Scripts | `scripts/probe_legacy_portal*.php`, `scripts/sync-all-legacy-data.sh` |
| Tests | `LegacyPortalPackageSpeedTest`, `LegacyPortalOnuAutoLinkTest` |

মোট **68** standalone file (`deletableFiles()`).

---

## Rollback

Command **git revert / restore** দিয়ে undo করা সহজ — cutover আগে commit/tag রাখুন:

```bash
git tag pre-legacy-cutover
php artisan isp:remove-legacy-portal --force
# সমস্যা হলে:
git checkout pre-legacy-cutover -- .
```

Production-এ `--force` চালানোর আগে branch/tag রাখা বteleportable best practice.

---

## Related docs

| Doc | বিষয় |
|-----|--------|
| [`SCHEDULER_OPS.md`](SCHEDULER_OPS.md) | Scheduler / 502 prevention |
| [`DEPLOY_NEXTDEPLOY.md`](DEPLOY_NEXTDEPLOY.md) | Production deploy |
| [`deploy/PRODUCTION_CHECKLIST.md`](../deploy/PRODUCTION_CHECKLIST.md) | Go-live checklist |

---

## Quick reference

```bash
# 1. Preview
php artisan isp:remove-legacy-portal

# 2. Cutover (after checklist)
php artisan isp:remove-legacy-portal --force

# 3. Post-cutover
php artisan optimize:clear && php artisan config:cache

# Optional: full cleanup
php artisan isp:remove-legacy-portal --force --include-data-helpers --drop-mirror-tables
```
