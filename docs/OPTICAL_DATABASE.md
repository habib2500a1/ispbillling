# Optical monitoring database

All optical data is stored in the **main application database** (`DB_DATABASE` in `.env`, e.g. `isp_platform`). There is no separate optical database.

## Layer model

| Layer | Tables | Purpose |
|-------|--------|---------|
| **Live** | `devices`, `olt_ports` | Current RX/TX dBm on ONU rows; OLT health JSON |
| **History** | `onu_signal_logs`, `olt_health_logs`, `snmp_poll_logs` | Charts, trends, audit |
| **Analytics** | `onu_health_scores`, `pon_signal_stats`, `signal_predictions` | Health %, PON stats, AI risk |
| **Alerts** | `signal_alerts`, `fiber_fault_logs` | Weak signal, LOS, fiber cut |

## Key columns (live dBm)

```sql
SELECT serial_number, rx_power_dbm, tx_power_dbm, onu_oper_status, last_polled_at
FROM devices WHERE type = 'onu';
```

## CLI

```bash
php artisan isp:optical-db-status
php artisan isp:optical-db-status --tenant=1 --json
php artisan migrate --force
php artisan isp:prune-optical-database
```

## Retention (`.env`)

| Variable | Default |
|----------|---------|
| `OPTICAL_SNAPSHOT_RETENTION_DAYS` | 14 |
| `OPTICAL_HOURLY_RETENTION_DAYS` | 90 |
| `OPTICAL_OLT_HEALTH_RETENTION_DAYS` | 30 |
| `OPTICAL_RESOLVED_ALERT_RETENTION_DAYS` | 90 |

Config file: `config/optical_database.php`
