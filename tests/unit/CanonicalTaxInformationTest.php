<?php

require_once __DIR__.'/../../dkmodul/class/Accounting/Canonical/TaxInformation.php';

$tax = new DkCanonicalTaxInformation(array(
    'taxCode' => 'DK25',
    'taxPercentage' => '25',
    'taxBase' => '100.00',
    'taxAmount' => '25.00',
    'countryCode' => 'DK',
));

assert($tax->taxType === 'VAT');
assert($tax->taxCode === 'DK25');
assert($tax->taxPercentage === '25.00000000');
assert($tax->taxBase === '100.00000000');
assert($tax->taxAmount === '25.00000000');

$failed = false;
try {
    new DkCanonicalTaxInformation(array('taxCode' => ''));
} catch (InvalidArgumentException $e) {
    $failed = true;
}
assert($failed === true);

echo "Canonical tax information tests passed\n";
