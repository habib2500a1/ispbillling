#!/usr/bin/env bash
# Non-interactive GitHub login using token from .env or environment.
# Add to .env:  GITHUB_TOKEN=ghp_xxxxxxxx
# Then: ./scripts/gh-auth-with-token.sh
set -euo pipefail

APP_ROOT="$(cd "$(dirname "$0")/.." && pwd)"
TOKEN="${GITHUB_TOKEN:-}"

if [[ -z "$TOKEN" ]] && [[ -f "$APP_ROOT/.env" ]]; then
  TOKEN="$(grep -E '^GITHUB_TOKEN=' "$APP_ROOT/.env" | cut -d= -f2- | tr -d '"' | tr -d "'" || true)"
fi

# GH_TOKEN in the shell overrides gh login — clear so .env token is used.
unset GH_TOKEN

if [[ -z "$TOKEN" ]]; then
  echo "Missing GITHUB_TOKEN."
  echo "1. GitHub → Settings → Developer settings → Personal access tokens → Generate (classic)"
  echo "2. Scope: repo"
  echo "3. Add to .env: GITHUB_TOKEN=ghp_your_token"
  echo "4. Run this script again"
  exit 1
fi

echo "$TOKEN" | gh auth login --with-token
gh auth status
echo "OK — run: UPLOAD_GITHUB=1 ./scripts/release-all-mobile-apks.sh"
