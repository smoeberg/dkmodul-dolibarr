<?php

require_once __DIR__.'/../../dkmodul/class/Accounting/Canonical/Transaction.php';
require_once __DIR__.'/../../dkmodul/class/Accounting/Canonical/CompanyContext.php';
require_once __DIR__.'/../../dkmodul/class/Accounting/Canonical/Account.php';
require_once __DIR__.'/../../dkmodul/class/Accounting/Canonical/Party.php';
require_once __DIR__.'/../../dkmodul/class/Accounting/Canonical/TaxCode.php';

assert(DkCanonicalDecimal::normalize('1.2') === '1.20000000');
assert(DkCanonicalDecimal::add('0.10', '0.20') === '0.30000000');
assert(DkCanonicalDecimal::add('1000000000000000.12345678', '0.87654322') === '1000000000000001.00000000');
assert(DkCanonicalDecimal::add('-1.25', '0.25') === '-1.00000000');
assert(DkCanonicalDecimal::add('9999999999999999.99999999', '-0.00000001') === '9999999999999999.99999998');

$transaction = new DkCanonicalTransaction(array(
    'transactionId' => '1001',
    'journalCode' => 'VT',
    'transactionDate' => '2026-09-18',
    'registrationDateTime' => '2026-09-18T10:00:00+02:00',
    'actor' => 'user:1',
    'documentRef' => 'INV-1001',
    'lines' => array(
        array('lineId' => '1', 'accountCode' => '1000', 'debit' => '125.50', 'credit' => '0'),
        array('lineId' => '2', 'accountCode' => '3000', 'debit' => '0', 'credit' => '125.50'),
    ),
));
assert($transaction->validate() === true);

$company = new DkCanonicalCompanyContext(array(
    'id' => '1',
    'name' => 'Test ApS',
    'registrationNumber' => '12345678',
    'currencyCode' => 'DKK',
    'address' => array(
        'streetName' => 'Testvej 1',
        'postalCode' => '8000',
        'city' => 'Aarhus C',
        'countryCode' => 'DK',
    ),
    'bankAccounts' => array(array('iban' => 'DK5000400440116243')),
));
$account = new DkCanonicalAccount(array(
    'accountCode' => '1000',
    'label' => 'Bank',
    'openingBalance' => '1.2',
    'closingBalance' => '3.4',
));
$party = new DkCanonicalParty(array('partyId' => 'C1', 'label' => 'Customer'));
$taxCode = new DkCanonicalTaxCode(array(
    'taxCode' => 'S25',
    'description' => 'Sales VAT',
    'taxPercentage' => '25',
));

assert($company['currencyCode'] === 'DKK');
assert($account['openingBalance'] === '1.20000000');
assert($party['partyId'] === 'C1');
assert($taxCode['taxPercentage'] === '25.00000000');

$recordMutationRejected = false;
try {
    $account['label'] = 'Changed';
} catch (LogicException $e) {
    $recordMutationRejected = true;
}
assert($recordMutationRejected === true);
assert($account['label'] === 'Bank');

$transactionMutationRejected = false;
try {
    $transaction->actor = 'user:2';
} catch (Error $e) {
    $transactionMutationRejected = true;
}
assert($transactionMutationRejected === true);
assert($transaction->actor === 'user:1');

$unbalanced = false;
try {
    new DkCanonicalTransaction(array(
        'transactionId' => '1002',
        'journalCode' => 'VT',
        'transactionDate' => '2026-09-18',
        'registrationDateTime' => '2026-09-18T10:00:00+02:00',
        'actor' => 'user:1',
        'lines' => array(
            array('lineId' => '1', 'accountCode' => '1000', 'debit' => '10', 'credit' => '0'),
            array('lineId' => '2', 'accountCode' => '3000', 'debit' => '0', 'credit' => '9'),
        ),
    ));
} catch (InvalidArgumentException $e) {
    $unbalanced = true;
}
assert($unbalanced === true);

$bothSides = false;
try {
    new DkCanonicalLine(array('lineId' => '1', 'accountCode' => '1000', 'debit' => '1', 'credit' => '1'));
} catch (InvalidArgumentException $e) {
    $bothSides = true;
}
assert($bothSides === true);

echo "Canonical accounting model tests passed\n";
