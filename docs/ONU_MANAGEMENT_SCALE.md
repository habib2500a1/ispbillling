# ONU Management at 500K+ Scale

Architecture and operational rules for large ISP deployments (500,000+ subscribers).

## Core principle: never auto-delete MAC on offline

Power loss, customer router off, fiber cut, and OLT reboot all report **offline**. Deleting inventory rows in those cases breaks billing, support context, and auto-link workflows.

### Offline handling (implemented)

When ONU goes offline:

| Action | Config key | Default |
|--------|------------|---------|
| Save last seen | `onu_management.offline_handling.save_last_seen` | `true` |
| Save offline since | `onu_management.offline_handling.save_offline_since` | `true` |
| Customer profile warning | `onu_management.offline_handling.customer_profile_warning` | `true` |
| Ticket suggest (24h+) | `onu_management.offline_handling.ticket_suggest_on_offline` | `true` |
| Delete on sync | `onu_management.offline_handling.delete_offline_on_sync` | **`false`** |
| Protect linked ONUs | `onu_management.offline_handling.protect_linked_onu_delete` | `true` |

Services:

- `OnuOfflineHandlingService` — last seen, offline since, customer `meta.onu_warning`
- `OnuMacArchiveService` — archive old MAC when ONU is replaced (never delete on offline)
- `OnuSmartAutomationService` — offline >24h + zero due → auto support ticket

### Recommended workflows

- **Unauthorized ONU detect** — sync marks `unauthorized` / `auth_fail`; customer warning at critical level
- **ONU replace** — MAC change triggers archive in `meta.mac_archive`
- **Manual purge** — only unlinked placeholder rows; requires `OPTICAL_DELETE_OFFLINE_ON_SYNC=true`

## Smart automation rules

| Condition | Action |
|-----------|--------|
| ONU offline > 24h AND customer due = 0 | Auto ticket (`equipment`) |
| RX < -28 dBm | Signal warning (`OnuSignalAlertService`) |
| RX < -30 dBm | Critical alarm + ticket (existing optical alerts) |

Config: `config/onu_management.php` + `config/optical.php` (`good_min=-28`, `weak_min=-30`).

Scheduled via `isp:collect-onu-signals` (runs `OnuSmartAutomationService` per tenant).

## Polling architecture (500K ONUs)

Do **not** poll all ONUs in one job. Partition work:

```
ONU Monitor Service 1  →  ~100k–150k ONUs
ONU Monitor Service 2  →  ~100k–150k ONUs
ONU Monitor Service 3  →  ...
ONU Monitor Service 4  →  ...
```

### Event-based monitoring (preferred at scale)

Reduce SNMP poll load with:

- SNMP traps (LOS, dying gasp, link up/down)
- Syslog from OLT
- OLT event push / northbound API

Poll remains as fallback for RX/TX and inventory sync.

## Data layers

| Layer | Technology | Purpose |
|-------|------------|---------|
| Live status cache | Redis | Subscriber page ONU status (<2s load) |
| Historical optics | PostgreSQL + partitioned `onu_signal_logs` | Sparklines, trends |
| Real-time NOC | WebSocket / Laravel Reverb | Live dashboard |
| Event queue | Kafka / RabbitMQ | Trap ingestion, automation workers |
| OLT integration | SNMP + vendor API | Sync, reboot, authorize |

## Subscriber page ONU section

Network tab shows **ONU operations** panel:

- Status (Online / Offline / LOS / Unauthorized)
- RX / TX power
- Last seen, uptime, reboot count
- Firmware, OLT, PON
- MAC archive on replace
- Profile-level warning banner when offline

Presenter: `SubscriberOnuOpsPresenter`.

## Bulk operations (OLT admin)

Available on OLT → ONUs relation manager:

- Bulk reboot (selected)
- Create tickets for weak signal
- Purge **unlinked** offline placeholders only (when explicitly enabled)

## Environment variables

```env
OPTICAL_DELETE_OFFLINE_ON_SYNC=false
OPTICAL_SMART_AUTOMATION=true
OPTICAL_OFFLINE_TICKET_HOURS=24
OPTICAL_RX_GOOD_MIN=-28
OPTICAL_RX_WEAK_MIN=-30
OPTICAL_MAC_ARCHIVE_ENABLED=true
```

## Deploy notes

After code deploy with PHP-FPM `opcache.validate_timestamps=Off`, reload FPM:

```bash
./scripts/reload-php-fpm.sh
```

Ensure `isp:collect-onu-signals` is scheduled (see `AutomaticProcessSeeder` → `collect-onu-signals`).
