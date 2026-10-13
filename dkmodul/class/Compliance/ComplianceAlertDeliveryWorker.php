<?php

require_once __DIR__.'/AlertDeliveryChannel.php';
require_once __DIR__.'/AlertEscalationChannel.php';

final class DkComplianceAlertDeliveryWorker
{
    private $repository;
    private $primary;
    private $escalation;
    private $maxAttempts;

    public function __construct($repository, DkAlertDeliveryChannel $primary, DkAlertEscalationChannel $escalation, $maxAttempts = 5)
    {
        if ((int) $maxAttempts < 1) {
            throw new RuntimeException('Alert delivery max attempts must be positive');
        }
        $this->repository = $repository;
        $this->primary = $primary;
        $this->escalation = $escalation;
        $this->maxAttempts = (int) $maxAttempts;
    }

    public function run($entity, DateTimeImmutable $now, $limit = 50, $throwOnFailure = true)
    {
        $this->repository->recoverStaleProcessing($entity, $now->modify('-15 minutes'));
        $rows = $this->repository->pendingDeliveries($entity, $now, $limit);
        $result = array('processed' => 0, 'delivered' => 0, 'retried' => 0, 'escalated' => 0, 'failed' => 0);

        foreach ($rows as $row) {
            if (!$this->repository->claimDelivery($row->delivery_uuid, $now)) {
                continue;
            }
            $result['processed']++;
            $attempt = ((int) $row->attempt_count) + 1;
            $alert = $this->alertFromRow($row);
            try {
                $reference = $this->primary->send($alert);
                if (!is_string($reference) || trim($reference) === '') {
                    throw new RuntimeException('Primary alert channel returned no delivery reference');
                }
                $this->repository->markDelivery($row->delivery_uuid, 'delivered', $attempt, null, $reference, '', $now);
                $result['delivered']++;
                continue;
            } catch (Throwable $e) {
                $errorCode = substr(hash('sha256', get_class($e).':'.$e->getMessage()), 0, 64);
            }

            if ($attempt < $this->maxAttempts) {
                $delayMinutes = min(60, 5 * (2 ** max(0, $attempt - 1)));
                $this->repository->markDelivery(
                    $row->delivery_uuid, 'retry', $attempt, $now->modify('+'.$delayMinutes.' minutes'), '', $errorCode, $now
                );
                $result['retried']++;
                continue;
            }

            try {
                $reference = $this->escalation->escalate($alert, $attempt);
                if (!is_string($reference) || trim($reference) === '') {
                    throw new RuntimeException('Escalation channel returned no delivery reference');
                }
                $this->repository->markDelivery($row->delivery_uuid, 'escalated', $attempt, null, $reference, $errorCode, $now);
                $result['escalated']++;
            } catch (Throwable $e) {
                $escalationCode = substr(hash('sha256', get_class($e).':'.$e->getMessage()), 0, 64);
                $this->repository->markDelivery($row->delivery_uuid, 'failed', $attempt, null, '', $escalationCode, $now);
                $result['failed']++;
            }
        }

        if ($throwOnFailure && $result['failed'] > 0) {
            throw new RuntimeException('Operational alert delivery failed after retry and escalation exhaustion');
        }

        return $result;
    }

    private function alertFromRow($row)
    {
        $reasonCodes = json_decode((string) $row->reason_codes, true);
        if (!is_array($reasonCodes)) {
            throw new RuntimeException('Compliance alert reason evidence is invalid JSON');
        }

        return array(
            'alert_uuid' => (string) $row->alert_uuid,
            'check_uuid' => (string) $row->check_uuid,
            'deployment_id' => (string) $row->deployment_id,
            'check_type' => (string) $row->check_type,
            'event_type' => (string) $row->event_type,
            'severity' => (string) $row->severity,
            'reason_codes' => array_values($reasonCodes),
        );
    }
}
