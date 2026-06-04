# Scheduler — সাইট দ্রুত রাখা (502 প্রতিরোধ)

## সমস্যা

`isp:run-automatic-processes` (MikroTik, OLT, বিলিং ইত্যাদি) যখন **একাধিক কপি** একসাথে চলে, PHP-FPM worker শেষ হয়ে **502 Bad Gateway** হয় — Cloudflare ঠিক থাকলেও সাইট লোড হয় না।

## সুরক্ষা (কোডে)

| স্তর | কাজ |
|------|-----|
| Schedule | `runInBackground()` **নেই** — mutex আগে ছেড়ে দিত |
| `withoutOverlapping(300)` | Laravel schedule mutex |
| `SchedulerRunnerLock` | একই সময়ে শুধু **১টি** runner |
| `isp:scheduler-guard` | প্রতি ৫ মিনিটে **পুরনো stuck** worker বন্ধ |

## `.env` (production)

```env
AUTOMATION_RUNNER_LOCK_SECONDS=300
AUTOMATION_MAX_RUNNER_PROCESSES=1
AUTOMATION_STALE_RUNNER_SECONDS=360
```

## চেক

```bash
pgrep -afc 'isp:run-automatic-processes'   # 0 বা 1 ভালো; 2+ খারাপ
php artisan isp:scheduler-guard --dry-run
bash scripts/health-check.sh
```

## জরুরি পরিষ্কার

```bash
php artisan isp:scheduler-guard
sudo systemctl reload php8.3-fpm
```

## ওয়েব লোড কমাতে

- Online clients: `BANDWIDTH_ONLINE_CLIENTS_COLLECT_ON_POLL=false` (ডিফল্ট)
- ভারী প্রসেস Admin → Automatic process থেকে interval বাড়ান (`every_five_minutes` ইত্যাদি)
- MikroTik collect Horizon queue-তে (`BANDWIDTH_QUEUE_SYNC_FROM_WEB=true`)
