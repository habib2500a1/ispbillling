#!/usr/bin/env bash
# Publish Livewire JS/CSS and ensure /livewire/ works behind nginx static rules.
set -euo pipefail
cd "$(dirname "$0")/.."

run_artisan() {
  if id www-data &>/dev/null; then
    sudo -u www-data php artisan "$@"
  else
    php artisan "$@"
  fi
}

run_artisan livewire:publish --assets --force
ln -sfn "$(pwd)/public/vendor/livewire" "$(pwd)/public/livewire"

if [[ -x scripts/fix-storage-permissions.sh ]]; then
  bash scripts/fix-storage-permissions.sh
fi

echo "OK: public/vendor/livewire and public/livewire -> vendor/livewire"
ls -la public/livewire/livewire.min.js
