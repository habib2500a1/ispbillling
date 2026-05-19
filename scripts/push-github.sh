#!/usr/bin/env bash
# Push ISP platform to https://github.com/habib2500a1/ispbillling
# Usage: GH_TOKEN=ghp_xxxx ./scripts/push-github.sh

set -euo pipefail
cd "$(dirname "$0")/.."

REPO="https://github.com/habib2500a1/ispbillling.git"

if [[ -z "${GH_TOKEN:-}" ]]; then
  echo "Set GH_TOKEN (GitHub Personal Access Token with repo scope)."
  echo "  export GH_TOKEN=ghp_xxxx"
  echo "  ./scripts/push-github.sh"
  exit 1
fi

git -c safe.directory="$(pwd)" remote remove origin 2>/dev/null || true
git -c safe.directory="$(pwd)" remote add origin "https://${GH_TOKEN}@github.com/habib2500a1/ispbillling.git"

git -c safe.directory="$(pwd)" push -u origin main

git -c safe.directory="$(pwd)" remote set-url origin "$REPO"
echo "Done: https://github.com/habib2500a1/ispbillling"
