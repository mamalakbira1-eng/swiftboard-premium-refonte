#!/usr/bin/env bash
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
BASE="${SWIFTBOARD_BASE_URL:?SWIFTBOARD_BASE_URL requis}"
OUT="$ROOT/reports/lighthouse-lot10"
mkdir -p "$OUT"
for entry in \
  "home|/" \
  "forum|/forums/forum/finances/" \
  "topic|/forums/topic/par-ou-commencer-une-epargne-d-urgence/" \
  "profile|/forums/users/sbvip/" \
  "login|/wp-login.php" \
  "search|/?s=zzzz-no-result-swiftboard-qa"; do
  label="${entry%%|*}"
  path="${entry#*|}"
  url="${BASE}${path}"
  echo "Lighthouse $label $url"
  "$ROOT/qa/node_modules/.bin/lighthouse" "$url" \
    --quiet \
    --chrome-flags="--headless --no-sandbox --disable-gpu" \
    --only-categories=performance,accessibility,best-practices,seo \
    --output=json \
    --output-path="$OUT/${label}.json" \
    --form-factor=desktop \
    --screenEmulation.mobile=false \
    --throttling-method=simulate
 done
node "$ROOT/qa/scripts/summarize-lighthouse-lot10.mjs"
