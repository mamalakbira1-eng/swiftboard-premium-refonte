#!/usr/bin/env bash
set -euo pipefail
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
LOCALE="${1:-}"
case "$LOCALE" in
  fr|en|ar) ;;
  *) echo "Usage: $0 {fr|en|ar}" >&2; exit 2 ;;
esac
DIR='ltr'
WP_LOCALE='en_US'
case "$LOCALE" in
  fr) WP_LOCALE='fr_FR' ;;
  en) WP_LOCALE='en_US' ;;
  ar) WP_LOCALE='ar'; DIR='rtl' ;;
esac
cd "$ROOT"
WPCLI='sudo docker compose run --quiet-pull --rm wpcli'
restore() {
  sudo docker exec swiftboard-db mariadb -uroot -prootwordpress -e 'DROP DATABASE wordpress; CREATE DATABASE wordpress;'
  sudo docker exec -i swiftboard-db mariadb -uwordpress -pwordpress wordpress < "$ROOT/baseline/reddit-before-locales.sql"
  $WPCLI option set WPLANG en_US >/dev/null 2>&1 || true
  $WPCLI cache flush >/dev/null 2>&1 || true
  sudo docker compose exec -T wordpress sh -lc 'rm -rf /var/www/html/wp-content/uploads/swiftboard-cache/*'
}
trap restore EXIT
sudo docker exec swiftboard-db mariadb -uroot -prootwordpress -e 'DROP DATABASE wordpress; CREATE DATABASE wordpress;'
sudo docker exec -i swiftboard-db mariadb -uwordpress -pwordpress wordpress < "$ROOT/baseline/locale-$LOCALE.sql"
$WPCLI option set WPLANG "$WP_LOCALE" >/dev/null
$WPCLI language core install "$WP_LOCALE" --activate >/dev/null 2>&1 || true
$WPCLI option set WPLANG "$WP_LOCALE" >/dev/null
$WPCLI cache flush >/dev/null 2>&1 || true
sudo docker compose exec -T wordpress sh -lc 'rm -rf /var/www/html/wp-content/uploads/swiftboard-cache/*'
cd qa
SWIFTBOARD_BASE_URL="${SWIFTBOARD_BASE_URL:-http://localhost:8088}" SB_EXPECT_LOCALE="$LOCALE" SB_EXPECT_DIR="$DIR" pnpm exec playwright test tests/cdc-locale.spec.mjs --project=chromium-desktop
