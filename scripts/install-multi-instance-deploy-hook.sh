#!/usr/bin/env bash
# Git hook: after pull, deploy all instances listed in deploy/instances.json (if present).
set -euo pipefail

APP_ROOT="$(cd "$(dirname "$0")/.." && pwd)"
HOOK="$APP_ROOT/.git/hooks/post-merge"
CONFIG="$APP_ROOT/deploy/instances.json"

if [[ -f "$CONFIG" ]]; then
  DEPLOY_CMD="bash \"$APP_ROOT/scripts/deploy-all-instances.sh\" >> \"$APP_ROOT/storage/logs/git-post-merge-deploy.log\" 2>&1 &"
else
  # shellcheck source=scripts/detect-hosting.sh
  source "$APP_ROOT/scripts/detect-hosting.sh"
  if [[ "$HOSTING_TYPE" == "vps" ]]; then
    DEPLOY_SCRIPT="post-deploy.sh"
  else
    DEPLOY_SCRIPT="post-deploy-cpanel.sh"
  fi
  DEPLOY_CMD="nohup bash \"$APP_ROOT/scripts/$DEPLOY_SCRIPT\" >> \"$APP_ROOT/storage/logs/git-post-merge-deploy.log\" 2>&1 &"
fi

cat > "$HOOK" <<EOF
#!/bin/bash
cd "$APP_ROOT" || exit 0
nohup bash -lc '$DEPLOY_CMD' &
EOF

chmod +x "$HOOK"
echo "Installed: $HOOK"

if [[ -f "$CONFIG" ]]; then
  echo "Multi-instance mode: git pull deploys all entries in deploy/instances.json"
else
  echo "Single-instance mode: git pull runs post-deploy.sh"
  echo "Tip: copy deploy/instances.example.json → deploy/instances.json for 10-domain deploy"
fi
