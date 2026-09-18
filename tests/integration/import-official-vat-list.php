<?php

define('NOLOGIN', 1);
define('NOREQUIREMENU', 1);
define('NOREQUIREHTML', 1);

require '/var/www/html/main.inc.php';
require '/var/www/html/custom/dkmodul/class/Accounting/StandardVatCodeImporter.php';

if ($argc < 2) {
    fwrite(STDERR, "Usage: php import-official-vat-list.php /path/to/vat.json\n");
    exit(2);
}

$json = file_get_contents($argv[1]);
if ($json === false) {
    fwrite(STDERR, "Unable to read VAT list\n");
    exit(1);
}

$importer = new DkStandardVatCodeImporter($db);
$result = $importer->importJson($json, '20260101');

if ($result['validFrom'] !== '2025-12-01' || $result['codeCount'] < 10) {
    fwrite(STDERR, "Unexpected VAT import result\n");
    exit(1);
}

echo "Imported official ERST VAT catalogue: ".$result['codeCount']." codes\n";
