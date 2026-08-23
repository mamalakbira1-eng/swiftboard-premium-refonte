#!/usr/bin/env bash
set -euo pipefail
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"
WPCLI='sudo docker compose run --quiet-pull --rm wpcli'
restore() {
  sudo docker exec swiftboard-db mariadb -uroot -prootwordpress -e 'DROP DATABASE wordpress; CREATE DATABASE wordpress;'
  sudo docker exec -i swiftboard-db mariadb -uwordpress -pwordpress wordpress < "$ROOT/baseline/reddit-before-locales.sql"
  sudo docker compose exec -T wordpress sh -lc 'rm -rf /var/www/html/wp-content/uploads/swiftboard-cache/*'
}
trap restore EXIT
sudo docker exec swiftboard-db mariadb -uroot -prootwordpress -e 'DROP DATABASE wordpress; CREATE DATABASE wordpress;'
sudo docker exec -i swiftboard-db mariadb -uwordpress -pwordpress wordpress < "$ROOT/baseline/locale-ar.sql"
$WPCLI option set WPLANG ar >/dev/null
$WPCLI language core install ar --activate >/dev/null 2>&1 || true
$WPCLI option set WPLANG ar >/dev/null
$WPCLI cache flush >/dev/null 2>&1 || true
sudo docker compose exec -T wordpress sh -lc 'rm -rf /var/www/html/wp-content/uploads/swiftboard-cache/*'
cd qa
SWIFTBOARD_BASE_URL="${SWIFTBOARD_BASE_URL:-http://localhost:8088}" SB_EXPECT_LOCALE=ar SB_EXPECT_DIR=rtl pnpm exec playwright test tests/cdc-locale.spec.mjs
