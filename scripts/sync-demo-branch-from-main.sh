#!/usr/bin/env bash
# demo branch এ main এর নতুন code আনুন — deploy/production.url demo তে রাখে
set -euo pipefail

APP_ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$APP_ROOT"

DEMO_URL='https://demo.anetbd.com'

current="$(git branch --show-current)"
if [[ "$current" != "demo" ]]; then
  echo "Switching to demo branch..."
  git checkout demo
fi

echo "==> Pull latest demo"
git pull origin demo

echo "==> Merge main into demo"
git fetch origin main
if ! git merge origin/main -m "Merge main into demo ($(date -u +%Y-%m-%d))"; then
  echo ""
  echo "CONFLICT: resolve manually, then:"
  echo "  echo '$DEMO_URL' > deploy/production.url"
  echo "  git add deploy/production.url"
  echo "  git commit"
  echo "  git push origin demo"
  exit 1
fi

echo "$DEMO_URL" > deploy/production.url
if ! git diff --quiet deploy/production.url; then
  git add deploy/production.url
  git commit -m "Keep deploy/production.url on demo.anetbd.com after merge"
fi

echo "==> Push demo"
git push origin demo

echo "==> Done. NextDeploy demo app → Redeploy."
