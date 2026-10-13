<?php

require_once dirname(__DIR__, 2).'/dkmodul/class/Compliance/AlertDeliveryChannel.php';
require_once dirname(__DIR__, 2).'/dkmodul/class/Compliance/AlertEscalationChannel.php';
require_once dirname(__DIR__, 2).'/dkmodul/class/Compliance/ComplianceAlertDeliveryWorker.php';

final class WorkerRepositoryFake
{
    public $row;
    public $states = array();

    public function __construct($attemptCount = 0)
    {
        $this->row = (object) array(
            'delivery_uuid' => 'delivery-1',
            'alert_uuid' => 'alert-1',
            'deployment_id' => 'deployment-1',
            'check_type' => 'backup-restore',
            'event_type' => 'opened',
            'severity' => 'high',
            'reason_codes' => '["backup-stale"]',
            'check_uuid' => 'check-1',
            'attempt_count' => $attemptCount,
        );
    }

    public function recoverStaleProcessing($entity, DateTimeImmutable $cutoff) {}

    public function pendingDeliveries($entity, DateTimeImmutable $now, $limit)
    {
        return $this->states === array('claimed') ? array() : array($this->row);
    }

    public function claimDelivery($deliveryUuid, DateTimeImmutable $now)
    {
        if ($this->states === array('claimed')) {
            return false;
        }
        $this->states[] = 'claimed';
        return true;
    }

    public function markDelivery($deliveryUuid, $status, $attemptCount, $nextAttemptAt, $externalReference, $errorCode, DateTimeImmutable $attemptedAt)
    {
        $this->states[] = $status;
    }
}

final class PrimaryChannelFake implements DkAlertDeliveryChannel
{
    public $succeed;
    public $calls = 0;

    public function __construct($succeed)
    {
        $this->succeed = $succeed;
    }

    public function send(array $alert)
    {
        $this->calls++;
        if (!$this->succeed) {
            throw new RuntimeException('provider unavailable');
        }
        return 'provider-123';
    }
}

final class EscalationChannelFake implements DkAlertEscalationChannel
{
    public $succeed;
    public $calls = 0;

    public function __construct($succeed)
    {
        $this->succeed = $succeed;
    }

    public function escalate(array $alert, $attemptCount)
    {
        $this->calls++;
        if (!$this->succeed) {
            throw new RuntimeException('escalation unavailable');
        }
        return 'pager-123';
    }
}

$now = new DateTimeImmutable('2026-10-13T12:00:00+00:00');

$repository = new WorkerRepositoryFake(0);
$primary = new PrimaryChannelFake(true);
$escalation = new EscalationChannelFake(true);
$worker = new DkComplianceAlertDeliveryWorker($repository, $primary, $escalation, 3);
$result = $worker->run(1, $now);
assert($result['delivered'] === 1);
assert($primary->calls === 1);
assert($escalation->calls === 0);
assert(in_array('delivered', $repository->states, true));

$repository = new WorkerRepositoryFake(0);
$primary = new PrimaryChannelFake(false);
$escalation = new EscalationChannelFake(true);
$worker = new DkComplianceAlertDeliveryWorker($repository, $primary, $escalation, 2);
$result = $worker->run(1, $now, 50, false);
assert($result['retried'] === 1);
assert($escalation->calls === 0);
assert(in_array('retry', $repository->states, true));

$repository = new WorkerRepositoryFake(1);
$primary = new PrimaryChannelFake(false);
$escalation = new EscalationChannelFake(true);
$worker = new DkComplianceAlertDeliveryWorker($repository, $primary, $escalation, 2);
$result = $worker->run(1, $now, 50, false);
assert($result['escalated'] === 1);
assert(in_array('escalated', $repository->states, true));

$repository = new WorkerRepositoryFake(1);
$primary = new PrimaryChannelFake(false);
$escalation = new EscalationChannelFake(false);
$worker = new DkComplianceAlertDeliveryWorker($repository, $primary, $escalation, 2);
$failedClosed = false;
try {
    $worker->run(1, $now);
} catch (RuntimeException $e) {
    $failedClosed = strpos($e->getMessage(), 'failed after retry and escalation exhaustion') !== false;
}
assert($failedClosed === true);
assert(in_array('failed', $repository->states, true));

echo "Compliance alert delivery worker tests passed\n";
