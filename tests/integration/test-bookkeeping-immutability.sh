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

echo "Ensuring OIOUBL XSLT 2.0 runtime dependency..."
if ! docker compose exec -T dolibarr test -f /usr/share/java/Saxon-HE.jar; then
  docker compose exec -T dolibarr sh -c 'apt-get update -qq && apt-get install -y -qq --no-install-recommends default-jre-headless libsaxonhe-java >/dev/null && rm -rf /var/lib/apt/lists/*'
fi
docker compose exec -T dolibarr java -jar /usr/share/java/Saxon-HE.jar -? >/dev/null

echo "Verifying real Dolibarr module activation..."
module_enabled="$(docker compose exec -T mariadb mariadb -uroot -proot dolidb -Nse "SELECT value FROM llx_const WHERE name='MAIN_MODULE_DKMODUL' AND entity=1 ORDER BY rowid DESC LIMIT 1")"
test "$module_enabled" = "1"

for table in llx_dk_audit_event llx_dk_correction llx_dk_bookkeeping_origin llx_dk_document_archive llx_dk_einvoice_delivery llx_dk_einvoice_transport_event llx_dk_einvoice_inbound llx_dk_einvoice_inbound_validation llx_dk_einvoice_inbound_draft llx_dk_einvoice_inbound_supplier_validation llx_dk_einvoice_inbound_posting llx_dk_standard_account llx_dk_account_mapping llx_dk_standard_vat_code llx_dk_vat_mapping; do
  table_count="$(docker compose exec -T mariadb mariadb -uroot -proot dolidb -Nse "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='dolidb' AND table_name='$table'")"
  test "$table_count" = "1"
done

test "$(docker compose exec -T mariadb mariadb -uroot -proot dolidb -Nse "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='dolidb' AND table_name='llx_dk_correction_link'")" = "0"

trigger_count="$(docker compose exec -T mariadb mariadb -uroot -proot dolidb -Nse "SELECT COUNT(*) FROM information_schema.triggers WHERE trigger_schema='dolidb' AND trigger_name LIKE 'llx_dk_%'")"
test "$trigger_count" = "23"

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
VALUES (1,990001,'2031-01-01','dk_test','DK-TEST-1',0,0,'1000','DK test',100.00,0.00,1,NOW(),'OD')"

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

echo "Archiving immutable digital bookkeeping evidence..."
docker compose exec -T dolibarr php /var/www/dkmodul-tests/assert-document-archive.php
document_rowid="$(sql "SELECT rowid FROM llx_dk_document_archive WHERE entity=1 AND bookkeeping_rowid=${rowid} ORDER BY rowid DESC LIMIT 1")"
test -n "$document_rowid"
expect_failure "UPDATE llx_dk_document_archive SET original_name='changed.pdf' WHERE rowid=${document_rowid}"
expect_failure "DELETE FROM llx_dk_document_archive WHERE rowid=${document_rowid}"
test "$(sql "SELECT COUNT(*) FROM llx_dk_document_archive WHERE rowid=${document_rowid} AND retain_until='2036-12-31'")" = "1"

echo "Creating balanced bookkeeping transaction for canonical adapter..."
sql "INSERT INTO llx_accounting_bookkeeping
(entity,ref,piece_num,doc_date,doc_type,doc_ref,fk_doc,fk_docdet,numero_compte,label_compte,label_operation,debit,credit,fk_user_author,date_creation,code_journal,date_validated)
VALUES
(1,'DK-990002',990002,'2026-09-18','dk_test','DK-CANONICAL-1',0,0,'1000','Cash','Canonical debit',250.00,0.00,1,NOW(),'OD',NOW()),
(1,'DK-990002',990002,'2026-09-18','dk_test','DK-CANONICAL-1',0,0,'3000','Revenue','Canonical credit',0.00,250.00,1,NOW(),'OD',NOW())"

docker compose exec -T dolibarr php /var/www/dkmodul-tests/assert-canonical-provider.php

echo "Creating real customer invoice source for VAT provenance..."

revenue_account_rowid="$(sql "SELECT rowid FROM llx_accounting_account WHERE entity=1 AND account_number='3000' ORDER BY rowid LIMIT 1")"
if [ -z "$revenue_account_rowid" ]; then
  pcg_version="$(sql "SELECT pcg_version FROM llx_accounting_system WHERE active=1 ORDER BY rowid LIMIT 1")"
  if [ -z "$pcg_version" ]; then
    pcg_version="$(sql "SELECT pcg_version FROM llx_accounting_system ORDER BY rowid LIMIT 1")"
  fi
  test -n "$pcg_version"

  sql "INSERT INTO llx_accounting_account
  (entity,datec,fk_pcg_version,pcg_type,account_number,label,fk_user_author,active)
  VALUES (1,NOW(),'${pcg_version}','INCOME','3000','Revenue',1,1)"
  revenue_account_rowid="$(sql "SELECT rowid FROM llx_accounting_account WHERE entity=1 AND account_number='3000' ORDER BY rowid LIMIT 1")"
