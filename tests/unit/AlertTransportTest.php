<?php

require_once __DIR__.'/../../dkmodul/class/Operations/DkAlert.php';
require_once __DIR__.'/../../dkmodul/class/Operations/DkAlertTransport.php';

final class TestAlertTransport implements DkAlertTransport
{
    public array $alerts = array();

    public function send(array $alert): string
    {
        if (!isset($alert['schemaVersion'], $alert['control'], $alert['severity'])) {
            throw new RuntimeException('Malformed alert');
        }
        $this->alerts[] = $alert;
        return 'delivery-1';
    }
}

$transport = new TestAlertTransport();
$alert = DkAlert::create(
    'backup-restore',
    'critical',
    'Restore test failed',
    42,
    array('restoreTestId' => 'restore-1')
);

assert($alert['severity'] === 'critical');
assert($alert['details']['restoreTestId'] === 'restore-1');
assert($transport->send($alert) === 'delivery-1');
assert(count($transport->alerts) === 1);

$throws = false;
try {
    DkAlert::create('', 'critical', 'message', 42);
} catch (InvalidArgumentException $e) {
    $throws = true;
}
assert($throws === true);

$throws = false;
try {
    DkAlert::create('control', 'info', 'message', 42);
} catch (InvalidArgumentException $e) {
    $throws = true;
}
assert($throws === true);

$throws = false;
try {
    DkAlert::create('control', 'error', 'message', 0);
} catch (InvalidArgumentException $e) {
    $throws = true;
}
assert($throws === true);

echo "Alert transport contract evidence passed\n";
