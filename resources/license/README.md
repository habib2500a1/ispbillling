# Platform license keys (sell / on-premise)

1. **Vendor only:** `php artisan isp:license:generate-keys`
   - Creates `public.pem` here (commit to repo) and `storage/license/private.pem` (never commit).

2. **Per customer:** `php artisan isp:issue-license customer.domain.com --expires=2027-12-31`

3. Customer `.env`:
   - `ISP_DEPLOYMENT_MODE=on_premise`
   - `ISP_LICENSE_ENFORCE=true`
   - `ISP_LICENSE_KEY=<signed key>`

**Rent (SaaS)** hosts use `ISP_DEPLOYMENT_MODE=saas` and `ISP_LICENSE_ENFORCE=false` — no key needed.