fi
test -n "$revenue_account_rowid"

sql "INSERT INTO llx_societe
(nom,entity,status,code_client,fk_pays,fk_stcomm,client,fournisseur,datec,address,zip,town,siren,email)
VALUES ('DK VAT Customer',1,1,'DKVATCUST',(SELECT rowid FROM llx_c_country WHERE code='DK' LIMIT 1),0,1,0,NOW(),'Kundevej 2','2100','København','87654321','customer@example.invalid')"
customer_id="$(sql "SELECT rowid FROM llx_societe WHERE code_client='DKVATCUST' ORDER BY rowid DESC LIMIT 1")"
test -n "$customer_id"

sql "INSERT INTO llx_facture
(ref,entity,type,fk_soc,datec,datef,date_lim_reglement,total_tva,total_ht,total_ttc,fk_statut,fk_user_author,fk_cond_reglement)
VALUES ('DKVAT-1',1,0,${customer_id},NOW(),'2026-09-18','2026-10-18',62.50,250.00,312.50,1,1,1)"
invoice_id="$(sql "SELECT rowid FROM llx_facture WHERE ref='DKVAT-1' AND entity=1 ORDER BY rowid DESC LIMIT 1")"
test -n "$invoice_id"

sql "INSERT INTO llx_facturedet
(fk_facture,label,description,vat_src_code,tva_tx,qty,subprice,total_ht,total_tva,total_ttc,product_type,fk_code_ventilation)
VALUES (${invoice_id},'VAT test sale','VAT test sale','DKTEST25',25,1,250.00,250.00,62.50,312.50,0,${revenue_account_rowid})"

sql "INSERT INTO llx_accounting_bookkeeping
(entity,ref,piece_num,doc_date,doc_type,doc_ref,fk_doc,fk_docdet,thirdparty_code,subledger_account,subledger_label,numero_compte,label_compte,label_operation,debit,credit,fk_user_author,date_creation,code_journal,journal_label,date_validated)
VALUES
(1,'DK-990003',990003,'2026-09-18','customer_invoice','DKVAT-1',${invoice_id},0,'DKVATCUST','DKVATCUST','DK VAT Customer','1000','Receivables','Customer receivable',312.50,0.00,1,NOW(),'VT','Sales',NOW()),
(1,'DK-990003',990003,'2026-09-18','customer_invoice','DKVAT-1',${invoice_id},0,'DKVATCUST','','','3000','Revenue','VAT test sale',0.00,250.00,1,NOW(),'VT','Sales',NOW()),
(1,'DK-990003',990003,'2026-09-18','customer_invoice','DKVAT-1',${invoice_id},0,'DKVATCUST','','','2600','Sales VAT','Sales VAT',0.00,62.50,1,NOW(),'VT','Sales',NOW())"

docker compose exec -T dolibarr php /var/www/dkmodul-tests/assert-tax-provenance.php

echo "Validating and archiving outbound OIOUBL invoice..."
rm -rf /tmp/erst-openebusiness-common
git clone -q https://git.erst.dk/openebusiness/common.git /tmp/erst-openebusiness-common
git -C /tmp/erst-openebusiness-common checkout -q 223694e79eb4dbf0895640b35484ab55abae2c42
docker compose cp /tmp/erst-openebusiness-common/resources/Schemas/UBL_v2.1 dolibarr:/tmp/oioubl-schema
docker compose cp /tmp/erst-openebusiness-common/resources/Schematrons/OIOUBL/OIOUBL_Invoice_Schematron.xsl dolibarr:/tmp/OIOUBL_Invoice_Schematron.xsl
docker compose exec -T dolibarr php /var/www/dkmodul-tests/assert-oioubl-outbound.php
docker compose exec -T dolibarr java -jar /usr/share/java/Saxon-HE.jar \
  -s:/tmp/DKVAT-1.xml \
  -xsl:/tmp/OIOUBL_Invoice_Schematron.xsl \
  -o:/tmp/OIOUBL_Invoice_Schematron_Result.xml
docker compose exec -T dolibarr php /var/www/dkmodul-tests/assert-oioubl-outbound.php

