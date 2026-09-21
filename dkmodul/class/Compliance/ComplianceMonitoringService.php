<?php

require_once __DIR__.'/ComplianceAlertPolicy.php';
require_once __DIR__.'/AlertTransportInterface.php';

final class DkComplianceMonitoringService
{
    private $repository;
    private $transport;

    public function __construct($repository, DkAlertTransportInterface $transport)
    {
        $this->repository = $repository;
        $this->transport = $transport;
    }

    public function record($entity, $deploymentId, $checkType, $status, array $reasonCodes, $evidenceSha256, DateTimeImmutable $checkedAt, DateTimeImmutable $nextDueAt)
    {
        if (!preg_match('/^[a-f0-9]{64}$/', (string) $evidenceSha256)) {
            throw new RuntimeException('Compliance-check evidence SHA-256 is required');
        }
        if ($nextDueAt <= $checkedAt) {
            throw new RuntimeException('Compliance-check next due time must be after check time');
        }

        $checkUuid = $this->repository->appendCheck(
            $entity, $deploymentId, $checkType, $status, $reasonCodes, $evidenceSha256, $checkedAt, $nextDueAt
        );
        $fingerprint = hash('sha256', (string) $deploymentId.'|'.(string) $checkType);
        $lastEventType = $this->repository->latestAlertEventType($entity, $deploymentId, $fingerprint);
        $event = DkComplianceAlertPolicy::transition($lastEventType, $status, $deploymentId, $checkType, $reasonCodes);
        if ($event === null) {
            return array('check_uuid' => $checkUuid, 'alert_uuid' => null);
        }

        $alertUuid = $this->repository->appendAlert($entity, $deploymentId, $checkType, $event, $checkUuid);
        $payload = $event + array(
            'alert_uuid' => $alertUuid,
            'check_uuid' => $checkUuid,
            'deployment_id' => $deploymentId,
            'check_type' => $checkType,
            'checked_at' => $checkedAt->format(DateTimeInterface::ATOM),
        );
        try {
            $delivery = $this->transport->deliver($payload);
            if (!is_array($delivery) || !isset($delivery['channel'], $delivery['status'])) {
                throw new RuntimeException('Alert transport returned an invalid delivery result');
            }
        } catch (Throwable $e) {
            $delivery = array('channel' => 'transport', 'status' => 'failed', 'external_reference' => substr($e->getMessage(), 0, 255));
        }
        $this->repository->appendDelivery($entity, $alertUuid, $delivery, $checkedAt);

        return array('check_uuid' => $checkUuid, 'alert_uuid' => $alertUuid);
    }
}
