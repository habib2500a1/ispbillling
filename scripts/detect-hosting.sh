#!/usr/bin/env bash
# Detect hosting environment for deploy scripts.
set -euo pipefail

export HOSTING_TYPE="${HOSTING_TYPE:-}"
export WEB_USER="${WEB_USER:-$(whoami)}"
export WEB_GROUP="${WEB_GROUP:-$WEB_USER}"

if [[ -n "$HOSTING_TYPE" ]]; then
  return 0 2>/dev/null || true
  exit 0
fi

if [[ -d /usr/local/cpanel ]] || [[ -n "${CPANEL:-}" ]] || [[ "$(whoami)" == cpanel* ]]; then
  HOSTING_TYPE=cpanel
elif [[ -d /usr/local/webuzo ]] || [[ -n "${WEBUZO:-}" ]]; then
  HOSTING_TYPE=webuzo
elif id www-data &>/dev/null && [[ "$(id -u)" -eq 0 ]]; then
  HOSTING_TYPE=vps
elif [[ "$(id -u)" -ne 0 ]] && [[ "${HOME:-}" == /home/* ]]; then
  HOSTING_TYPE=cpanel
else
  HOSTING_TYPE=generic
fi

export HOSTING_TYPE WEB_USER WEB_GROUP
