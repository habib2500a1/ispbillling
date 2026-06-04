#!/usr/bin/env bash
# Regenerate Dart + Kotlin clients from public/docs/reseller-openapi.yaml
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
SPEC="$ROOT/public/docs/reseller-openapi.yaml"
CLI="@openapitools/openapi-generator-cli@2.15.3"

cd "$ROOT"

python3 - <<'PY' || true
from pathlib import Path
p = Path("public/docs/reseller-openapi.yaml")
t = p.read_text()
if "        '200':\n          content:" in t:
    p.write_text(t.replace("        '200':\n          content:", "        '200':\n          description: OK\n          content:"))
PY

rm -rf mobile/reseller_sdk/dart/generated mobile/reseller_sdk/kotlin/generated

npx --yes "$CLI" generate \
  -i "$SPEC" \
  -g dart \
  -o mobile/reseller_sdk/dart/generated \
  --additional-properties=pubName=isp_reseller_api,pubLibrary=isp_reseller_api

npx --yes "$CLI" generate \
  -i "$SPEC" \
  -g kotlin \
  -o mobile/reseller_sdk/kotlin/generated \
  --additional-properties=packageName=com.ispplatform.reseller.api,library=jvm-retrofit2,useCoroutines=true

echo "Done. Dart: mobile/reseller_sdk/dart/generated"
echo "Done. Kotlin: mobile/reseller_sdk/kotlin/generated"
