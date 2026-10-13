<?php

$root = dirname(__DIR__, 2);
$checklist = $root.'/docs/p0-readiness-checklist.md';

if (!is_file($checklist)) {
    fwrite(STDERR, "FAIL: P0 readiness checklist is missing\n");
    exit(1);
}

$content = file_get_contents($checklist);
if ($content === false) {
    fwrite(STDERR, "FAIL: P0 readiness checklist cannot be read\n");
    exit(1);
}

$unchecked = preg_match_all('/^- \[ \] /m', $content, $matches);
if ($unchecked === false) {
    fwrite(STDERR, "FAIL: checklist could not be parsed\n");
    exit(1);
}

$required = array(
    'docs/p0-readiness-checklist.md',
    'docs/adr/0014-backup-and-restore-evidence.md',
    'docs/adr/0015-operational-alert-transport.md',
    'docs/adr/0016-signing-and-custody-boundary.md',
    'dkmodul/class/Operations/DkBackupEvidenceService.php',
    'dkmodul/class/Operations/DkAlertTransport.php',
    'dkmodul/class/Operations/DkEvidenceSigner.php',
    'tests/unit/BackupEvidenceServiceTest.php',
    'tests/unit/AlertTransportTest.php',
    'tests/unit/CustodyEvidenceTest.php',
);

$missing = array();
foreach ($required as $path) {
    if (!is_file($root.'/'.$path)) {
        $missing[] = $path;
    }
}

if ($unchecked > 0) {
    fwrite(STDERR, "FAIL: {$unchecked} P0 checklist item(s) remain unchecked\n");
    preg_match_all('/^- \[ \] (.+)$/m', $content, $items);
    foreach ($items[1] as $item) {
        fwrite(STDERR, "  OPEN: ".$item."\n");
    }
}

if ($missing) {
    fwrite(STDERR, "FAIL: required release evidence files are missing\n");
    foreach ($missing as $path) {
        fwrite(STDERR, "  MISSING: ".$path."\n");
    }
}

if ($unchecked > 0 || $missing) {
    exit(1);
}

echo "RELEASE GATE: PASS\n";
