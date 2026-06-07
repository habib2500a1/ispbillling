#!/usr/bin/env bash
# Install git post-merge hook — auto deploy from .env APP_URL after git pull.
set -euo pipefail

APP_ROOT="$(cd "$(dirname "$0")/.." && pwd)"
HOOK="$APP_ROOT/.git/hooks/post-merge"

cat > "$HOOK" <<EOF
#!/bin/bash
# Auto deploy after git pull — reads domain from .env APP_URL
cd "$APP_ROOT" || exit 0
nohup bash "$APP_ROOT/scripts/deploy-from-env.sh" >> "$APP_ROOT/storage/logs/git-post-merge-deploy.log" 2>&1 &
EOF

chmod +x "$HOOK"
echo "Installed: $HOOK"
echo "git pull will run deploy-from-env.sh (reads APP_URL from .env, builds app for that domain)."
echo "Optional: deploy/instances.json only if you run many clones on one server."
