# Code Pagol on ispbillling

**Primary Git repo:** https://github.com/habib2500a1/ispbillling

| Branch | Purpose |
|--------|---------|
| `main` | Production anetbd / ispbillling platform — **use this for all new work & deploy** |
| `codepagol/main` | Archived Code Pagol / MikroTik-Billing snapshot (Jul 2026). Do not deploy. |

- Live: https://bill.flixbd.xyz
- NextDeploy app: `isptest-58fb` → `habib2500a1/ispbillling` branch **`main`**
- Port anetbd features into `main`; keep Bootstrap/Livewire UI only where needed as custom views on top of ispbillling.

```bash
git clone https://github.com/habib2500a1/ispbillling.git
cd ispbillling
git checkout main
# work, commit, push
git push origin main
```
