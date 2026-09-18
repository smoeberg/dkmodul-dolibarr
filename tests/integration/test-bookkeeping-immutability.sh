#!/usr/bin/env bash
set -euo pipefail

cd "$(dirname "$0")"

cleanup() {
  docker compose down -v --remove-orphans >/dev/null 2>&1 || true
}
trap cleanup EXIT

docker compose up -d

echo "Waiting for Dolibarr accounting table..."
for _ in $(seq 1 120); do
  if docker compose exec -T mariadb mariadb -uroot -proot dolidb -Nse "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='dolidb' AND table_name='llx_accounting_bookkeeping'" 2>/dev/null | grep -q '^1$'; then
    break
  fi
  sleep 2
done

if ! docker compose exec -T mariadb mariadb -uroot -proot dolidb -Nse "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='dolidb' AND table_name='llx_accounting_bookkeeping'" | grep -q '^1$'; then
  echo "Dolibarr database initialization did not finish"
  docker compose logs dolibarr
  exit 1
fi

echo "Waiting for Dolibarr installation lock..."
for _ in $(seq 1 60); do
  if docker compose exec -T dolibarr test -f /var/www/documents/install.lock; then
    break
  fi
  sleep 2
done
docker compose exec -T dolibarr test -f /var/www/documents/install.lock

echo "Verifying real Dolibarr module activation..."
module_enabled="$(docker compose exec -T mariadb mariadb -uroot -proot dolidb -Nse "SELECT value FROM llx_const WHERE name='MAIN_MODULE_DKMODUL' AND entity=1 ORDER BY rowid DESC LIMIT 1")"
test "$module_enabled" = "1"

for table in llx_dk_audit_event llx_dk_correction llx_dk_bookkeeping_origin; do
  table_count="$(docker compose exec -T mariadb mariadb -uroot -proot dolidb -Nse "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='dolidb' AND table_name='$table'")"
  test "$table_count" = "1"
done

trigger_count="$(docker compose exec -T mariadb mariadb -uroot -proot dolidb -Nse "SELECT COUNT(*) FROM information_schema.triggers WHERE trigger_schema='dolidb' AND trigger_name LIKE 'llx_dk_%'")"
test "$trigger_count" = "5"

sql() {
  docker compose exec -T mariadb mariadb -uroot -proot dolidb -Nse "$1"
}

expect_failure() {
  local statement="$1"
  if sql "$statement" >/tmp/dkmodul-expected-failure.log 2>&1; then
    echo "Expected statement to fail but it succeeded: $statement"
    exit 1
  fi
  if ! grep -q "DK compliance" /tmp/dkmodul-expected-failure.log; then
    cat /tmp/dkmodul-expected-failure.log
    echo "Statement failed, but not because of the DK compliance guard"
    exit 1
  fi
}

echo "Creating unlocked test entry..."
sql "INSERT INTO llx_accounting_bookkeeping
(entity,piece_num,doc_date,doc_type,doc_ref,fk_doc,fk_docdet,numero_compte,label_compte,debit,credit,fk_user_author,date_creation,code_journal)
VALUES (1,990001,CURDATE(),'dk_test','DK-TEST-1',0,0,'1000','DK test',100.00,0.00,1,NOW(),'OD')"

rowid="$(sql "SELECT rowid FROM llx_accounting_bookkeeping WHERE piece_num=990001 AND entity=1")"

origin_count="$(sql "SELECT COUNT(*) FROM llx_dk_bookkeeping_origin WHERE bookkeeping_rowid=${rowid} AND fk_user_author=1 AND origin_type='insert'")"
test "$origin_count" = "1"

sql "UPDATE llx_accounting_bookkeeping SET debit=125.00 WHERE rowid=${rowid}"
test "$(sql "SELECT COUNT(*) FROM llx_accounting_bookkeeping WHERE rowid=${rowid} AND ABS(debit-125.00) < 0.000001")" = "1"

sql "UPDATE llx_accounting_bookkeeping SET date_validated=NOW() WHERE rowid=${rowid}"

expect_failure "UPDATE llx_accounting_bookkeeping SET debit=130.00 WHERE rowid=${rowid}"
expect_failure "UPDATE llx_accounting_bookkeeping SET doc_date=DATE_SUB(doc_date, INTERVAL 1 DAY) WHERE rowid=${rowid}"
expect_failure "UPDATE llx_accounting_bookkeeping SET numero_compte='2000' WHERE rowid=${rowid}"
expect_failure "UPDATE llx_accounting_bookkeeping SET lettering_code='A' WHERE rowid=${rowid}"
expect_failure "UPDATE llx_accounting_bookkeeping SET date_validated=NULL WHERE rowid=${rowid}"
expect_failure "DELETE FROM llx_accounting_bookkeeping WHERE rowid=${rowid}"

origin_rowid="$(sql "SELECT rowid FROM llx_dk_bookkeeping_origin WHERE bookkeeping_rowid=${rowid}")"
expect_failure "UPDATE llx_dk_bookkeeping_origin SET fk_user_author=99 WHERE rowid=${origin_rowid}"
expect_failure "DELETE FROM llx_dk_bookkeeping_origin WHERE rowid=${origin_rowid}"

sql "UPDATE llx_accounting_bookkeeping SET date_export=NOW() WHERE rowid=${rowid}"
test -n "$(sql "SELECT date_export FROM llx_accounting_bookkeeping WHERE rowid=${rowid}")"

echo "DK bookkeeping database immutability integration test passed"

echo "Creating balanced bookkeeping transaction for canonical adapter..."
sql "INSERT INTO llx_accounting_bookkeeping
(entity,ref,piece_num,doc_date,doc_type,doc_ref,fk_doc,fk_docdet,numero_compte,label_compte,label_operation,debit,credit,fk_user_author,date_creation,code_journal,date_validated)
VALUES
(1,'DK-990002',990002,CURDATE(),'dk_test','DK-CANONICAL-1',0,0,'1000','Cash','Canonical debit',250.00,0.00,1,NOW(),'OD',NOW()),
(1,'DK-990002',990002,CURDATE(),'dk_test','DK-CANONICAL-1',0,0,'3000','Revenue','Canonical credit',0.00,250.00,1,NOW(),'OD',NOW())"

docker compose exec -T dolibarr php /var/www/dkmodul-tests/assert-canonical-provider.php

echo "Dolibarr DK accounting integration tests passed"
