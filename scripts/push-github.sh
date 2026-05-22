#!/usr/bin/env bash
# Push ISP platform to https://github.com/habib2500a1/ispbillling
# Usage: GH_TOKEN=ghp_xxxx ./scripts/push-github.sh

set -euo pipefail
APP_ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$APP_ROOT"

REPO="https://github.com/habib2500a1/ispbillling.git"

if [[ -z "${GH_TOKEN:-}" ]] && [[ -f "$APP_ROOT/.env" ]]; then
  GH_TOKEN="$(grep -E '^GH_TOKEN=' "$APP_ROOT/.env" | cut -d= -f2- | tr -d '"' | tr -d "'" || true)"
  [[ -z "$GH_TOKEN" ]] && GH_TOKEN="$(grep -E '^GITHUB_TOKEN=' "$APP_ROOT/.env" | cut -d= -f2- | tr -d '"' | tr -d "'" || true)"
fi

if [[ -z "${GH_TOKEN:-}" ]]; then
  echo "Set GITHUB_TOKEN in .env or GH_TOKEN (classic: repo scope; fine-grained: Contents Read and write on ispbillling)."
  exit 1
fi

git -c safe.directory="$(pwd)" remote remove origin 2>/dev/null || true
git -c safe.directory="$(pwd)" remote add origin "https://${GH_TOKEN}@github.com/habib2500a1/ispbillling.git"

git -c safe.directory="$(pwd)" push -u origin main

git -c safe.directory="$(pwd)" remote set-url origin "$REPO"
echo "Done: https://github.com/habib2500a1/ispbillling"
