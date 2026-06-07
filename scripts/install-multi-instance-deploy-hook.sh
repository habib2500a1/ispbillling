#!/usr/bin/env bash
# Optional: git hook for many clones listed in deploy/instances.json.
# Default (easy): use install-git-deploy-hook.sh — reads domain from .env only.
set -euo pipefail

APP_ROOT="$(cd "$(dirname "$0")/.." && pwd)"
HOOK="$APP_ROOT/.git/hooks/post-merge"
CONFIG="$APP_ROOT/deploy/instances.json"

if [[ ! -f "$CONFIG" ]]; then
  echo "No $CONFIG — use install-git-deploy-hook.sh for single .env deploy."
  exit 1
fi

DEPLOY_CMD="bash \"$APP_ROOT/scripts/deploy-all-instances.sh\" >> \"$APP_ROOT/storage/logs/git-post-merge-deploy.log\" 2>&1 &"

cat > "$HOOK" <<EOF
#!/bin/bash
cd "$APP_ROOT" || exit 0
nohup bash -lc '$DEPLOY_CMD' &
EOF

chmod +x "$HOOK"
echo "Installed: $HOOK"
echo "Multi-clone mode: git pull deploys all entries in deploy/instances.json"
