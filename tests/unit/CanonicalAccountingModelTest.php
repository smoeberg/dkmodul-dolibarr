<?php

require_once __DIR__.'/../../dkmodul/class/Accounting/Canonical/Transaction.php';

assert(DkCanonicalDecimal::normalize('1.2') === '1.20000000');
assert(DkCanonicalDecimal::add('0.10', '0.20') === '0.30000000');
assert(DkCanonicalDecimal::add('1000000000000000.12345678', '0.87654322') === '1000000000000001.00000000');
assert(DkCanonicalDecimal::add('-1.25', '0.25') === '-1.00000000');

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
