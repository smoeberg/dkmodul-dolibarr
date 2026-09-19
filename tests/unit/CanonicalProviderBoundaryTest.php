<?php

require_once __DIR__.'/../../dkmodul/class/Saft/V21/Saft21Exporter.php';

class DkNonCanonicalProviderFixture implements DkAccountingDataProviderInterface
{
    public function getCompanyContext()
    {
        return array('id' => 'legacy-array');
    }

    public function getAccounts($fromDate, $toDate)
    {
        return array();
    }

    public function getParties($fromDate, $toDate)
    {
        return array();
    }

    public function getTaxCodes($fromDate, $toDate)
    {
        return array();
    }

    public function getTransactions($fromDate, $toDate)
    {
        return array();
    }
}

$rejected = false;
try {
    (new DkSaft21Exporter(new DkNonCanonicalProviderFixture()))->export(
        '2026-01-01',
        '2026-12-31',
        array('createdDate' => '2026-12-31')
    );
} catch (UnexpectedValueException $e) {
    $rejected = str_contains($e->getMessage(), 'canonical company context');
}

assert($rejected === true);

echo "Canonical provider boundary tests passed\n";
