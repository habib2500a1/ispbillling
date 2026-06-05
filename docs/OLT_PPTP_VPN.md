# OLT reach via PPTP (bill server → POP LAN)

When the OLT (`103.29.127.94`) is only reachable on the POP LAN, connect the bill server with PPTP to the MikroTik at `103.29.127.228`.

## Panel (per OLT)

**OLT & Tools → Edit OLT** → section **PPTP VPN (private OLT IP)**:

- Enable **Use PPTP for this OLT**
- PPTP server `103.29.127.228`, user/password, subnet `103.29.127.0/24` (or leave blank for auto /24)
- **Save** → **Test PPTP** → **Test SNMP** / **Sync Aveis ONUs**

SNMP poll and ONU sync call PPTP automatically when direct ping to the OLT fails.

## Server (once)

```bash
sudo bash /var/www/isp-platform/scripts/olt-pptp/install-isp-olt-pptp.sh
# installs pptp-linux + sudo for www-data → isp-olt-pptp-ctl

php artisan isp:olt-pptp-status --olt-ip=103.29.127.94
```

## MikroTik (required)

Allow the bill server **`72.18.215.205`**:

```
/ip firewall filter
add chain=input action=accept protocol=gre src-address=72.18.215.205 comment="bill PPTP GRE"
add chain=input action=accept protocol=tcp dst-port=1723 src-address=72.18.215.205 comment="bill PPTP ctrl"
```

PPTP server: user `ispbill`, profile with local pool (e.g. `103.29.127.230-103.29.127.240`).

## If tunnel stays down (LCP timeout)

- TCP `1723` works but **GRE (IP protocol 47)** is blocked → fix MikroTik filter + upstream firewall.
- Some VPS hosts block GRE; ask host to allow GRE to this VM, or use **WireGuard** instead of PPTP.

## After VPN is up

```bash
ping 103.29.127.94
snmpget -v2c -c public 103.29.127.94:161 1.3.6.1.2.1.1.1.0
php artisan isp:sync-aveis-epon-onus --olt=364
```

Panel: **Test SNMP** → **Sync Aveis ONUs**.
