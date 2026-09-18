<?php

define('NOLOGIN', 1);
define('NOREQUIREMENU', 1);
define('NOREQUIREHTML', 1);

require '/var/www/html/main.inc.php';
require '/var/www/html/custom/dkmodul/class/Accounting/DolibarrAccountingDataProvider.php';

$provider = new DkDolibarrAccountingDataProvider($db, 1);
$transactions = $provider->getTransactions('2026-01-01', '2026-12-31');

$target = null;
foreach ($transactions as $transaction) {
    if ($transaction->sourcePieceNumber === 990003) {
        $target = $transaction;
        break;
    }
}

if ($target === null) {
    fwrite(STDERR, "VAT provenance transaction not found\n");
    exit(1);
}

$byAccount = array();
foreach ($target->lines as $line) {
    $byAccount[$line->accountCode] = $line;
}

if (!isset($byAccount['3000'])) {
    fwrite(STDERR, "Revenue line 3000 not found\n");
    exit(1);
}

if (count($byAccount['3000']->taxInformation) !== 1) {
    fwrite(STDERR, "Revenue line must have exactly one VAT provenance item\n");
    exit(1);
}

$tax = $byAccount['3000']->taxInformation[0];

$checks = array(
    'taxCode' => 'DKTEST25',
    'taxPercentage' => '25.00000000',
    'taxBase' => '250.00000000',
    'taxAmount' => '62.50000000',
);

foreach ($checks as $field => $expected) {
    if ((string) $tax->{$field} !== $expected) {
        fwrite(STDERR, "Unexpected {$field}: ".$tax->{$field}."\n");
        exit(1);
    }
}

foreach (array('1000', '2600') as $account) {
    if (!isset($byAccount[$account])) {
        fwrite(STDERR, "Expected account {$account} missing\n");
        exit(1);
    }
    if (count($byAccount[$account]->taxInformation) !== 0) {
        fwrite(STDERR, "Account {$account} must not receive source VAT provenance\n");
        exit(1);
    }
}

echo "Dolibarr invoice VAT provenance mapped to canonical revenue line\n";