echo "Dispatching archived OIOUBL through transport boundary..."
docker compose exec -T dolibarr php /var/www/dkmodul-tests/assert-einvoice-transport.php
delivery_rowid="$(sql "SELECT rowid FROM llx_dk_einvoice_delivery WHERE entity=1 ORDER BY rowid DESC LIMIT 1")"
transport_event_rowid="$(sql "SELECT rowid FROM llx_dk_einvoice_transport_event WHERE entity=1 AND event_type='accepted' ORDER BY rowid DESC LIMIT 1")"
test -n "$delivery_rowid"
test -n "$transport_event_rowid"
expect_failure "UPDATE llx_dk_einvoice_delivery SET endpoint_id='changed' WHERE rowid=${delivery_rowid}"
expect_failure "DELETE FROM llx_dk_einvoice_delivery WHERE rowid=${delivery_rowid}"
expect_failure "UPDATE llx_dk_einvoice_transport_event SET receipt_code='changed' WHERE rowid=${transport_event_rowid}"
expect_failure "DELETE FROM llx_dk_einvoice_transport_event WHERE rowid=${transport_event_rowid}"

echo "Staging and validating inbound OIOUBL invoice..."
docker compose exec -T dolibarr php /var/www/dkmodul-tests/assert-oioubl-inbound-staging.php
inbound_rowid="$(sql "SELECT rowid FROM llx_dk_einvoice_inbound WHERE entity=1 ORDER BY rowid DESC LIMIT 1")"
inbound_validation_rowid="$(sql "SELECT rowid FROM llx_dk_einvoice_inbound_validation WHERE entity=1 ORDER BY rowid DESC LIMIT 1")"
test -n "$inbound_rowid"
test -n "$inbound_validation_rowid"
expect_failure "UPDATE llx_dk_einvoice_inbound SET sender_endpoint_id='changed' WHERE rowid=${inbound_rowid}"
expect_failure "DELETE FROM llx_dk_einvoice_inbound WHERE rowid=${inbound_rowid}"
expect_failure "UPDATE llx_dk_einvoice_inbound_validation SET invoice_id='changed' WHERE rowid=${inbound_validation_rowid}"
expect_failure "DELETE FROM llx_dk_einvoice_inbound_validation WHERE rowid=${inbound_validation_rowid}"

echo "Approving validated inbound OIOUBL into supplier invoice draft..."
sql "INSERT INTO llx_societe
(nom,entity,status,code_fournisseur,fk_pays,fk_stcomm,client,fournisseur,datec,address,zip,town,siren,email)
VALUES ('Inbound OIOUBL Supplier',1,1,'DKINBOUND',(SELECT rowid FROM llx_c_country WHERE code='DK' LIMIT 1),0,0,1,NOW(),'Testvej 1','8000','Aarhus C','12345678','supplier@example.invalid')"
docker compose exec -T dolibarr php /var/www/dkmodul-tests/assert-oioubl-inbound-supplier-draft.php
inbound_draft_rowid="$(sql "SELECT rowid FROM llx_dk_einvoice_inbound_draft WHERE entity=1 ORDER BY rowid DESC LIMIT 1")"
test -n "$inbound_draft_rowid"
expect_failure "UPDATE llx_dk_einvoice_inbound_draft SET supplier_invoice_ref='changed' WHERE rowid=${inbound_draft_rowid}"
expect_failure "DELETE FROM llx_dk_einvoice_inbound_draft WHERE rowid=${inbound_draft_rowid}"

echo "Explicitly validating inbound supplier invoice without ledger transfer..."
docker compose exec -T dolibarr php /var/www/dkmodul-tests/assert-oioubl-inbound-supplier-validation.php
inbound_supplier_validation_rowid="$(sql "SELECT rowid FROM llx_dk_einvoice_inbound_supplier_validation WHERE entity=1 ORDER BY rowid DESC LIMIT 1")"
test -n "$inbound_supplier_validation_rowid"
expect_failure "UPDATE llx_dk_einvoice_inbound_supplier_validation SET fk_user_validator=2 WHERE rowid=${inbound_supplier_validation_rowid}"
expect_failure "DELETE FROM llx_dk_einvoice_inbound_supplier_validation WHERE rowid=${inbound_supplier_validation_rowid}"

echo "Configuring explicit native mappings for inbound supplier posting..."
sql "DELETE FROM llx_accounting_fiscalyear WHERE entity=1 AND label='DKSAFT-2026'"
sql "INSERT INTO llx_accounting_fiscalyear
(label,date_start,date_end,statut,entity,datec,fk_user_author)
VALUES ('DKSAFT-2026','2026-01-01','2026-12-31',0,1,NOW(),1)"
posting_pcg_version="$(sql "SELECT fk_pcg_version FROM llx_accounting_account WHERE entity=1 AND active=1 ORDER BY rowid LIMIT 1")"
test -n "$posting_pcg_version"
for account in 2000 4000 4450; do
  if [ "$(sql "SELECT COUNT(*) FROM llx_accounting_account WHERE entity=1 AND account_number='${account}' AND active=1")" = "0" ]; then
    type="OTHER"
    label="Integration account ${account}"
    if [ "$account" = "4000" ]; then type="EXPENSE"; label="Purchases"; fi
    if [ "$account" = "2000" ]; then type="LIABILITY"; label="Supplier payable"; fi
    if [ "$account" = "4450" ]; then type="ASSET"; label="Purchase VAT"; fi
    sql "INSERT INTO llx_accounting_account
    (entity,datec,fk_pcg_version,pcg_type,account_number,label,fk_user_author,active)
    VALUES (1,NOW(),'${posting_pcg_version}','${type}','${account}','${label}',1,1)"
  fi
