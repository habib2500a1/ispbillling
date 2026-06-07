#!/usr/bin/env bash
# Download mobile APKs from GitHub Releases → public/downloads/ (uses .env token + tags).
set -euo pipefail

APP_ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$APP_ROOT"

ENV_FILE="${ENV_FILE:-$APP_ROOT/.env}"

read_env() {
  local key="$1"
  if [[ ! -f "$ENV_FILE" ]]; then
    echo ""
    return
  fi
  grep -E "^${key}=" "$ENV_FILE" | head -1 | cut -d= -f2- | tr -d '"' | tr -d "'"
}

REPO="$(read_env MOBILE_GITHUB_REPO)"
REPO="${REPO:-habib2500a1/ispbillling}"
RADIANT_TAG="$(read_env MOBILE_RADIANT_GITHUB_TAG)"
MFS_TAG="$(read_env MOBILE_MFS_GITHUB_TAG)"
TOKEN="$(read_env GITHUB_TOKEN)"

if [[ -z "$RADIANT_TAG" ]]; then
  if [[ -f mobile/isp_radiant/pubspec.yaml ]]; then
    ver="$(grep -E '^version:' mobile/isp_radiant/pubspec.yaml | head -1 | awk '{print $2}' | cut -d+ -f1)"
    RADIANT_TAG="isp-radiant-v${ver}"
  fi
fi

if [[ -z "$MFS_TAG" ]]; then
  if [[ -f mobile/mfs_verify/pubspec.yaml ]]; then
    ver="$(grep -E '^version:' mobile/mfs_verify/pubspec.yaml | head -1 | awk '{print $2}' | cut -d+ -f1)"
    MFS_TAG="mfs-verify-v${ver}"
  fi
fi

mkdir -p public/downloads

download_release() {
  local tag="$1"
  local dest_name="$2"
  local dest="$APP_ROOT/public/downloads/$dest_name"
  local tmpdir
  tmpdir="$(mktemp -d)"

  if [[ -z "$tag" ]]; then
    echo "[sync] Skip $dest_name — no GitHub tag"
    rm -rf "$tmpdir"
    return 0
  fi

  echo "[sync] GitHub $REPO @$tag → $dest_name"

  if command -v gh >/dev/null 2>&1 && [[ -n "$TOKEN" ]]; then
    env -u GITHUB_TOKEN -u GH_TOKEN bash -c "
      echo '$TOKEN' | gh auth login --with-token >/dev/null 2>&1
      gh release download '$tag' -R '$REPO' -D '$tmpdir' --clobber
    " || true
  fi

  local apk=""
  for candidate in "$tmpdir"/isp-radiant.apk "$tmpdir"/isp-mfs-verify.apk "$tmpdir"/app-release.apk "$tmpdir"/*.apk; do
    if [[ -f "$candidate" ]] && [[ "$(stat -c%s "$candidate" 2>/dev/null || echo 0)" -gt 1000 ]]; then
      apk="$candidate"
      break
    fi
  done

  if [[ -z "$apk" ]] && [[ -n "$TOKEN" ]]; then
    for asset in "$dest_name" app-release.apk; do
      local url="https://github.com/${REPO}/releases/download/${tag}/${asset}"
      if curl -fsSL -H "Authorization: Bearer ${TOKEN}" -H "Accept: application/octet-stream" \
        -o "$tmpdir/fallback.apk" "$url" 2>/dev/null \
        && [[ "$(stat -c%s "$tmpdir/fallback.apk" 2>/dev/null || echo 0)" -gt 1000 ]]; then
        apk="$tmpdir/fallback.apk"
        break
      fi
    done
  fi

  if [[ -z "$apk" ]]; then
    echo "[sync] WARN: could not download $dest_name from $tag"
    rm -rf "$tmpdir"
    return 0
  fi

  cp -f "$apk" "$dest"
  rm -rf "$tmpdir"
  echo "[sync] OK: $dest ($(du -h "$dest" | awk '{print $1}'))"
}

download_release "$RADIANT_TAG" "isp-radiant.apk"
download_release "$MFS_TAG" "isp-mfs-verify.apk"

if [[ -f public/downloads/isp-mfs-verify.apk ]] && [[ -f vendor/autoload.php ]]; then
  php -r "
    require '$APP_ROOT/vendor/autoload.php';
    \$app = require '$APP_ROOT/bootstrap/app.php';
    \$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
    \$meta = App\Support\MobileApkRelease::parsePubspecVersion(App\Support\MobileApkRelease::MFS_VERIFY_PUBSPEC);
    if (\$meta) {
      App\Support\MobileApkRelease::writeMfsVerifyManifest(\$meta);
    }
  " 2>/dev/null || true
fi

if id www-data >/dev/null 2>&1; then
  chown www-data:www-data public/downloads/*.apk public/downloads/*.json 2>/dev/null || true
fi
chmod 644 public/downloads/*.apk public/downloads/*.json 2>/dev/null || true
