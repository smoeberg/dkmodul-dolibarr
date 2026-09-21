<?php

require_once __DIR__.'/BackupComplianceStatus.php';

final class DkBackupComplianceMonitor
{
    private $db;

    public function __construct($db)
    {
        $this->db = $db;
    }

    public function status($entity, $deploymentId, $requiredRetainUntil, $hostingRegistration, DateTimeImmutable $now = null)
    {
        $entity = (int) $entity;
        $deploymentId = $this->db->escape((string) $deploymentId);
        $backups = $this->rows(
            'SELECT backup_type,status,completed_at,provider_name,provider_registration,country,region,'
            .'object_reference,receipt_sha256,byte_size,retention_until,immutable_until,signature_verified'
            .' FROM '.$this->db->prefix().'dk_backup_evidence'
            .' WHERE entity='.$entity." AND deployment_id='".$deploymentId."' ORDER BY completed_at DESC"
        );
        $restores = $this->rows(
            'SELECT status,completed_at,database_sha256,document_sample_sha256,saft_sha256,'
            .'debit_total,credit_total,reviewed_by,reviewed_at,evidence_reference FROM '.$this->db->prefix().'dk_restore_evidence'
            .' WHERE entity='.$entity." AND deployment_id='".$deploymentId."' ORDER BY completed_at DESC"
        );

        return DkBackupComplianceStatus::evaluate(
            $backups,
            $restores,
            $now ?: new DateTimeImmutable('now'),
            $requiredRetainUntil,
            $hostingRegistration
        );
    }

    private function rows($sql)
    {
        $result = $this->db->query($sql);
        if (!$result) {
            throw new RuntimeException('Unable to read DK backup compliance evidence: '.$this->db->lasterror());
        }
        $rows = array();
        while ($object = $this->db->fetch_object($result)) {
            $rows[] = get_object_vars($object);
        }

        return $rows;
    }
}
