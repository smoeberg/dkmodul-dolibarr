<?php

require_once __DIR__.'/../../dkmodul/class/Compliance/ComplianceMonitoringService.php';

final class DkFakeMonitorRepository
{
    public $lastEventType;
    public $checks = array();
    public $alerts = array();
    public $deliveries = array();

    public function appendCheck($entity, $deploymentId, $checkType, $status, array $reasons, $hash, $checkedAt, $nextDueAt)
    {
        $this->checks[] = compact('status', 'reasons', 'hash');
        return 'check-'.count($this->checks);
    }

    public function latestAlertEventType($entity, $deploymentId, $fingerprint)
    {
        return $this->lastEventType;
    }

    public function appendAlert($entity, $deploymentId, $checkType, array $event, $checkUuid)
    {
        $this->alerts[] = $event;
        $this->lastEventType = $event['event_type'];
        return 'alert-'.count($this->alerts);
    }

    public function appendDelivery($entity, $alertUuid, array $delivery, $attemptedAt)
    {
        $this->deliveries[] = $delivery;
        return 'delivery-'.count($this->deliveries);
    }
}

final class DkFakeAlertTransport implements DkAlertTransportInterface
{
    public $payloads = array();

    public function deliver(array $alert)
    {
        $this->payloads[] = $alert;
        return array('channel' => 'webhook', 'status' => 'delivered', 'external_reference' => 'test-ref');
    }
}

$repository = new DkFakeMonitorRepository();
$transport = new DkFakeAlertTransport();
$service = new DkComplianceMonitoringService($repository, $transport);
$checkedAt = new DateTimeImmutable('2026-09-21T12:00:00+00:00');
$nextDueAt = $checkedAt->modify('+1 hour');
$hash = str_repeat('a', 64);

$ok = $service->record(1, 'deployment-1', 'backup-restore', 'ok', array(), $hash, $checkedAt, $nextDueAt);
assert($ok['alert_uuid'] === null);

$opened = $service->record(1, 'deployment-1', 'backup-restore', 'failed', array('full-backup-stale'), $hash, $checkedAt, $nextDueAt);
assert($opened['alert_uuid'] === 'alert-1');
assert(count($transport->payloads) === 1);
assert($repository->alerts[0]['event_type'] === 'opened');

$duplicate = $service->record(1, 'deployment-1', 'backup-restore', 'failed', array('full-backup-stale'), $hash, $checkedAt, $nextDueAt);
assert($duplicate['alert_uuid'] === null);
assert(count($transport->payloads) === 1);

$recovered = $service->record(1, 'deployment-1', 'backup-restore', 'ok', array(), $hash, $checkedAt, $nextDueAt);
assert($recovered['alert_uuid'] === 'alert-2');
assert($repository->alerts[1]['event_type'] === 'recovered');
assert(count($repository->deliveries) === 2);

echo "Compliance monitoring service tests passed\n";
