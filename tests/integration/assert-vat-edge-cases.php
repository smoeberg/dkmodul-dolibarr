<?php

define('NOLOGIN', 1);
define('NOREQUIREMENU', 1);
define('NOREQUIREHTML', 1);

require '/var/www/html/main.inc.php';
require '/var/www/html/custom/dkmodul/class/Accounting/DolibarrAccountingDataProvider.php';

$provider = new DkDolibarrAccountingDataProvider($db, 1);
$transactions = $provider->getTransactions('2026-01-01', '2026-12-31');

$byPiece = array();
foreach ($transactions as $transaction) {
    if ($transaction->sourcePieceNumber !== null) {
        $byPiece[(int) $transaction->sourcePieceNumber] = $transaction;
    }
}

foreach (array(990004, 990005, 990006) as $piece) {
    if (!isset($byPiece[$piece])) {
        fwrite(STDERR, "Expected VAT edge-case transaction {$piece} not found\n");
        exit(1);
    }
}

$findLine = static function ($transaction, $account) {
    foreach ($transaction->lines as $line) {
        if ($line->accountCode === $account) {
            return $line;
        }
    }
    return null;
};

$supplierLine = $findLine($byPiece[990004], '4000');
if ($supplierLine === null || count($supplierLine->taxInformation) !== 1) {
    fwrite(STDERR, "Supplier expense line VAT provenance missing\n");
    exit(1);
}
$supplierTax = $supplierLine->taxInformation[0];
if ($supplierTax->taxCode !== 'DKBUY25'
    || !DkCanonicalDecimal::equals($supplierTax->taxPercentage, '25')
    || !DkCanonicalDecimal::equals($supplierTax->taxBase, '200')
    || !DkCanonicalDecimal::equals($supplierTax->taxAmount, '50')) {
    fwrite(STDERR, "Unexpected supplier VAT provenance\n");
    exit(1);
}

foreach (array('2000', '4450') as $account) {
    $line = $findLine($byPiece[990004], $account);
    if ($line === null || count($line->taxInformation) !== 0) {
        fwrite(STDERR, "Supplier control line {$account} must not receive source VAT provenance\n");
        exit(1);
    }
}

$zeroLine = $findLine($byPiece[990005], '3000');
if ($zeroLine === null || count($zeroLine->taxInformation) !== 1) {
    fwrite(STDERR, "Zero-rate revenue VAT provenance missing\n");
    exit(1);
}
$zeroTax = $zeroLine->taxInformation[0];
if ($zeroTax->taxCode !== 'DKSALE0'
    || !DkCanonicalDecimal::equals($zeroTax->taxPercentage, '0')
    || !DkCanonicalDecimal::equals($zeroTax->taxBase, '100')
    || !DkCanonicalDecimal::equals($zeroTax->taxAmount, '0')) {
    fwrite(STDERR, "Unexpected zero-rate VAT provenance\n");
    exit(1);
}

$creditLine = $findLine($byPiece[990006], '3000');
if ($creditLine === null || count($creditLine->taxInformation) !== 1) {
    fwrite(STDERR, "Credit-note revenue VAT provenance missing\n");
    exit(1);
}
$creditTax = $creditLine->taxInformation[0];
if ($creditTax->taxCode !== 'DKTEST25'
    || !DkCanonicalDecimal::equals($creditTax->taxPercentage, '25')
    || !DkCanonicalDecimal::equals($creditTax->taxBase, '-100')
    || !DkCanonicalDecimal::equals($creditTax->taxAmount, '-25')) {
    fwrite(STDERR, "Unexpected credit-note VAT sign handling\n");
    exit(1);
}

echo "Supplier, zero-rate and credit-note VAT provenance tests passed\n";
