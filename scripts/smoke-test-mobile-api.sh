#!/usr/bin/env bash
# Smoke test mobile API endpoints (production or local BASE_URL).
set -euo pipefail
BASE="${1:-https://bill.flixbd.xyz/api/v1}"
EMAIL="${ISP_ADMIN_EMAIL:-admin@isp.local}"
PASS="${ISP_ADMIN_PASSWORD:-changeme123!}"

fail=0
ok=0

check() {
  local name="$1" expect="$2" code="$3" body="$4"
  if [[ "$code" == "$expect" ]] || [[ "$expect" == *"|"* && "$code" =~ ^($expect)$ ]]; then
    echo "OK   $name ($code)"
    ok=$((ok + 1))
  else
    echo "FAIL $name (expected $expect got $code) ${body:0:120}"
    fail=$((fail + 1))
  fi
}

echo "=== Public ==="
code=$(curl -s -o /tmp/smoke.json -w '%{http_code}' -H 'Accept: application/json' "$BASE/health")
check "health" "200" "$code" "$(cat /tmp/smoke.json)"

code=$(curl -s -o /tmp/smoke.json -w '%{http_code}' -H 'Accept: application/json' "$BASE/mobile/config")
check "mobile/config" "200" "$code" "$(cat /tmp/smoke.json)"

echo "=== Login ==="
code=$(curl -s -o /tmp/smoke.json -w '%{http_code}' -H 'Accept: application/json' -H 'Content-Type: application/json' \
  -X POST "$BASE/mobile/login" -d "{\"role\":\"staff\",\"login\":\"$EMAIL\",\"password\":\"$PASS\",\"device_name\":\"smoke\"}")
check "staff login" "200" "$code" "$(cat /tmp/smoke.json)"
TOKEN=$(python3 -c "import json; print(json.load(open('/tmp/smoke.json')).get('token',''))" 2>/dev/null || true)
if [[ -z "$TOKEN" ]]; then
  echo "ABORT: no token"
  exit 1
fi
AUTH="Authorization: Bearer $TOKEN"

endpoints=(
  "GET|/me|200"
  "GET|/staff/dashboard|200"
  "GET|/staff/billing/summary|200"
  "GET|/staff/billing/due|200"
  "GET|/staff/billing/invoices?status=all|200"
  "GET|/staff/billing/collections|200"
  "GET|/staff/customers/form-options|200"
  "GET|/staff/customers/search?q=admin|200"
  "GET|/staff/monitoring/online|200"
  "GET|/staff/tickets|200"
  "GET|/staff/tasks|200"
  "GET|/staff/approvals/pending|200"
  "GET|/staff/expense-categories|200"
  "GET|/staff/packages|200"
  "GET|/staff/payment-methods|200"
  "GET|/staff/reports/due|200"
  "GET|/staff/reports/collections|200"
  "GET|/staff/reports/expiring|200"
)

echo "=== Staff (authenticated) ==="
for ep in "${endpoints[@]}"; do
  IFS='|' read -r method path expect <<< "$ep"
  code=$(curl -s -o /tmp/smoke.json -w '%{http_code}' -H 'Accept: application/json' -H "$AUTH" -X "$method" "$BASE$path")
  check "$method $path" "$expect" "$code" "$(cat /tmp/smoke.json)"
done

echo "=== Auth required (no token) ==="
code=$(curl -s -o /tmp/smoke.json -w '%{http_code}' -H 'Accept: application/json' "$BASE/staff/dashboard")
check "dashboard no auth" "401" "$code" ""

echo ""
echo "Result: $ok passed, $fail failed"
[[ "$fail" -eq 0 ]]
