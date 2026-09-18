#!/usr/bin/env bash
set -euo pipefail

if mariadb "$@" -e "DELETE FROM llx_accounting_bookkeeping WHERE rowid=1"; then
  echo "ERROR: validated DELETE unexpectedly succeeded"
  exit 1
fi

echo "validated DELETE correctly blocked"