done
inbound_supplier_id="$(sql "SELECT rowid FROM llx_societe WHERE entity=1 AND code_fournisseur='DKINBOUND' ORDER BY rowid DESC LIMIT 1")"
inbound_supplier_invoice_id="$(sql "SELECT supplier_invoice_rowid FROM llx_dk_einvoice_inbound_draft WHERE entity=1 ORDER BY rowid DESC LIMIT 1")"
inbound_expense_account_id="$(sql "SELECT rowid FROM llx_accounting_account WHERE entity=1 AND account_number='4000' AND active=1 ORDER BY rowid LIMIT 1")"
dk_posting_country_id="$(sql "SELECT rowid FROM llx_c_country WHERE code='DK' LIMIT 1")"
sql "UPDATE llx_societe SET accountancy_code_supplier_general='2000',code_compta_fournisseur='DKINBOUND' WHERE rowid=${inbound_supplier_id}"
sql "UPDATE llx_facture_fourn_det SET fk_code_ventilation=${inbound_expense_account_id},vat_src_code='DKBUY25' WHERE fk_facture_fourn=${inbound_supplier_invoice_id}"
sql "DELETE FROM llx_c_tva WHERE entity=1 AND fk_pays=${dk_posting_country_id} AND code='DKBUY25'"
sql "INSERT INTO llx_c_tva (entity,fk_pays,code,type_vat,taux,note,active,accountancy_code_buy)
VALUES (1,${dk_posting_country_id},'DKBUY25',2,25,'Inbound posting test VAT',1,'4450')"
if [ "$(sql "SELECT COUNT(*) FROM llx_accounting_journal WHERE entity=1 AND nature=3 AND active=1")" = "0" ]; then
  sql "INSERT INTO llx_accounting_journal (entity,code,label,nature,active) VALUES (1,'KO','Purchases',3,1)"
fi

echo "Posting validated inbound supplier invoice as balanced immutable movement..."
docker compose exec -T dolibarr php /var/www/dkmodul-tests/assert-oioubl-inbound-supplier-posting.php
inbound_posting_rowid="$(sql "SELECT rowid FROM llx_dk_einvoice_inbound_posting WHERE entity=1 ORDER BY rowid DESC LIMIT 1")"
inbound_posting_bookkeeping_rowid="$(sql "SELECT rowid FROM llx_accounting_bookkeeping WHERE entity=1 AND doc_type='supplier_invoice' AND fk_doc=${inbound_supplier_invoice_id} ORDER BY rowid LIMIT 1")"
test -n "$inbound_posting_rowid"
test -n "$inbound_posting_bookkeeping_rowid"
expect_failure "UPDATE llx_accounting_bookkeeping SET debit=debit+1 WHERE rowid=${inbound_posting_bookkeeping_rowid}"
expect_failure "DELETE FROM llx_accounting_bookkeeping WHERE rowid=${inbound_posting_bookkeeping_rowid}"
expect_failure "UPDATE llx_dk_einvoice_inbound_posting SET line_count=99 WHERE rowid=${inbound_posting_rowid}"
expect_failure "DELETE FROM llx_dk_einvoice_inbound_posting WHERE rowid=${inbound_posting_rowid}"

echo "Configuring strict SAF-T mapping fixture on real Dolibarr database..."

set_const() {
  local name="$1"
  local value="$2"
  sql "DELETE FROM llx_const WHERE name='${name}' AND entity=1"
  sql "INSERT INTO llx_const (name,value,type,visible,note,entity) VALUES ('${name}','${value}','chaine',0,'Dolibarr DK integration test',1)"
}

set_const MAIN_INFO_SOCIETE_NOM "Dolibarr DK Test ApS"
set_const MAIN_INFO_SIREN "12345678"
set_const MAIN_INFO_SOCIETE_ADDRESS "Testvej 1"
set_const MAIN_INFO_SOCIETE_ZIP "8000"
set_const MAIN_INFO_SOCIETE_TOWN "Aarhus C"
set_const MAIN_INFO_SOCIETE_TEL "70112233"
set_const MAIN_INFO_SOCIETE_MAIL "test@example.invalid"
set_const MAIN_MONNAIE "DKK"

