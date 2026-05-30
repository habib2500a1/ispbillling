# RADIANT ISP — Device Test Checklist (post-modernization)

Install the latest APK: https://bill.flixbd.xyz/downloads/isp-radiant.apk
No API endpoints were changed — this verifies the new UI + clean-architecture wiring + dark/light.

## 0. Theme & global
- [ ] App opens in **dark** (premium `#0B1120` + purple) by default.
- [ ] Profile → **Appearance**: switch Light / Dark / Auto — whole app changes instantly.
- [ ] Kill & relaunch → chosen theme is **remembered**.
- [ ] Turn on airplane mode on a data screen → **offline banner / offline state** shows, no crash.
- [ ] Every list screen shows a **skeleton shimmer** while loading (not a bare spinner).
- [ ] Pull-to-refresh works on dashboards, lists, detail screens.

## 1. Auth & routing
- [ ] Login as **Client** → lands on customer dashboard.
- [ ] Login as **Admin/Staff** → lands on staff dashboard (correct mode).
- [ ] Logout returns to login; re-login works.

## 2. Client app
- [ ] **Dashboard**: name/code/status header, quick actions, monthly bill/paid/package/expire, live usage chart updates (~2s), notices.
- [ ] **Packages**: list loads; tap swap icon → change request confirm → success snackbar.
- [ ] **Pay dues**: total due card, wallet, due invoices; tap Pay → gateway picker → checkout opens.
- [ ] **Payment history**: paid bills list; tap a row → invoice detail.
- [ ] **Invoice detail**: Invoice Total + Recharge, Generation/Expire, Sub-Total/Previous Due/Total/Paid, Balance Due, customer box, Payments, Items, Note.
- [ ] **Tickets**: list loads; create ticket → appears; open thread → reply.
- [ ] **ONU**: status card + RX/TX; Request reboot → success.
- [ ] **Change password** works.

## 3. Admin — dashboard
- [ ] KPIs (today collection, cash, online, due, active, expire today).
- [ ] **Collection overview** card: % collected progress bar + Monthly/Collected/Due/Discount.
- [ ] Revenue 7-day chart, zone paid/unpaid chart, modules grid, tickets/tasks status.
- [ ] Offline payments banner + Sync (if collector mode) still works.

## 4. Admin — clients
- [ ] **Client List**: search (name/code/mobile/IP), filter chips (All/Active/Suspended/Due), "Showing N of total", load more.
- [ ] Client card: online dot, M.bill, status pill, **MikroTik toggle** flips line, call + SMS work.
- [ ] Tap card → **detail** (live usage LIVE badge, open invoices, Receive bill). Long-press → edit.
- [ ] Suspend / Reconnect (network control) from detail menu.

## 5. Admin — billing & collection
- [ ] **Billing list**: Paid/Unpaid/Received/Due stat cards.
- [ ] **Due** tab: due-client cards with red Due + amber **Pay** → Receive Bill flow; Ex.Date, toggle, call, SMS.
- [ ] **Invoices** tab: filter chips (all/due/open/paid/partial).
- [ ] **Collections** tab: Total Transaction + Collected stat + collection cards (Received/Created footer, print/call).
- [ ] **Receive Bill**: method pills, amounts, submit → success sheet; due updates.

## 6. Admin — modules
- [ ] Reports, Approvals, Tasks, Packages, Expense, Team discount, Inventory/POS, Monitoring, NOC, Comms, MFS SMS all open with the **purple gradient header** and skeleton load.
- [ ] **Team discount** bottom-sheet is dark (not white).
- [ ] **Comms**: send bulk due reminder + broadcast notice → success messages.
- [ ] No screen shows a stray white/light panel in dark mode.

## 7. Regression (logic unchanged)
- [ ] All amounts, dates, statuses match the web admin panel.
- [ ] Payments recorded in-app appear on the server.
- [ ] No feature removed vs the previous version.
