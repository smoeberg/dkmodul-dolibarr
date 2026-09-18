<?php

require_once __DIR__.'/../../dkmodul/class/Accounting/Canonical/Line.php';

$line = new DkCanonicalLine(array(
    'lineId' => '1',
    'accountCode' => '1010',
    'debit' => '0',
    'credit' => '300',
    'taxComponents' => array(
        array(
            'taxCode' => 'S1',
            'taxPercentage' => '25',
            'taxBase' => '200',
            'taxAmount' => '50',
            'taxBaseDescription' => 'Component 1',
        ),
        array(
            'taxCode' => 'S0',
            'taxPercentage' => '0',
            'taxBase' => '100',
            'taxAmount' => '0',
            'taxBaseDescription' => 'Component 2',
        ),
    ),
));

assert(count($line->taxComponents) === 2);
assert($line->taxComponents[0]['taxCode'] === 'S1');
assert($line->taxComponents[0]['taxBase'] === '200.00000000');
assert($line->taxComponents[0]['taxAmount'] === '50.00000000');
assert($line->taxComponents[1]['taxPercentage'] === '0.00000000');

echo "VAT provenance canonical contract tests passed\n";
