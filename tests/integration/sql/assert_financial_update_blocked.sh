#!/usr/bin/env bash
set -euo pipefail

if mariadb "$@" -e "UPDATE llx_accounting_bookkeeping SET debit=999, montant=999 WHERE rowid=1"; then
  echo "ERROR: validated financial UPDATE unexpectedly succeeded"
  exit 1
fi

echo "validated financial UPDATE correctly blocked"
