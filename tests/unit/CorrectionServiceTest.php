<?php

require_once __DIR__.'/../../dkmodul/class/Accounting/CorrectionService.php';

class DkCorrectionFailOnQueryDb
{
    public function query($sql)
    {
        throw new RuntimeException('DB should not be reached by input-validation tests');
    }
}

$service = new DkCorrectionService(new DkCorrectionFailOnQueryDb());

$cases = array(
    array(1, 10, 11, 'invalid', 'reason', 1),
    array(1, 10, 11, 'reversal', '', 1),
    array(1, 10, 10, 'reversal', 'reason', 1),
    array(1, 0, 11, 'reversal', 'reason', 1),
);

foreach ($cases as $case) {
    $failed = false;
    try {
        $service->record(...$case);
    } catch (InvalidArgumentException $e) {
        $failed = true;
    }
    assert($failed === true);
}

$user = (object) array('id' => 1);
$reverseCases = array(
    array(0, 10, 'reason', $user),
    array(1, 0, 'reason', $user),
    array(1, 10, '', $user),
    array(1, 10, 'reason', (object) array()),
);

foreach ($reverseCases as $case) {
    $failed = false;
    try {
        $service->reverse(...$case);
    } catch (InvalidArgumentException $e) {
        $failed = true;
    }
    assert($failed === true);
}

echo "CorrectionService validation tests passed\n";
