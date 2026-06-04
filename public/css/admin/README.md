# ISP Admin CSS — modular layout

সব admin CSS এখানে ছোট ফাইলে। **কোন বড় monolith এডিট করবেন না** — নিচের ফোল্ডার + PHP class ব্যবহার করুন।

## ফোল্ডার → PHP loader → কোথায় লোড

| ফোল্ডার | PHP class | কখন লোড হয় |
|---------|-----------|--------------|
| `admin/saas/` (11 files) | `AdminSaasStyles` | সব admin (`design-system.blade.php`) |
| `admin/clients-directory/` (5) | `ClientsDirectoryStyles` | `/admin/subscribers`, due, vip, … |
| `admin/subscriber-view/` (4) | `SubscriberViewStyles` | Subscriber 360 view + OLT CSS |
| `admin-responsive.css` | — | Mobile/tablet override (শেষে) |
| `admin-utilities.css` | — | Utilities |
| `*-hub-pro.css` (root) | `AdminRouteAssets` | শুধু সেই hub পেজ |

## কোন ফাইলে কী এডিট করবেন

| কাজ | ফাইল |
|-----|------|
| Sidebar, menu | `saas/02-sidebar.css` |
| Dashboard | `saas/03-dashboard-widgets.css` |
| Clients table, PPP, actions | `clients-directory/03-table.css` |
| Left/pending/expired table | `saas/07-tables-subscribers.css` |
| Due list theme | `clients-directory/04-due-page.css` |
| Subscriber 360 page | `subscriber-view/01-shell-hero.css` … `04-*.css` |
| Billing hub | `../billing-hub-pro.css` (এখনো এক ফাইল) |

## যাচাই (deploy前)

```bash
php artisan isp:verify-stylesheets
```

## Fast load (production)

`.env` এ:

```env
ISP_BUNDLE_CSS=true
APP_ENV=production
```

CSS মডিউল এডিটের পর bundle বানান:

```bash
php artisan isp:build-styles
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

**Bundle on:** 11+5+4 আলাদা request → **৩টা** bundle (`admin-saas.css`, `clients-directory-pro.css`, `subscriber-view-pro.css`).

**Bundle off (local dev):** ছোট মডিউল ফাইল সরাসরি লোড — এডিট করতে সুবিধা।

## Bundle rebuild

```bash
php artisan isp:build-styles
```

## এখনো এক ফাইল (ভাগ করা হয়নি)

| File | Lines | Note |
|------|-------|------|
| `portal.css` | ~3000 | Customer portal (আলাদা app) |
| `reseller-portal-pro.css` | ~2700+ | Reseller portal (pro) |
| `*-hub-pro.css` | 400–1400 | Hub প্রতি এক ফাইল — পরে `admin/hubs/` এ ভাগ করা যাবে |

## আর্কিটেকচার

```
StylesheetModules (shared html + version + SPA script)
    ├── AdminSaasStyles
    ├── ClientsDirectoryStyles
    └── SubscriberViewStyles

AdminStylesheetRegistry::missingModules()
AdminRouteAssets (route → hub CSS + directory + view)
```
