#!/usr/bin/env bash
set -euo pipefail

workdir="${1:-/tmp/erst-standard-filformater}"
rm -rf "$workdir"

ERST_COMMIT="ea9a4b5704c7a0e9646b0d3b928a59089d71cf0e"
git clone https://git.erst.dk/standard-filformater/standard-filformater.git "$workdir"
cd "$workdir"
git checkout "$ERST_COMMIT"

test "$(git rev-parse HEAD)" = "$ERST_COMMIT"

echo "ERST_COMMIT=$(git rev-parse HEAD)"
echo "ERST_COMMIT_DATE=$(git show -s --format=%cI HEAD)"

schema="SAF-T/XSD/Danish_SAF-T_Financial_Schema_v_2_1.xsd"
example="SAF-T/XML_examples/SAF-T v. 2.1.xml"

test -f "$schema"
test -f "$example"

echo "=== SAF-T 2.1 namespace/root ==="
grep -E 'targetNamespace=|<xs:element name="AuditFile"' "$schema" | head -10

echo "=== Header example ==="
sed -n '1,220p' "SAF-T/XML_examples/Header.xml"

echo "=== MasterFiles example (first 320 lines) ==="
sed -n '1,320p' "SAF-T/XML_examples/MasterFiles.xml"

echo "=== GeneralLedgerEntries example (first 420 lines) ==="
sed -n '1,420p' "SAF-T/XML_examples/GeneralLedgerEntries.xml"

print_schema_range() {
  local element="$1"
  local lines="$2"
  local start
  start="$(grep -n -m1 "<xs:element name=\"$element\"" "$schema" | cut -d: -f1 || true)"
  if [ -n "$start" ]; then
    echo "=== XSD $element from line $start ==="
    sed -n "${start},$((start + lines))p" "$schema"
  fi
}

print_schema_range "Header" 220
print_schema_range "MasterFiles" 220
print_schema_range "GeneralLedgerEntries" 300

echo "=== Full 2.1 example structural tags ==="
grep -E '<(/)?(AuditFile|Header|MasterFiles|GeneralLedgerAccounts|Account|GeneralLedgerEntries|Journal|Transaction|Line|TaxInformation|SourceDocuments)([ >])' "$example" | head -250 || true

echo "=== 2.1 schema required top-level sections ==="
grep -n -E '<xs:element name="(Header|MasterFiles|GeneralLedgerEntries|SourceDocuments)"' "$schema" | head -30 || true


print_named_type() {
  local kind="$1"
  local name="$2"
  local lines="$3"
  local start
  start="$(grep -n -m1 "<xs:${kind} name=\"${name}\"" "$schema" | cut -d: -f1 || true)"
  if [ -n "$start" ]; then
    echo "=== XSD ${kind} ${name} from line ${start} ==="
    sed -n "${start},$((start + lines))p" "$schema"
  fi
}

print_named_type "complexType" "HeaderStructure" 180
print_named_type "group" "CompanyStructureContent" 180
print_named_type "complexType" "SelectionCriteriaStructure" 120
print_named_type "complexType" "AmountStructure" 120

print_named_type "complexType" "AddressStructure" 120
print_named_type "complexType" "CompanyHeaderStructure" 140


vat_json="Standardkontoplanen/JSON/2026-01-01-Momskoder-Bruttoliste.json"
test -f "$vat_json"

echo "=== VAT JSON structure ==="
python3 - "$vat_json" <<'PY'
import json
import sys

path = sys.argv[1]
with open(path, encoding="utf-8-sig") as fh:
    data = json.load(fh)

print("top_type=" + type(data).__name__)
if isinstance(data, dict):
    print("top_keys=" + repr(list(data.keys())))
    for key, value in data.items():
        print(f"section={key!r} type={type(value).__name__}")
        if isinstance(value, list):
            print("count=" + str(len(value)))
            for item in value[:3]:
                print("sample=" + json.dumps(item, ensure_ascii=False, sort_keys=True))
        elif isinstance(value, dict):
            print("value=" + json.dumps(value, ensure_ascii=False, sort_keys=True))
elif isinstance(data, list):
    print("count=" + str(len(data)))
    for item in data[:3]:
        print("sample=" + json.dumps(item, ensure_ascii=False, sort_keys=True))
PY


print_named_type "complexType" "TaxInformationStructure" 180
