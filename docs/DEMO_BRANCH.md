# Demo branch (`demo`) — `main` production-safe রাখুন

## কেন আলাদা branch?

| | `main` | `demo` |
|---|--------|--------|
| NextDeploy app | Production (`anetbd.com`) | Demo (`demo.anetbd.com`) |
| `deploy/production.url` | `https://anetbd.com` | `https://demo.anetbd.com` |
| Environment | `ISP_DEMO_MODE=false` | `ISP_DEMO_MODE=true` + আলাদা DB |
| Real customer data | আছে | নেই |
| GitHub Actions APK | চালু (`main` push) | চালে না (শুধু `main`) |

**একই codebase** — demo-specific behaviour শুধু `.env` + `ISP_DEMO_MODE` দিয়ে।  
Branch আলাদা রাখলে production app ভুল branch pull করে demo config পায় না।

---

## একবার সেটআপ

### ১) `main` এ demo code (safe)

Demo code (`DemoSetupCommand`, banner, `ISP_DEMO_MODE` guard) `main` এ থাকতে পারে — production এ `ISP_DEMO_MODE=false` থাকলে কিছু হয় না।

```bash
git checkout main
git pull origin main
# changes commit + push (যদি এখনো না হয়ে থাকে)
git push origin main
```

### ২) `demo` branch তৈরি

```bash
git checkout -b demo
# শুধু এই ফাইল demo branch এ আলাদা
echo 'https://demo.anetbd.com' > deploy/production.url
git add deploy/production.url
git commit -m "demo branch: point production.url at demo.anetbd.com"
git push -u origin demo
```

### ৩) NextDeploy panel

| App | Branch | Domain | Environment |
|-----|--------|--------|-------------|
| **Production** | `main` | `anetbd.com` | `deploy/.env.nextdeploy.example` |
| **Demo** | `demo` | `demo.anetbd.com` | `deploy/.env.demo.example` |

Demo app → Settings → **Branch: `demo`** → Save → Redeploy.

---

## নিয়মিত আপডেট (main → demo)

নতুন feature `main` এ merge হলে demo তে আনুন:

```bash
bash scripts/sync-demo-branch-from-main.sh
```

অথবা ম্যানুয়াল:

```bash
git checkout demo
git pull origin demo
git merge main
# conflict হলে deploy/production.url → demo.anetbd.com রাখুন
git push origin demo
```

Demo app এ **Redeploy** — নতুন code চলবে, fake data থাকবে (আলাদা DB)।

---

## কোন ফাইল branch এ আলাদা?

শুধু:

```
deploy/production.url   → https://demo.anetbd.com (demo branch)
                        → https://anetbd.com     (main branch)
```

বাকি সব (কোড, `deploy/.env.demo.example`, scripts) **দুই branch এ same** — runtime আলাদা হয় NextDeploy Environment + database দিয়ে।

---

## ভুল এড়ান

| ভুল | ফলাফল |
|-----|--------|
| Demo app → branch `main` | `production.url` anetbd.com — confuse হতে পারে |
| Production app → branch `demo` | ভুল APK URL / deploy hint |
| Demo + production একই DB | Real customer demo তে দেখা যাবে |
| `demo` branch এ real `.env` commit | Secret leak — কখনো করবেন না |

---

## সংক্ষিপ্ত

```
main  → anetbd.com  → real data → NextDeploy production app
demo  → demo.anetbd.com → fake DEMO-* → NextDeploy demo app (আলাদা DB)
```

বিস্তারিত panel: [DEMO_ANETBD.md](./DEMO_ANETBD.md)
