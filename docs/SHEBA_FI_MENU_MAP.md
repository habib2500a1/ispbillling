# Sheba-Fi → ISP Platform — অপারেটর মেনু ম্যাপ

এই ডকুমেন্ট [demo.shebafi.com](https://demo.shebafi.com/) (Sheba-Fi PHP ডেমো) এর মেনু/ট্যাবকে আমাদের Filament admin (`/admin`) এর সমতুল্য পেজের সাথে মিলিয়ে দেয়। স্টাফ ট্রেনিং ও parity যাচাইয়ের জন্য ব্যবহার করুন।

> **নোট:** Sheba-Fi ডেমো ASP.NET legacy portal (`pay.anetbd.com`) নয়। `LEGACY_PORTAL_URL` এ Sheba-Fi URL দেবেন না।

## দ্রুত লিংক (সবচেয়ে বেশি ব্যবহৃত)

| Sheba-Fi | আমাদের প্ল্যাটফর্ম |
|----------|-------------------|
| Active Clients | `/admin/subscribers` |
| Due Clients / Collect Due | `/admin/bill-collection-desk` |
| Online Monitoring | `/admin/online-clients-monitoring` |
| Packages | `/admin/packages` |
| Routers | `/admin/mikrotik-servers` |
| OLT | `/admin/olt-hub` |
| POP/Branch (Agents) | `/admin/resellers-hub` |
| Tickets | `/admin/support-tickets` |
| Quick Pay (no login) | `/pay` |
| সব মডিউল ইনডেক্স | `/admin/operations-hub` |

---

## Overview ও কনফিগ

| Sheba-Fi tab | কাজ | Filament URL / পেজ |
|--------------|-----|-------------------|
| `dashboard` | KPI, মাসিক বিক্রয় | `/admin` (Dashboard), `/admin/billing-overview` |
| `configuration` | Zone / Area / Box | `/admin/zones`, `/admin/areas` |
| `services` | প্যাকেজ | `/admin/packages` |
| `offers` | Offer & Promotion | `/admin/promotional-offers` |
| `settings` | Gateway, SMS, API | `/admin/settings-hub`, `/admin/api-configuration`, `/admin/app-settings` |
| Quick Pay | গেস্ট বিল পে | `/pay` |

## Clients (গ্রাহক)

| Sheba-Fi tab | কাজ | Filament |
|--------------|-----|----------|
| `add_client` | নতুন গ্রাহক | `/admin/subscribers/create` |
| `clients` | Active তালিকা | `/admin/subscribers` |
| `free_clients` | Free | `/admin/subscribers` → Subscriber lists → Free |
| `due_clients` | Due | `/admin/subscribers` (Due filter) বা Bill collection desk |
| `inactive` | Inactive | Subscriber lists / status filter |
| `due` (Expire) | মেয়াদ শেষ | `/admin/subscriber-lists-hub` → Expired |
| `left_list` | Left | Subscriber lists → Left |
| Import Clients | CSV / legacy import | `/admin/import-clients-csv` + `isp:import-legacy-portal*` |
| Import (Sheba-Fi JSON) | Manual JSON export | `php artisan isp:import-sheba-fi-json` — [`SHEBA_FI_DATA_IMPORT.md`](SHEBA_FI_DATA_IMPORT.md) |

**গ্রিড অ্যাকশন (Sheba-Fi: Move, Recharge, Retest):** `/admin/subscribers` তালিকায় সারি-লেভেল বাটন — Move (reseller), Recharge (wallet), Retest (ONU sync)।

## Network

| Sheba-Fi tab | Filament |
|--------------|----------|
| `online_clients` | `/admin/online-clients-monitoring` |
| `usage_dashboard` | `/admin/bandwidth-monitor` |
| `usage_reports` | Reports hub + bandwidth |
| `routers` | `/admin/mikrotik-servers` |
| `olt` | `/admin/olt-hub`, `/admin/optical-monitoring-hub` |

## Billing ও Finance

| Sheba-Fi tab | Filament |
|--------------|----------|
| `finance` | `/admin/accounts-wallet-hub` |
| `monthly_sales` | `/admin/billing-reports`, `/admin/financial-reports` |
| `reports` | `/admin/reports-hub`, `/admin/payments-report` |
| `accounts` | Wallet / cashbook (Accounts hub) |

## Support ও Call Center

| Sheba-Fi tab | Filament |
|--------------|----------|
| `tickets` | `/admin/support-tickets` |
| `call_center_dashboard` | `/admin/call-center-hub` |
| `ip_phone_config` | `/admin/manage-call-center-settings` |
| `call_logs` | `/admin/call-logs` |
| `follow_ups` | Call follow-ups (`/admin/call-follow-ups`) |
| `call_reports` | `/admin/call-center-reports` |
| `voice_templates` | `/admin/voice-templates` |
| `voice_sms` | Voice SMS campaigns (`/admin/voice-sms-campaigns`) |

## Store / Inventory

| Sheba-Fi tab | Filament |
|--------------|----------|
| `store_inventory` | `/admin/inventory-hub`, `/admin/products` |
| `store_sales` | `/admin/inventory-sales` |
| `store_support` | `/admin/store-device-loans` |
| `store_reports` | Inventory hub reports |

## HR

| Sheba-Fi tab | Filament |
|--------------|----------|
| `hr_dashboard` … `hr_reports` | `/admin/hr-payroll-hub` |
| `office_staff` | `/admin/staff-control-hub` |

## Resellers

| Sheba-Fi tab | Filament |
|--------------|----------|
| `agents` | `/admin/resellers-hub` |
| `manage_agents` | Reseller staff (reseller portal / admin) |
| `left_resellers` | Resellers (inactive/archived) |

## Logs

| Sheba-Fi tab | Filament |
|--------------|----------|
| `activity` | `/admin/activity-logs` |
| `error_logs` | `/admin/system-error-logs` |
| `sms_logs` | SMS delivery reports |

---

## আমাদের অতিরিক্ত শক্তি (Sheba-Fi ডেমোতে নেই)

- Multi-tenant, RBAC, 2FA
- GL accounting (`/admin/accounting-hub`)
- GPON NOC, fiber plant, NetFlow, RADIUS
- Reseller enterprise API (`/reseller`)
- Customer portal OTP, ONU live, AI
- Mobile API (`/api/v1`)
- WhatsApp bot, BTRC DIS, collector GPS

বিস্তারিত অপারেশন: [`BANGLA_OPERATOR_GUIDE.md`](BANGLA_OPERATOR_GUIDE.md)  
কল সেন্টার আর্কিটেকচার: [`CALL_CENTER_ARCHITECTURE.md`](CALL_CENTER_ARCHITECTURE.md)