dk_country_id="$(sql "SELECT rowid FROM llx_c_country WHERE code='DK' LIMIT 1")"
test -n "$dk_country_id"

sql "DELETE FROM llx_bank_account WHERE ref='DKTEST'"
sql "INSERT INTO llx_bank_account
(ref,label,entity,fk_user_author,iban_prefix,bic,fk_pays,courant,clos,rappro,currency_code,account_number)
VALUES ('DKTEST','DK Test Bank',1,1,'DK5000400440116243','DABADKKK',${dk_country_id},1,0,1,'DKK','1000')"

sql "DELETE FROM llx_dk_account_mapping WHERE entity=1 AND source_account IN ('1000','3000','2600')"
sql "DELETE FROM llx_dk_standard_account WHERE standard_version='20260101' AND account_code IN ('1000','3000','2600')"
sql "INSERT INTO llx_dk_standard_account
(standard_version,valid_from,account_code,account_type,label,source_hash,date_imported)
VALUES
('20260101','2026-01-01','1000','Asset','Test bank account',REPEAT('0',64),NOW()),
('20260101','2026-01-01','3000','Sale','Test revenue account',REPEAT('0',64),NOW()),
('20260101','2026-01-01','2600','Liability','Test sales VAT account',REPEAT('0',64),NOW())"
sql "INSERT INTO llx_dk_account_mapping
(entity,source_account,standard_version,standard_account,valid_from,valid_to,fk_user_author,date_creation)
VALUES
(1,'1000','20260101','1000','2026-01-01',NULL,1,NOW()),
(1,'3000','20260101','3000','2026-01-01',NULL,1,NOW()),
(1,'2600','20260101','2600','2026-01-01',NULL,1,NOW())"

# Isolate the VAT catalogue so strict mapping can be proven deterministically.
sql "DELETE FROM llx_c_tva WHERE fk_pays=${dk_country_id}"
sql "INSERT INTO llx_c_tva
(entity,fk_pays,code,type_vat,taux,note,active)
VALUES (1,${dk_country_id},'DKTEST25',0,25,'Integration test sales VAT',1)"

echo "Importing official ERST VAT catalogue..."
rm -rf /tmp/erst-standard-filformater
git clone -q https://git.erst.dk/standard-filformater/standard-filformater.git /tmp/erst-standard-filformater
git -C /tmp/erst-standard-filformater checkout -q ea9a4b5704c7a0e9646b0d3b928a59089d71cf0e
docker compose cp "/tmp/erst-standard-filformater/Standardkontoplanen/JSON/2026-01-01-Momskoder-Bruttoliste.json" dolibarr:/tmp/erst-vat.json
docker compose exec -T dolibarr php /var/www/dkmodul-tests/import-official-vat-list.php /tmp/erst-vat.json

test "$(sql "SELECT new_tax_code FROM llx_dk_standard_vat_code WHERE standard_version='20260101' AND tax_code='S1'")" = "S01"
test "$(sql "SELECT DATE_FORMAT(valid_from,'%Y-%m-%d') FROM llx_dk_standard_vat_code WHERE standard_version='20260101' AND tax_code='S1'")" = "2025-12-01"

sql "DELETE FROM llx_dk_vat_mapping WHERE entity=1 AND source_tax_code='DKTEST25'"
sql "INSERT INTO llx_dk_vat_mapping
(entity,source_tax_code,standard_version,standard_tax_code,valid_from,valid_to,fk_user_author,date_creation)
VALUES (1,'DKTEST25','20260101','S1','2025-12-01',NULL,1,NOW())"

echo "Creating supplier, zero-rate and credit-note VAT provenance fixtures..."

purchase25_code="$(sql "SELECT tax_code FROM llx_dk_standard_vat_code WHERE standard_version='20260101' AND tax_percentage=25 AND (tax_group LIKE 'Køb%' OR tax_type LIKE 'Køb%') ORDER BY tax_code LIMIT 1")"
test -n "$purchase25_code"

sale0_code="$(sql "SELECT tax_code FROM llx_dk_standard_vat_code WHERE standard_version='20260101' AND tax_percentage=0 AND (tax_group LIKE 'Salg%' OR tax_type LIKE 'Salg%') ORDER BY tax_code LIMIT 1")"
test -n "$sale0_code"

sql "INSERT INTO llx_c_tva
(entity,fk_pays,code,type_vat,taux,note,active)
VALUES
(1,${dk_country_id},'DKBUY25',0,25,'Integration test purchase VAT',1),
(1,${dk_country_id},'DKSALE0',0,0,'Integration test zero-rate sales VAT',1)"

