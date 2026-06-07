#!/usr/bin/env bash
# Install git post-merge hook — auto post-deploy after git pull on server.
set -euo pipefail

APP_ROOT="$(cd "$(dirname "$0")/.." && pwd)"
HOOK="$APP_ROOT/.git/hooks/post-merge"

# shellcheck source=scripts/detect-hosting.sh
source "$APP_ROOT/scripts/detect-hosting.sh"
if [[ "$HOSTING_TYPE" == "vps" ]]; then
  DEPLOY_SCRIPT="post-deploy.sh"
else
  DEPLOY_SCRIPT="post-deploy-cpanel.sh"
fi

cat > "$HOOK" <<EOF
#!/bin/bash
# Auto post-deploy after git pull / merge on server
cd "$APP_ROOT" || exit 0
nohup bash "$APP_ROOT/scripts/$DEPLOY_SCRIPT" >> "$APP_ROOT/storage/logs/git-post-merge-deploy.log" 2>&1 &
EOF

chmod +x "$HOOK"
echo "Installed: $HOOK"
echo "Now 'git pull' on server auto-runs post-deploy (mobile APKs, cache, etc.)."
