<?php

require_once __DIR__.'/../../dkmodul/class/Saft/V21/Saft21Importer.php';

if ($argc < 3) {
    fwrite(STDERR, "Usage: php validate-saft21-import.php /path/schema.xsd /path/example.xml\n");
    exit(2);
}

$xml = file_get_contents($argv[2]);
if ($xml === false) {
    fwrite(STDERR, "Unable to read official SAF-T example\n");
    exit(1);
}

$importer = new DkSaft21Importer();

/*
 * The official ERST 2.1 example is XSD-valid and structurally parseable, but
 * its declared TotalDebit is 61100 while its 13 GL debit lines sum to 61110.
 * Production import must remain strict and reject that accounting mismatch.
 *
 * We therefore parse the upstream example once in diagnostic mode to prove
 * schema/structure compatibility, then explicitly prove strict mode rejects
 * the inconsistent accounting totals.
 */
$package = $importer->importXml($xml, $argv[1], false);

if ($package->header['auditFileVersion'] !== '2.1') {
    fwrite(STDERR, "Unexpected official SAF-T version\n");
    exit(1);
}
if ($package->lineCount() < 1 || count($package->transactions) < 1 || count($package->accounts) < 1) {
    fwrite(STDERR, "Official SAF-T example did not produce parseable accounting data\n");
    exit(1);
}

if (DkCanonicalDecimal::equals($package->declaredTotalDebit, $package->totalDebit())) {
    fwrite(STDERR, "Expected known ERST example debit-total inconsistency was not observed\n");
    exit(1);
}

if (!DkCanonicalDecimal::equals($package->declaredTotalCredit, $package->totalCredit())) {
    fwrite(STDERR, "Unexpected credit-total mismatch in official ERST example\n");
    exit(1);
}

$strictRejected = false;
try {
    $importer->importXml($xml, $argv[1], true);
} catch (InvalidArgumentException $e) {
    $strictRejected = str_contains($e->getMessage(), 'TotalDebit');
}

if (!$strictRejected) {
    fwrite(STDERR, "Strict importer did not reject inconsistent official ERST debit totals\n");
    exit(1);
}

echo "Official ERST SAF-T 2.1 example is XSD/structure compatible; "
    ."strict accounting consistency correctly rejects its debit-total mismatch "
    ."(".$package->declaredTotalDebit." declared vs ".$package->totalDebit()." parsed)\n";