sql "DELETE FROM llx_dk_vat_mapping WHERE entity=1 AND source_tax_code IN ('DKBUY25','DKSALE0')"
sql "INSERT INTO llx_dk_vat_mapping
(entity,source_tax_code,standard_version,standard_tax_code,valid_from,valid_to,fk_user_author,date_creation)
VALUES
(1,'DKBUY25','20260101','${purchase25_code}','2025-12-01',NULL,1,NOW()),
(1,'DKSALE0','20260101','${sale0_code}','2025-12-01',NULL,1,NOW())"

pcg_version="$(sql "SELECT pcg_version FROM llx_accounting_system WHERE active=1 ORDER BY rowid LIMIT 1")"
test -n "$pcg_version"

for account in 2000 4000 4450; do
  existing="$(sql "SELECT rowid FROM llx_accounting_account WHERE entity=1 AND account_number='${account}' ORDER BY rowid LIMIT 1")"
  if [ -z "$existing" ]; then
    type="OTHER"
    label="Integration account ${account}"
    if [ "$account" = "4000" ]; then type="EXPENSE"; label="Purchases"; fi
    if [ "$account" = "2000" ]; then type="LIABILITY"; label="Supplier payable"; fi
    if [ "$account" = "4450" ]; then type="ASSET"; label="Purchase VAT"; fi
    sql "INSERT INTO llx_accounting_account
    (entity,datec,fk_pcg_version,pcg_type,account_number,label,fk_user_author,active)
    VALUES (1,NOW(),'${pcg_version}','${type}','${account}','${label}',1,1)"
  fi
done

expense_account_rowid="$(sql "SELECT rowid FROM llx_accounting_account WHERE entity=1 AND account_number='4000' ORDER BY rowid LIMIT 1")"
test -n "$expense_account_rowid"

sql "INSERT INTO llx_societe
(nom,entity,status,code_fournisseur,fk_pays,fk_stcomm,client,fournisseur,datec)
VALUES ('DK VAT Supplier',1,1,'DKVATSUP',${dk_country_id},0,0,1,NOW())"
supplier_id="$(sql "SELECT rowid FROM llx_societe WHERE code_fournisseur='DKVATSUP' ORDER BY rowid DESC LIMIT 1")"
test -n "$supplier_id"

sql "INSERT INTO llx_facture_fourn
(ref,ref_supplier,entity,type,fk_soc,datec,datef,total_tva,total_ht,total_ttc,fk_statut,fk_user_author)
VALUES ('DKSUP-1','SUP-1',1,0,${supplier_id},NOW(),'2026-09-18',50.00,200.00,250.00,1,1)"
supplier_invoice_id="$(sql "SELECT rowid FROM llx_facture_fourn WHERE ref='DKSUP-1' AND entity=1 ORDER BY rowid DESC LIMIT 1")"

sql "INSERT INTO llx_facture_fourn_det
(fk_facture_fourn,description,vat_src_code,tva_tx,qty,pu_ht,total_ht,tva,total_ttc,product_type,fk_code_ventilation)
VALUES (${supplier_invoice_id},'Supplier VAT test','DKBUY25',25,1,200.00,200.00,50.00,250.00,0,${expense_account_rowid})"

sql "INSERT INTO llx_accounting_bookkeeping
(entity,ref,piece_num,doc_date,doc_type,doc_ref,fk_doc,fk_docdet,thirdparty_code,subledger_account,subledger_label,numero_compte,label_compte,label_operation,debit,credit,fk_user_author,date_creation,code_journal,journal_label,date_validated)
VALUES
(1,'DK-990004',990004,'2026-09-18','supplier_invoice','SUP-1',${supplier_invoice_id},0,'DKVATSUP','DKVATSUP','DK VAT Supplier','4000','Purchases','Supplier purchase',200.00,0.00,1,NOW(),'KO','Purchases',NOW()),
(1,'DK-990004',990004,'2026-09-18','supplier_invoice','SUP-1',${supplier_invoice_id},0,'DKVATSUP','','','4450','Purchase VAT','Purchase VAT',50.00,0.00,1,NOW(),'KO','Purchases',NOW()),
(1,'DK-990004',990004,'2026-09-18','supplier_invoice','SUP-1',${supplier_invoice_id},0,'DKVATSUP','DKVATSUP','DK VAT Supplier','2000','Supplier payable','Supplier payable',0.00,250.00,1,NOW(),'KO','Purchases',NOW())"

