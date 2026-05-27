# ISP Platform SaaS

Production-oriented multi-tenant ISP billing, CRM, network monitoring, reseller management, customer portal, and mobile API platform.

## Overview

This project is a professional ISP operations platform built for selling to ISP companies as a SaaS product. It already includes tenant isolation, subscriber billing, MikroTik integration, reseller workflows, customer self-service, MFS payment collection, PDF invoices, mobile APIs, optical monitoring, and Docker-based deployment.

Current stack:

- Backend: Laravel 11, PHP 8.2+
- Admin UI: Filament 3
- Frontend assets: Tailwind CSS, Vite, Alpine.js
- Database: PostgreSQL-ready, SQLite/MySQL compatible
- Queue: Redis + Horizon recommended
- API auth: Sanctum bearer tokens for staff, reseller, and customer apps
- Deployment: Docker Compose, Nginx, PHP-FPM, Redis, PostgreSQL

## Core Modules

- Multi-tenant architecture with tenant-scoped data access
- ISP user and staff management with role/permission control
- Subscriber onboarding, package assignment, billing day, due tracking
- Monthly invoice generation, PDF invoices, collection desk, due reports
- Payment integrations for bKash, Nagad, Rocket, SSLCommerz, personal MFS flows
- MikroTik PPPoE provisioning, online/offline sync, suspend/reconnect flows
- Customer portal with bills, usage, tickets, payment, ONU status
- Reseller panel, reseller API, reseller settlement and commission workflows
- SMS, WhatsApp, Telegram notification channels
- Optical/OLT/ONU monitoring, signal analytics, webhook/SNMP ingestion
- Mobile API for staff, technicians, collectors, customers, and resellers
- White-label and branding support for company and reseller experiences
- Docker deployment with PostgreSQL and Redis

## Feature Status

Implemented or largely available:

- Multi tenant
- Admin dashboard
- Customer portal
- Reseller panel
- Role permission system
- Package subscription and billing automation
- PDF invoices
- Payment collection flows
- Mikrotik PPPoE integration
- Auto disconnect/reconnect workflows
- Real-time online monitoring
- White-label building blocks
- Multi-branch resources
- Mobile responsive admin and mobile APIs
- REST-style API surface
- PostgreSQL deployment path

Partially implemented / evolving:

- AI support assistant workflows
- deeper analytics polish
- fully standardized production docs
- broader API documentation
- optional JWT bridge for third-party API consumers

## Authentication

Current API authentication uses Laravel Sanctum personal access tokens:

- Staff API: `/api/v1/auth/login`
- Customer API: `/api/v1/customer/login`
- Reseller API: `/api/v1/reseller/login`
- Token refresh endpoints are available for mobile clients

Why this matters:

- secure bearer token auth is already implemented
- tenant-aware auth is already in place
- mobile app access is production-usable now
- if strict JWT compatibility is required later, it should be added as a dedicated API layer rather than replacing stable live flows abruptly

## Local Development

Requirements:

- PHP 8.2+
- Composer
- Node.js 20+
- PostgreSQL or SQLite
- Redis recommended

Quick start:

```bash
cp .env.example .env
composer install
npm install
php artisan key:generate
php artisan migrate
npm run build
php artisan serve
```

For concurrent local development:

```bash
composer run dev
```

## Docker Deployment

Production reference files live in `deploy/`.

Start stack:

```bash
docker compose -f deploy/docker-compose.yml up -d --build
```

Included services:

- `app` PHP-FPM
- `nginx`
- `postgres`
- `redis`
- `horizon`

Recommended production steps:

```bash
php artisan migrate --force
php artisan optimize
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

## Environment Setup

Important `.env` values:

- `APP_URL`
- `DB_CONNECTION`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`
- `QUEUE_CONNECTION=redis`
- `REDIS_HOST`
- `ISP_TENANT_BASE_DOMAIN`
- `ISP_SUPPORT_WEBHOOK_SECRET`
- `NETFLOW_WEBHOOK_SECRET`
- `OPTICAL_WEBHOOK_SECRET`
- `WHATSAPP_WEBHOOK_VERIFY_TOKEN`
- payment gateway credentials

Generate webhook/device secrets quickly:

```bash
php artisan isp:generate-webhook-secrets --write
php artisan config:clear
```

API reference:

- Public doc: `/docs/API_V1.md`
- Health index: `/api/v1/health`

## Production Audit

Run the built-in production readiness audit:

```bash
php artisan isp:production-audit --skip-tests
```

It checks:

- storage permissions
- debug mode
- app key presence
- essential env values
- HTTPS app URL in production
- queue connection suitability
- missing webhook secrets
- unused widget hints

## Scheduled Jobs

Examples of important automation commands already present:

- `php artisan isp:generate-bills`
- `php artisan isp:evaluate-service-expiry`
- `php artisan isp:mikrotik-poll-status`
- `php artisan isp:collect-bandwidth`
- `php artisan isp:process-netflow-inbox`
- `php artisan isp:collect-onu-signals`
- `php artisan isp:send-invoice-due-reminders`

Use Laravel scheduler and Redis/Horizon for production workloads.

## Key Areas In Code

- `app/Services/Clients` subscriber dashboard and operational summaries
- `app/Services/Bandwidth` PPP online sync and usage collection
- `app/Services/Payments` MFS and gateway payment orchestration
- `app/Services/Resellers` reseller workflows and settlement logic
- `app/Services/Optical` OLT, ONU, optical power, webhook, and AI-risk helpers
- `app/Http/Controllers/Api/V1` mobile and partner APIs
- `app/Filament` admin pages, resources, dashboards, and operational panels

## Recommended Production Stack

- Ubuntu or Debian server
- Nginx reverse proxy
- PHP-FPM 8.3
- PostgreSQL 16
- Redis 7
- Horizon workers
- HTTPS via Cloudflare or direct TLS
- cron entry for Laravel scheduler
- separate backup storage

## Current Hardening Improvements

Recent improvements include:

- tenant-safe online client count fixes
- mobile and desktop sidebar stability fixes
- cleaner production audit warnings
- webhook secret generation command
- improved `.env.example`
- project README upgraded from default Laravel text to SaaS-focused documentation

## Next Recommended Work

- add formal OpenAPI documentation
- add JWT compatibility layer if external integrators require JWT specifically
- refine AI support assistant flows
- continue UI/UX modernization for admin, portal, and reseller experiences
- add CI/CD pipeline for tests, linting, and deploy validation

## License

This repository follows the license defined by the project owner and deployment agreement.
