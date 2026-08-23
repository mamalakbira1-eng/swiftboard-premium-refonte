#!/usr/bin/env bash
set -euo pipefail
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"
WPCLI='sudo docker compose run --quiet-pull --rm wpcli'
DB='sudo docker exec swiftboard-db mariadb -uwordpress -pwordpress wordpress'
QA_PASSWORD="${SB_QA_PASSWORD:?Définir SB_QA_PASSWORD uniquement dans l’environnement local de recette}"
restore() {
  $WPCLI config delete SWIFTBOARD_ENABLE_SSE >/dev/null 2>&1 || true
  sudo docker exec swiftboard-db mariadb -uroot -prootwordpress -e 'DROP DATABASE wordpress; CREATE DATABASE wordpress;'
  sudo docker exec -i swiftboard-db mariadb -uwordpress -pwordpress wordpress < "$ROOT/baseline/reddit-before-locales.sql"
  sudo docker compose exec -T wordpress sh -lc 'rm -rf /var/www/html/wp-content/uploads/swiftboard-cache/*'
}
trap restore EXIT
$WPCLI config set SWIFTBOARD_ENABLE_SSE true --raw >/dev/null
$WPCLI user update sbmember --user_pass="$QA_PASSWORD" >/dev/null
USER_ID="$($WPCLI user get sbmember --field=ID 2>/dev/null | tail -1)"
SQL="DELETE FROM wp_swiftboard_notifications WHERE user_id=${USER_ID};"
for i in $(seq 1 20); do
  SQL+=" INSERT INTO wp_swiftboard_notifications (user_id,actor_id,type,post_id,post_type,title,excerpt,is_read,created_at) VALUES (${USER_ID},1,'mention',40,'topic','QA SSE ${i}','Synthetic local SSE validation event ${i}',0,NOW());"
done
$DB -e "$SQL"
$WPCLI cache flush >/dev/null 2>&1 || true
sudo docker compose exec -T wordpress sh -lc 'rm -rf /var/www/html/wp-content/uploads/swiftboard-cache/*'
cd qa
SWIFTBOARD_BASE_URL="${SWIFTBOARD_BASE_URL:-http://localhost:8088}" SB_TEST_USER='sbmember' SB_TEST_PASSWORD="$QA_PASSWORD" pnpm exec playwright test tests/cdc-sse.spec.mjs