sql "INSERT INTO llx_facture
(ref,entity,type,fk_soc,datec,datef,total_tva,total_ht,total_ttc,fk_statut,fk_user_author,fk_cond_reglement)
VALUES ('DKZERO-1',1,0,${customer_id},NOW(),'2026-09-18',0.00,100.00,100.00,1,1,1)"
zero_invoice_id="$(sql "SELECT rowid FROM llx_facture WHERE ref='DKZERO-1' AND entity=1 ORDER BY rowid DESC LIMIT 1")"

sql "INSERT INTO llx_facturedet
(fk_facture,label,description,vat_src_code,tva_tx,qty,subprice,total_ht,total_tva,total_ttc,product_type,fk_code_ventilation)
VALUES (${zero_invoice_id},'Zero VAT sale','Zero VAT sale','DKSALE0',0,1,100.00,100.00,0.00,100.00,0,${revenue_account_rowid})"

sql "INSERT INTO llx_accounting_bookkeeping
(entity,ref,piece_num,doc_date,doc_type,doc_ref,fk_doc,fk_docdet,thirdparty_code,subledger_account,subledger_label,numero_compte,label_compte,label_operation,debit,credit,fk_user_author,date_creation,code_journal,journal_label,date_validated)
VALUES
(1,'DK-990005',990005,'2026-09-18','customer_invoice','DKZERO-1',${zero_invoice_id},0,'DKVATCUST','DKVATCUST','DK VAT Customer','1000','Receivables','Zero-rate receivable',100.00,0.00,1,NOW(),'VT','Sales',NOW()),
(1,'DK-990005',990005,'2026-09-18','customer_invoice','DKZERO-1',${zero_invoice_id},0,'DKVATCUST','','','3000','Revenue','Zero-rate sale',0.00,100.00,1,NOW(),'VT','Sales',NOW())"

sql "INSERT INTO llx_facture
(ref,entity,type,fk_soc,datec,datef,total_tva,total_ht,total_ttc,fk_statut,fk_user_author,fk_cond_reglement)
VALUES ('DKCREDIT-1',1,2,${customer_id},NOW(),'2026-09-18',-25.00,-100.00,-125.00,1,1,1)"
credit_invoice_id="$(sql "SELECT rowid FROM llx_facture WHERE ref='DKCREDIT-1' AND entity=1 ORDER BY rowid DESC LIMIT 1")"

sql "INSERT INTO llx_facturedet
(fk_facture,label,description,vat_src_code,tva_tx,qty,subprice,total_ht,total_tva,total_ttc,product_type,fk_code_ventilation)
VALUES (${credit_invoice_id},'Credit note VAT','Credit note VAT','DKTEST25',25,1,-100.00,-100.00,-25.00,-125.00,0,${revenue_account_rowid})"

sql "INSERT INTO llx_accounting_bookkeeping
(entity,ref,piece_num,doc_date,doc_type,doc_ref,fk_doc,fk_docdet,thirdparty_code,subledger_account,subledger_label,numero_compte,label_compte,label_operation,debit,credit,fk_user_author,date_creation,code_journal,journal_label,date_validated)
VALUES
(1,'DK-990006',990006,'2026-09-18','customer_invoice','DKCREDIT-1',${credit_invoice_id},0,'DKVATCUST','DKVATCUST','DK VAT Customer','1000','Receivables','Credit-note receivable',0.00,125.00,1,NOW(),'VT','Sales',NOW()),
(1,'DK-990006',990006,'2026-09-18','customer_invoice','DKCREDIT-1',${credit_invoice_id},0,'DKVATCUST','','','3000','Revenue','Credit-note revenue',100.00,0.00,1,NOW(),'VT','Sales',NOW()),
(1,'DK-990006',990006,'2026-09-18','customer_invoice','DKCREDIT-1',${credit_invoice_id},0,'DKVATCUST','','','2600','Sales VAT','Credit-note VAT',25.00,0.00,1,NOW(),'VT','Sales',NOW())"

docker compose exec -T dolibarr php /var/www/dkmodul-tests/assert-vat-edge-cases.php

sql "DELETE FROM llx_dk_account_mapping WHERE entity=1 AND source_account IN ('2000','4000','4450')"
sql "DELETE FROM llx_dk_standard_account WHERE standard_version='20260101' AND account_code IN ('2000','4000','4450')"
sql "INSERT INTO llx_dk_standard_account
(standard_version,valid_from,account_code,account_type,label,source_hash,date_imported)
VALUES
('20260101','2026-01-01','2000','Liability','Test supplier payable',REPEAT('0',64),NOW()),
('20260101','2026-01-01','4000','Expense','Test purchase expense',REPEAT('0',64),NOW()),
('20260101','2026-01-01','4450','Asset','Test purchase VAT',REPEAT('0',64),NOW())"
sql "INSERT INTO llx_dk_account_mapping
(entity,source_account,standard_version,standard_account,valid_from,valid_to,fk_user_author,date_creation)
VALUES
(1,'2000','20260101','2000','2026-01-01',NULL,1,NOW()),
(1,'4000','20260101','4000','2026-01-01',NULL,1,NOW()),
(1,'4450','20260101','4450','2026-01-01',NULL,1,NOW())"

