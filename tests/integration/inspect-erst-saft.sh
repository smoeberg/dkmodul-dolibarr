#!/usr/bin/env bash
set -euo pipefail

workdir="${1:-/tmp/erst-standard-filformater}"
rm -rf "$workdir"

git clone --depth 1 https://git.erst.dk/standard-filformater/standard-filformater.git "$workdir"

cd "$workdir"

echo "ERST_COMMIT=$(git rev-parse HEAD)"
echo "ERST_COMMIT_DATE=$(git show -s --format=%cI HEAD)"

echo "=== SAF-T/XSD files ==="
find SAF-T/XSD -maxdepth 1 -type f -printf '%f\n' | sort

echo "=== version markers in XSD filenames/content ==="
for file in SAF-T/XSD/*; do
  [ -f "$file" ] || continue
  echo "--- $file"
  grep -Eo 'version="[0-9.]+"|Version[^<]{0,40}[0-9]+\.[0-9]+|v_[0-9]+_[0-9]+' "$file" | head -20 || true
done

echo "=== top-level SAF-T element names ==="
for file in SAF-T/XSD/*.xml SAF-T/XSD/*.xsd; do
  [ -f "$file" ] || continue
  echo "--- $file"
  grep -E '<(xs|xsd):element name="(AuditFile|Header|MasterFiles|GeneralLedgerEntries|SourceDocuments)"' "$file" | head -20 || true
done

echo "=== SAF-T changelog candidates ==="
find SAF-T -maxdepth 2 -type f \( -iname '*change*' -o -iname '*readme*' -o -iname '*version*' \) -print | sort

echo "=== XML examples ==="
find SAF-T/XML_examples -maxdepth 1 -type f -printf '%f\n' 2>/dev/null | sort || true
