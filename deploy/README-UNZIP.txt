ISP Platform — cPanel / Webuzo ZIP Install
==========================================

Package structure (extract in /home/YOUR_CPANEL_USER/):

  isp-app/        ← Laravel application (NOT public web root)
  public_html/    ← Web files (point domain here OR copy into your domain public_html)

STEP 1 — Unzip
--------------
cPanel File Manager → Home directory (/home/user/) → Upload zip → Extract

Do NOT extract only inside public_html — you need BOTH folders at home level.

STEP 2 — Domain / Document root
--------------------------------
Point your domain document root to:

  /home/YOUR_USER/public_html

If your host already uses public_html for the main domain, copy the zip's
public_html/* files into your existing public_html folder.
Keep isp-app/ at /home/YOUR_USER/isp-app/

STEP 3 — Open browser (Setup Wizard)
-------------------------------------
Visit: https://your-domain.com/install

Wizard steps:
  1. Requirements check
  2. Permissions (auto-fix button)
  3. Database (MySQL from cPanel)
  4. Admin email + password

STEP 4 — Cron (required)
------------------------
cPanel → Cron Jobs → every minute:

  cd /home/YOUR_USER/isp-app && php artisan schedule:run >> /dev/null 2>&1
  cd /home/YOUR_USER/isp-app && php artisan queue:work database --stop-when-empty --max-time=55 >> storage/logs/queue.log 2>&1

GitHub: https://github.com/habib2500a1/ispbillling
Guide:  docs/INSTALL_CPANEL_WEBUZO.md
