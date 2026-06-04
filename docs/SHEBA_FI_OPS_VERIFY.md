# Sheba-Fi parity — ops verification log

Run on staging after deploy (`2026-06-02`).

| # | Check | Result |
|---|--------|--------|
| 1 | Scheduler | `php artisan schedule:list` — `isp:run-automatic-processes`, `mfs:match-pending-payments` registered |
| 2 | Migration | `2026_06_23_100000_sheba_fi_parity_features` applied |
| 3 | Routes | `/admin/call-center-hub`, `/admin/promotional-offers`, `/admin/store-device-loans`, `/admin/system-error-logs` |
| 4 | Webhook | `POST /api/webhooks/call-center` + `X-ISP-Webhook-Secret` |
| 5 | Tests | `./vendor/bin/phpunit tests/Feature/ShebaFiParityTest.php` — OK |
| 6 | Call reports | `/admin/call-center-reports` — staff summary |
| 7 | Sheba-Fi JSON import | `php artisan isp:import-sheba-fi-json` — see `docs/SHEBA_FI_DATA_IMPORT.md` |

Manual UI checks (staff):

- Subscribers list → Move / Recharge / Retest icon actions (all list presets: Free, Due, Expired, Left)
- Billing sidebar → Offers & promotions
- Support sidebar → Call center, Call logs, Follow-ups, Call reports, SIP settings
- Inventory → Support device loans
- System → Error logs
- Subscriber view → লাইভ কল opens WebSIP dialer
