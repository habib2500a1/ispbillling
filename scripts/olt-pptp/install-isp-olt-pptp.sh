#!/usr/bin/env bash
# Install PPTP client on bill server → route 103.29.127.0/24 (OLT LAN) via tunnel.
# Run on server: sudo bash scripts/olt-pptp/install-isp-olt-pptp.sh
set -euo pipefail

PPTP_SERVER="${PPTP_SERVER:-103.29.127.228}"
PPTP_USER="${PPTP_USER:-ispbill}"
PPTP_PASSWORD="${PPTP_PASSWORD:?Set PPTP_PASSWORD env (do not commit to git)}"
OLT_SUBNET="${OLT_SUBNET:-103.29.127.0/24}"

apt-get install -y pptp-linux

cat > /etc/ppp/peers/isp-olt <<EOF
pty "pptp ${PPTP_SERVER} --nolaunchpppd"
name ${PPTP_USER}
remotename isp-olt
require-mschap-v2
refuse-pap
refuse-chap
refuse-eap
noauth
persist
maxfail 0
holdoff 15
lock
nodeflate
nobsdcomp
novj
novjccomp
mtu 1400
mru 1400
EOF
chmod 600 /etc/ppp/peers/isp-olt

grep -q "\"${PPTP_USER}\" isp-olt" /etc/ppp/chap-secrets 2>/dev/null || \
  echo "\"${PPTP_USER}\" isp-olt \"${PPTP_PASSWORD}\" *" >> /etc/ppp/chap-secrets
chmod 600 /etc/ppp/chap-secrets

install -m 755 /dev/stdin /etc/ppp/ip-up.d/99-isp-olt-route <<SCRIPT
#!/bin/sh
ip route replace ${OLT_SUBNET} dev "\${PPP_IFACE}" scope link 2>/dev/null || true
logger -t isp-olt-pptp "UP \${PPP_IFACE} — route ${OLT_SUBNET}"
SCRIPT

install -m 755 /dev/stdin /etc/ppp/ip-down.d/99-isp-olt-route <<SCRIPT
#!/bin/sh
ip route del ${OLT_SUBNET} dev "\${PPP_IFACE}" 2>/dev/null || true
logger -t isp-olt-pptp "DOWN \${PPP_IFACE}"
SCRIPT

cat > /etc/systemd/system/isp-olt-pptp.service <<'UNIT'
[Unit]
Description=PPTP VPN to ISP OLT LAN
After=network-online.target
Wants=network-online.target

[Service]
Type=forking
ExecStart=/usr/bin/pon isp-olt updetach persist
ExecStop=/usr/bin/poff isp-olt
Restart=on-failure
RestartSec=20

[Install]
WantedBy=multi-user.target
UNIT

chmod +x "${APP_ROOT:-/var/www/isp-platform}/scripts/olt-pptp/isp-olt-pptp-ctl"
echo 'www-data ALL=(root) NOPASSWD: /var/www/isp-platform/scripts/olt-pptp/isp-olt-pptp-ctl *' > /etc/sudoers.d/isp-olt-pptp
chmod 440 /etc/sudoers.d/isp-olt-pptp
visudo -cf /etc/sudoers.d/isp-olt-pptp

systemctl daemon-reload
systemctl enable isp-olt-pptp.service 2>/dev/null || true
echo "Installed. Per-OLT PPTP: configure on Edit OLT → PPTP VPN section."
echo "MikroTik must allow GRE (proto 47) + TCP 1723 from this server."
