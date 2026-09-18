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

$vatTransaction = null;
foreach ($transactions as $transaction) {
    if ($transaction->sourcePieceNumber === 990003) {
        $vatTransaction = $transaction;
        break;
    }
}

if (!$vatTransaction) {
    fwrite(STDERR, "VAT provenance transaction not found\n");
    exit(1);
}

$revenueLine = null;
foreach ($vatTransaction->lines as $line) {
    if ($line->accountCode === '3999') {
        $revenueLine = $line;
        break;
    }
}

if (!$revenueLine) {
    fwrite(STDERR, "VAT provenance revenue line not found\n");
    exit(1);
}

if (count($revenueLine->taxComponents) !== 2) {
    fwrite(STDERR, "Expected two VAT provenance components on aggregated revenue line\n");
    exit(1);
}

$components = array();
foreach ($revenueLine->taxComponents as $component) {
    $components[$component['taxCode']] = $component;
}

foreach (array('DKT25A' => array('200', '50'), 'DKT25B' => array('100', '25')) as $code => $expected) {
    if (!isset($components[$code])) {
        fwrite(STDERR, "Missing VAT component ".$code."\n");
        exit(1);
    }
    if (!DkCanonicalDecimal::equals($components[$code]['taxBase'], $expected[0])) {
        fwrite(STDERR, "Unexpected VAT base for ".$code."\n");
        exit(1);
    }
    if (!DkCanonicalDecimal::equals($components[$code]['taxAmount'], $expected[1])) {
        fwrite(STDERR, "Unexpected VAT amount for ".$code."\n");
        exit(1);
    }
}

echo "Dolibarr canonical accounting provider integration test passed\n";
