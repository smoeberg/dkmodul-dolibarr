<?php

define('NOLOGIN', 1);
define('NOREQUIREMENU', 1);
define('NOREQUIREHTML', 1);

require '/var/www/html/main.inc.php';
require '/var/www/html/custom/dkmodul/class/Accounting/DolibarrAccountingDataProvider.php';

$provider = new DkDolibarrAccountingDataProvider($db, 1);
$transactions = $provider->getTransactions('2026-01-01', '2030-12-31');

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

echo "Dolibarr VAT provenance provider integration test passed\n";
