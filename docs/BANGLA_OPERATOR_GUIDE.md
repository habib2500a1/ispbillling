# ISP Platform — অপারেটর গাইড (বাংলা)

## ১. SMS (KhudeBarta)

1. **System → Notifications** → Channel settings  
2. Provider: **KhudeBarta**  
3. API URL: `http://portal.khudebarta.com:3775/sendtext`  
4. API key, Secret key, **Caller ID** (approved masking) দিন  
5. **Send test SMS** বাটন দিয়ে টেস্ট করুন  
6. KhudeBarta portal-এ **DLR URL** দিন (পেজে দেখানো URL):  
   `https://আপনার-ডোমেইন/webhooks/sms/khudebarta/dlr`  
7. **SMS delivery (DLR)** মেনুতে failed/delivered দেখুন  

## ২. বিলিং ও Dunning

- **Billing center** → Smart billing ops  
- **Dunning report** — কোন stage-এ কত invoice eligible, কত SMS গেছে (২৪ ঘণ্টা)  
- Automatic process: `isp:send-invoice-due-reminders` (মিনিট ক্রন)  
- `.env`: `BILLING_DUNNING_ENABLED=true`, `BILLING_DUNNING_PAYMENT_LINK=true`  

## ৩. পেমেন্ট গেটওয়ে

| গেটওয়ে | সেটিং |
|--------|--------|
| bKash | Payment gateway settings (super-admin) |
| Nagad / SSL | `.env` — `NAGAD_ENABLED`, `SSLCOMMERZ_ENABLED` |
| **Rocket** | Payment gateway settings → Rocket tab অথবা `ROCKET_ENABLED`, `ROCKET_MERCHANT_NUMBER` |

Rocket: গ্রাহক `/pay` থেকে Rocket নম্বরে টাকা পাঠিয়ে TrxID দেবে। Webhook: `POST /api/webhooks/payments/rocket`

- **Auto-approve**: Payment gateway → Rocket → *Auto-approve valid TrxID* অথবা `ROCKET_AUTO_VERIFY=true`
- **Pending queue**: Payments → *Pending gateway payments* (ম্যানুয়াল Approve/Reject)
- **Reconciliation**: Billing center → *Gateway reconciliation* (৭ দিনের মিল + duplicate TrxID)
- Pending হলে Telegram ops অ্যালার্ট (`NOTIFICATIONS_TELEGRAM_ENABLED=true`)

## ৪. ফিল্ড কালেকশন (GPS)

- **Bill collection desk** — ডেস্কে সংগ্রহ + GPS capture বাটন  
- **Collector mobile** — `/admin/collector-mobile` বা শর্টকাট `/collector`  
- **Collector visits map** — Billing center → রিপোর্ট, ম্যাপ + leaderboard  
- API: `POST /api/v1/collector/collections` (cashier লগইন, `collector` token)

## ৫. MikroTik সেশন integrity

- Dashboard widget: multi-router, wrong-router, overdue-still-online  
- **Suspend** / **Dismiss** বাটন উইজেটে  
- Cron: `isp:mikrotik-session-integrity` (automatic process)  
- `.env`: `NETWORK_SESSION_INTEGRITY_ENABLED=true`  
- অটো সাসপেন্ড (overdue online): `NETWORK_INTEGRITY_AUTO_SUSPEND_OVERDUE=true`  
- Auto-suspend grace: `NETWORK_AUTO_SUSPEND_GRACE_DAYS=3`

## ৬. অ্যাকাউন্টিং (GL)

- **Accounting hub** → **GL auto-post**  
- Payment auto-post (ডিফল্ট চালু)  
- Invoice auto-post (AR + revenue + VAT) চালু করুন প্রয়োজন হলে  

## ৭. MikroTik (দুই রাউটার)

- **MikroTik servers** — প্রতিটি রাউটার enable  
- Dashboard: **MikroTik fleet health** widget  
- Cron: `isp:mikrotik-poll-status`  
- Queue: `QUEUE_HEAVY_JOBS_ENABLED=true` + `php artisan horizon`  

## ৮. Outage SMS (এলাকা)

- **Support → Outages** — Area সিলেক্ট করে outage তৈরি  
- টেবিলে **SMS area** বাটন — ওই area-র active গ্রাহকদের SMS  

## ৯. Automatic process

- **System → Automatic process**  
- **Export run history (CSV)** — শেষ ৩০ দিনের run log  
- সার্ভার ক্রন: `* * * * * php artisan schedule:run`  

## ১০. Late fee

- Automatic process: `isp:apply-late-fees` (দৈনিক)  
- `.env`: `BILLING_LATE_FEES_ENABLED=true`  
- গ্রাহক প্রোফাইলে late_fee_fixed / late_fee_percent  

## ১১. রিসেলার কমিশন

- পেমেন্ট হলে commission accrue  
- Payout হলে SMS/email (phone/email রিসেলার প্রোফাইলে)  

## দ্রুত কমান্ড

```bash
php artisan isp:test-sms 017XXXXXXXX
php artisan isp:run-automatic-processes
php artisan migrate --force
php artisan config:clear
```