echo "Generating strict SAF-T 2.1 from Dolibarr provider..."
docker compose exec -T dolibarr php /var/www/dkmodul-tests/assert-saft21-dolibarr-provider.php /tmp/dolibarr-dk-saft21.xml

echo "Validating Dolibarr-generated SAF-T against pinned official ERST XSD..."
docker compose cp "/tmp/erst-standard-filformater/SAF-T/XSD/Danish_SAF-T_Financial_Schema_v_2_1.xsd" dolibarr:/tmp/saft21.xsd

docker compose exec -T dolibarr php -r '
require "/var/www/html/custom/dkmodul/class/Saft/SaftValidator.php";
$validator = new DkSaftValidator();
$validator->validateXml(file_get_contents("/tmp/dolibarr-dk-saft21.xml"), "/tmp/saft21.xsd");
echo "Real Dolibarr SAF-T 2.1 validates against official ERST XSD\n";
'

echo "Preparing local accounts for SAF-T import mapping analysis..."
pcg_version="$(sql "SELECT fk_pcg_version FROM llx_accounting_account WHERE entity=1 AND account_number='3000' ORDER BY rowid LIMIT 1")"
test -n "$pcg_version"

if [ "$(sql "SELECT COUNT(*) FROM llx_accounting_account WHERE entity=1 AND account_number='1000' AND active=1")" = "0" ]; then
  sql "INSERT INTO llx_accounting_account
  (entity,datec,fk_pcg_version,pcg_type,account_number,label,fk_user_author,active)
  VALUES (1,NOW(),'${pcg_version}','ASSET','1000','Imported test asset',1,1)"
fi

if [ "$(sql "SELECT COUNT(*) FROM llx_accounting_account WHERE entity=1 AND account_number='2600' AND active=1")" = "0" ]; then
  sql "INSERT INTO llx_accounting_account
  (entity,datec,fk_pcg_version,pcg_type,account_number,label,fk_user_author,active)
  VALUES (1,NOW(),'${pcg_version}','LIABILITY','2600','Imported test VAT liability',1,1)"
fi

echo "Preparing active 2026 fiscal period for SAF-T Apply..."
sql "DELETE FROM llx_accounting_fiscalyear WHERE entity=1 AND label='DKSAFT-2026'"
sql "INSERT INTO llx_accounting_fiscalyear
(label,date_start,date_end,statut,entity,datec,fk_user_author)
VALUES ('DKSAFT-2026','2026-01-01','2026-12-31',0,1,NOW(),1)"

echo "Creating controlled correction reversal..."
docker compose exec -T dolibarr php /var/www/dkmodul-tests/assert-correction-workflow.php

echo "Proving DK-ACC-001-005 through the Dolibarr bookkeeping API..."
docker compose exec -T dolibarr php /var/www/dkmodul-tests/assert-core-bookkeeping-evidence.php

echo "Staging and analyzing generated SAF-T 2.1 import..."
docker compose exec -T dolibarr php /var/www/dkmodul-tests/assert-saft21-import-staging.php \
  /tmp/dolibarr-dk-saft21.xml /tmp/saft21.xsd

echo "Verifying append-only audit chain..."
audit_rowid="$(sql "SELECT rowid FROM llx_dk_audit_event WHERE entity=1 ORDER BY rowid LIMIT 1")"
test -n "$audit_rowid"
expect_failure "UPDATE llx_dk_audit_event SET payload_json='{}' WHERE rowid=${audit_rowid}"
expect_failure "DELETE FROM llx_dk_audit_event WHERE rowid=${audit_rowid}"

last_previous_hash="$(sql "SELECT previous_hash FROM llx_dk_audit_event WHERE entity=1 ORDER BY rowid DESC LIMIT 1")"
if sql "INSERT INTO llx_dk_audit_event
(entity,event_uuid,event_type,object_type,object_id,actor_id,created_at,previous_hash,payload_hash,event_hash,payload_json,metadata_json)
VALUES (1,UUID(),'dk.audit.fork-test','audit',0,1,NOW(),'${last_previous_hash}',REPEAT('0',64),REPEAT('1',64),'{}','{}')" >/tmp/dkmodul-audit-fork.log 2>&1; then
  echo "Audit ledger accepted a duplicate chain predecessor"
  exit 1
fi

docker compose exec -T dolibarr php /var/www/dkmodul-tests/assert-audit-ledger.php

echo "Dolibarr DK accounting + SAF-T export/import staging integration tests passed"
