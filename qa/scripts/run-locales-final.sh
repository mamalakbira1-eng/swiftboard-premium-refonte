#!/usr/bin/env bash
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
BASE="${SWIFTBOARD_BASE_URL:?SWIFTBOARD_BASE_URL requis}"
CURRENT=/tmp/swiftboard-current-before-locales.sql
restore_current() {
  docker exec swiftboard-db mariadb -uroot -prootwordpress -e 'DROP DATABASE IF EXISTS wordpress; CREATE DATABASE wordpress;'
  docker exec -i swiftboard-db mariadb -uwordpress -pwordpress wordpress < "$CURRENT"
  docker compose -f "$ROOT/docker-compose.yml" run --rm --no-deps wpcli cache flush --allow-root >/dev/null 2>&1 || true
  docker exec swiftboard-wordpress sh -lc 'rm -rf /var/www/html/wp-content/uploads/swiftboard-cache/* /var/www/html/wp-content/cache/* 2>/dev/null || true'
}
trap restore_current EXIT
for locale in fr en ar; do
  SWIFTBOARD_BASE_URL="$BASE" "$ROOT/scripts/run-locale-qa.sh" "$locale"
done
