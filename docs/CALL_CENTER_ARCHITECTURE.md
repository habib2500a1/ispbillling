# Call Center — Architecture & Integration

Sheba-Fi parity: browser WebSIP dialer, call logs, follow-up queue, voice templates, and optional auto voice SMS.

## Recommended deployment modes

| Mode | `CALL_CENTER_DRIVER` | When to use |
|------|----------------------|-------------|
| **Log only** (default) | `log_only` | Manual dial + staff logs calls in admin; no PBX required |
| **Webhook ingest** | `webhook` | Asterisk/FreePBX/3rd party POSTs CDR to `/api/webhooks/call-center` |
| **Future AMI** | `asterisk_ami` | Reserved — direct AMI listener (not implemented yet) |

## Data model

- `call_center_settings` — per-tenant SIP/WSS (for future WebRTC widget), extension defaults
- `call_logs` — direction, duration, status, recording URL, customer link
- `call_follow_ups` — scheduled callbacks (Sheba-Fi follow-ups tab)
- `voice_templates` — name, transcript, audio path/URL
- `voice_sms_campaigns` — scheduled voice blast queue (stubs until provider wired)

## WebSIP / JsSIP (browser phone)

1. Configure WSS URI, SIP domain, and staff extension in **Call center → SIP settings**.
2. Optional: embed JsSIP in Filament via `resources/views/filament/call-center/websip-widget.blade.php` (render hook).
3. Production: terminate WebRTC on your PBX (Asterisk PJSIP + valid TLS cert).

Secrets stay in DB / `.env` (`CALL_CENTER_WEBHOOK_SECRET`), never in client JS.

## Webhook ingest

```http
POST /api/webhooks/call-center
X-ISP-Webhook-Secret: <CALL_CENTER_WEBHOOK_SECRET>
Content-Type: application/json

{
  "direction": "outbound",
  "phone": "01712345678",
  "customer_id": 42,
  "duration_seconds": 120,
  "status": "answered",
  "recording_url": "https://pbx.example/rec/abc.mp3",
  "staff_extension": "101",
  "started_at": "2026-06-02T10:00:00+06:00"
}
```

## Ticket linkage

When `customer_id` is set, open calls can auto-create or append to a `call_center` channel support ticket (config: `call_center.auto_ticket_on_missed`).

## Security

- Webhook throttled (`throttle:webhooks`)
- Production: `CALL_CENTER_WEBHOOK_SECRET` required (see `isp:production-audit`)
- Staff pages gated by `StaffCapability` / support roles

## Roadmap

1. **Done:** Hub, settings, call logs CRUD, follow-ups, voice templates, webhook ingest
2. **Done:** JsSIP widget + click-to-dial (`CALL_CENTER_WEBSIP_ENABLED=true`, PortSIP profile in SIP settings)
3. **Done:** Call reports (`/admin/call-center-reports`) — staff totals from `call_logs`
4. **Done:** Voice SMS campaigns — SMS via `NotificationDispatcher`; voice via `VoiceCallGateway` (log driver until PBX wired)
5. **Later:** AMI live state, call recording playback proxy, dedicated voice-call gateway for production TTS/IVR
