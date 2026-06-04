# BDWebs / PortSIP — আমাদের প্ল্যাটফর্মে লাইভ কল

## একই লজিক (PortSIP = ব্রাউজার)

**Call center → SIP settings**-এ PortSIP অ্যাপে যা দেন, **ঠিক তাই** একবার দিন:

| PortSIP অ্যাপ | Admin SIP settings |
|---------------|-------------------|
| Server IP | SIP Server (IP) — `202.40.176.2` |
| Port UDP | SIP Port — `5060` |
| Domain | SIP Domain — `sip17.bdwebs.com` |
| Extension | Extension / Username |
| Password | Password |

ব্রাউজার ডায়ালার **একই ইউজার/পাস** দিয়ে WSS-এ রেজিস্টার করার চেষ্টা করে (IP+domain, PortSIP-এর মতো)।

## পার্থক্য (শুধু ট্রান্সপোর্ট)

| উপায় | ট্রান্সপোর্ট |
|--------|-------------|
| **PortSIP অ্যাপ** | UDP **5060** |
| **ব্রাউজার ডায়ালার** | **WSS** (অটো-ট্রাই `wss://sip-domain:7443/ws` …) |

ব্রাউজার UDP 5060 ব্যবহার করতে পারে না — BDWebs WSS না দিলে শুধু PortSIP অ্যাপ চলবে।

## Filament সেটিং

| ফিল্ড | উদাহরণ |
|-------|--------|
| SIP Server (IP) | `202.40.176.2` |
| SIP Port | `5060` |
| SIP Domain | `sip17.bdwebs.com` |
| Extension / Username | `09617179160` |
| Password | PortSIP-এর পাস |
| WSS URI | ঐচ্ছিক (খালি = অটো) |

### WSS URI ছাড়া (অটো)

`sip_domain = sip17.bdwebs.com` + WebSIP username/password (PortSIP-এর মতো) দিলেই সিস্টেম এইগুলো একে একে চেষ্টা করে:

- `wss://sip17.bdwebs.com:7443/ws`
- `wss://sip17.bdwebs.com:443/ws`
- `wss://sip17.bdwebs.com/ws`
- `wss://sip17.bdwebs.com:8089/ws`

সব ফেল করলে BDWebs-কে জিজ্ঞেস করুন সঠিক WSS URL — **PortSIP UDP তবু চলবে** (ব্রাউজার UDP ব্যবহার করতে পারে না)।

## `.env`

```env
CALL_CENTER_ENABLED=true
CALL_CENTER_WEBSIP_ENABLED=true
```

পাসওয়ার্ড `.env`-এ রাখবেন না (শুধু Filament SIP settings-এ এনক্রিপ্টেড থাকে)।

## কমান্ড দিয়ে সেট (ঐচ্ছিক)

`.env`-এ (পাসওয়ার্ড চ্যাটে শেয়ার করবেন না):

```env
BDWEBS_SIP_DOMAIN=sip17.bdwebs.com
BDWEBS_SIP_SERVER=202.40.176.2
BDWEBS_SIP_USERNAME=09617179160
BDWEBS_SIP_PASSWORD=your-secret
BDWEBS_WSS_URI=wss://sip17.bdwebs.com:7443/ws
```

তারপর:

```bash
php artisan isp:apply-bdwebs-sip --tenant=1
```

## লাইভ কল কখন হবে?

1. **WebSIP প্যানেল** (নিচে ডান) সবুজ **“Ready — live calls”** দেখালে — ক্লায়েন্ট লিস্টের ফোন আইকন দিয়ে ব্রাউজার থেকে কল যাবে।
2. **Register failed** থাকলে — WSS URL BDWebs থেকে নিন; অথবা PortSIP অ্যাপ দিয়ে কল করুন, আমাদের সিস্টেমে **Call log** ম্যানুয়াল/webhook দিয়ে রাখুন।

## নিরাপত্তা

SIP পাসওয়ার্ড চ্যাট/টিকেটে পাঠাবেন না। একবার লিক হলে BDWebs প্যানেল থেকে **পাসওয়ার্ড রিসেট** করুন।
