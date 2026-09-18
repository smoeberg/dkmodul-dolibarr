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
$package = $importer->importXml($xml, $argv[1]);

if ($package->header['auditFileVersion'] !== '2.1') {
    fwrite(STDERR, "Unexpected official SAF-T version\n");
    exit(1);
}
if ($package->lineCount() < 1 || count($package->transactions) < 1 || count($package->accounts) < 1) {
    fwrite(STDERR, "Official SAF-T example did not produce importable accounting data\n");
    exit(1);
}
if (!DkCanonicalDecimal::equals($package->totalDebit(), $package->totalCredit())) {
    fwrite(STDERR, "Imported official SAF-T example is not globally balanced\n");
    exit(1);
}

echo "Official ERST SAF-T 2.1 example parsed into canonical import package: "
    .count($package->transactions)." transactions / ".$package->lineCount()." lines\n";
