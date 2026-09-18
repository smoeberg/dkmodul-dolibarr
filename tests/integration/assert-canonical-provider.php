<?php

define('NOLOGIN', 1);
define('NOREQUIREMENU', 1);
define('NOREQUIREHTML', 1);

require '/var/www/html/main.inc.php';
require '/var/www/html/custom/dkmodul/class/Accounting/DolibarrAccountingDataProvider.php';

$provider = new DkDolibarrAccountingDataProvider($db, 1);
$transactions = $provider->getTransactions('2026-01-01', '2030-12-31');

$found = null;
foreach ($transactions as $transaction) {
    if ($transaction->sourcePieceNumber === 990002) {
        $found = $transaction;
        break;
    }
}

if (!$found) {
    fwrite(STDERR, "Canonical test transaction not found\n");
    exit(1);
}

if ($found->journalCode !== 'OD') {
    fwrite(STDERR, "Unexpected canonical journal\n");
    exit(1);
}

if ($found->actor !== 'user:1') {
    fwrite(STDERR, "Unexpected canonical actor\n");
    exit(1);
}

if (count($found->lines) !== 2) {
    fwrite(STDERR, "Unexpected canonical line count\n");
    exit(1);
}

if (!DkCanonicalDecimal::equals($found->lines[0]->debit, '250.00')) {
    fwrite(STDERR, "Unexpected canonical debit amount\n");
    exit(1);
}

if (!DkCanonicalDecimal::equals($found->lines[1]->credit, '250.00')) {
    fwrite(STDERR, "Unexpected canonical credit amount\n");
    exit(1);
}

if ($found->validatedAt === null) {
    fwrite(STDERR, "Canonical validation timestamp missing\n");
    exit(1);
}

echo "Dolibarr canonical accounting provider integration test passed\n";
