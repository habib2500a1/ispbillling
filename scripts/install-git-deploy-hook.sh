#!/usr/bin/env bash
# Install git post-merge hook — auto post-deploy after git pull on server.
set -euo pipefail

APP_ROOT="$(cd "$(dirname "$0")/.." && pwd)"
HOOK="$APP_ROOT/.git/hooks/post-merge"

cat > "$HOOK" <<EOF
#!/bin/bash
# Auto post-deploy after git pull / merge on server
cd "$APP_ROOT" || exit 0
nohup bash "$APP_ROOT/scripts/post-deploy.sh" >> "$APP_ROOT/storage/logs/git-post-merge-deploy.log" 2>&1 &
EOF

chmod +x "$HOOK"
echo "Installed: $HOOK"
echo "Now 'git pull' on server auto-runs post-deploy (mobile APKs, cache, etc.)."
