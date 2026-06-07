#!/usr/bin/env bash
# GitHub one-click installer — clone + install ISP Platform on cPanel/Webuzo.
#
# Run on server (cPanel Terminal):
#   curl -fsSL https://raw.githubusercontent.com/habib2500a1/ispbillling/main/install.sh | bash
#
# Or with custom folder:
#   INSTALL_DIR=/home/user/isp-platform curl -fsSL .../install.sh | bash
set -euo pipefail

REPO_URL="${REPO_URL:-https://github.com/habib2500a1/ispbillling.git}"
BRANCH="${BRANCH:-main}"
INSTALL_DIR="${INSTALL_DIR:-}"

if [[ -z "$INSTALL_DIR" ]]; then
  INSTALL_DIR="${HOME}/isp-platform"
fi

echo "==> ISP Platform one-click install"
echo "    Repo:   $REPO_URL"
echo "    Branch: $BRANCH"
echo "    Dir:    $INSTALL_DIR"
echo ""

if [[ -d "$INSTALL_DIR/.git" ]]; then
  echo "==> Existing install found — pulling latest..."
  cd "$INSTALL_DIR"
  git pull origin "$BRANCH"
else
  echo "==> Cloning repository..."
  git clone --branch "$BRANCH" --depth 1 "$REPO_URL" "$INSTALL_DIR"
  cd "$INSTALL_DIR"
fi

chmod +x scripts/install-cpanel-webuzo.sh scripts/post-deploy-cpanel.sh 2>/dev/null || true
bash scripts/install-cpanel-webuzo.sh "$@"
