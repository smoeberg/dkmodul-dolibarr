<?php

require_once __DIR__.'/BackupComplianceMonitor.php';
require_once __DIR__.'/ComplianceLock.php';
require_once __DIR__.'/ComplianceMonitorRepository.php';
require_once __DIR__.'/ComplianceMonitoringService.php';
require_once __DIR__.'/OutboxAlertTransport.php';
require_once __DIR__.'/P0ReleaseGateCollector.php';

final class DkComplianceMonitorJob
{
    private $db;

    public function __construct($db)
    {
        $this->db = $db;
    }

    public function run()
    {
        global $conf;

        if (!getDolGlobalInt('DKMODUL_REGISTERED_PROFILE')) {
            return 0;
        }

        $entity = (int) $conf->entity;
        $deploymentId = getDolGlobalString('DKMODUL_DEPLOYMENT_ID');
        $checkedAt = new DateTimeImmutable('now');
        $nextDueAt = $checkedAt->modify('+1 hour');
        $service = new DkComplianceMonitoringService(
            new DkComplianceMonitorRepository($this->db),
            new DkOutboxAlertTransport()
        );

        $configurationReasons = array();
        foreach (array(
            'DKMODUL_DEPLOYMENT_ID',
            'DKMODUL_REQUIRED_RETAIN_UNTIL',
            'DKMODUL_HOSTING_REGISTRATION',
            'DKMODUL_DEPLOYMENT_ATTESTATION_PATH',
            'DKMODUL_ATTESTATION_TRUST_STORE_PATH',
        ) as $constant) {
            if (trim(getDolGlobalString($constant)) === '') {
                $configurationReasons[] = 'missing-'.strtolower($constant);
            }
        }
        if (!getDolGlobalInt('DKMODUL_COMPLIANCE_MODE')) {
            $configurationReasons[] = 'compliance-mode-disabled';
        }
        $this->record($service, $entity, $deploymentId ?: 'unconfigured', 'configuration', $configurationReasons, $checkedAt, $nextDueAt);

        $attestationReasons = array();
        try {
            DkComplianceLock::assertDeploymentReady(
                dirname(__DIR__, 2).'/product-manifest.json',
                getDolGlobalString('DKMODUL_DEPLOYMENT_ATTESTATION_PATH'),
                true,
                getDolGlobalString('DKMODUL_ATTESTATION_TRUST_STORE_PATH')
            );
        } catch (Throwable $e) {
            $attestationReasons[] = 'attestation-invalid';
            dol_syslog('DK compliance attestation monitor: '.$e->getMessage(), LOG_ERR);
        }
        $this->record($service, $entity, $deploymentId ?: 'unconfigured', 'deployment-attestation', $attestationReasons, $checkedAt, $nextDueAt);

        $backupReasons = array();
        try {
            $status = (new DkBackupComplianceMonitor($this->db))->status(
                $entity,
                $deploymentId,
                getDolGlobalString('DKMODUL_REQUIRED_RETAIN_UNTIL'),
                getDolGlobalString('DKMODUL_HOSTING_REGISTRATION'),
                $checkedAt
            );
            $backupReasons = $status['reasons'];
        } catch (Throwable $e) {
            $backupReasons[] = 'backup-monitor-error';
            dol_syslog('DK backup compliance monitor: '.$e->getMessage(), LOG_ERR);
        }
        $this->record($service, $entity, $deploymentId ?: 'unconfigured', 'backup-restore', $backupReasons, $checkedAt, $nextDueAt);

        (new DkP0ReleaseGateCollector(new DkP0ReleaseGateRepository($this->db)))->collectAndPersist(
            $entity,
            $deploymentId ?: 'unconfigured',
            dirname(__DIR__, 2).'/product-manifest.json',
            $checkedAt
        );

        return 0;
    }

    private function record($service, $entity, $deploymentId, $checkType, array $reasons, DateTimeImmutable $checkedAt, DateTimeImmutable $nextDueAt)
    {
        sort($reasons, SORT_STRING);
        $status = count($reasons) === 0 ? 'ok' : 'failed';
        $evidenceHash = hash('sha256', json_encode(array(
            'deployment_id' => $deploymentId,
            'check_type' => $checkType,
            'status' => $status,
            'reasons' => $reasons,
            'checked_at' => $checkedAt->format(DateTimeInterface::ATOM),
        ), JSON_UNESCAPED_SLASHES));
        $service->record($entity, $deploymentId, $checkType, $status, $reasons, $evidenceHash, $checkedAt, $nextDueAt);
    }
}
