# Code Pagol (ISP-Mikrotik-Billing) — separate from anetbd

Same GitHub repo as anetbd, **different branch** — anetbd core is never modified on `main`.

| Branch | Project | Touch? | Deploy |
|--------|---------|--------|--------|
| `main` | **anetbd / ispbillling** (multi-tenant SaaS) | ❌ Do not change | Other sites / reference only |
| `codepagol/main` | **Code Pagol / ISP-Mikrotik-Billing** (bill.flixbd.xyz) | ✅ All new work here | `isptest-58fb` → bill.flixbd.xyz |

- Repo: https://github.com/habib2500a1/ispbillling
- Live: https://bill.flixbd.xyz
- anetbd logic **copy** করা হয় (portal login, customer desk, MikroTik import) — `main` branch **merge বা edit করা হয় না**

## Workflow

```bash
git clone https://github.com/habib2500a1/ispbillling.git
cd ispbillling
git checkout codepagol/main

# modify Code Pagol only — Bootstrap/Livewire UI, bill.flixbd config
git add .
git commit -m "your change"
git push origin codepagol/main
```

## Where files live

- **Root of `codepagol/main`** — full Code Pagol Laravel app (deploy target)
- **`ISP-Mikrotik-Billing/`** — partial snapshot / reference only (not deployed)
- **`/tmp/ispbillling` on `main`** — read-only reference for anetbd patterns
