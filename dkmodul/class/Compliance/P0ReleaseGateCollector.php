<?php

require_once __DIR__.'/P0ReleaseGate.php';
require_once __DIR__.'/P0ReleaseGateRepository.php';
require_once __DIR__.'/ProductManifest.php';

final class DkP0ReleaseGateCollector
{
    private $repository;

    public function __construct(DkP0ReleaseGateRepository $repository) { $this->repository = $repository; }

    public function collectAndPersist($entity, $deploymentId, $manifestPath, DateTimeImmutable $now)
    {
        $manifest = DkProductManifest::load($manifestPath);
        $manifestHash = hash_file('sha256', $manifestPath);
        if (!is_string($manifestHash)) throw new RuntimeException('Unable to hash P0 product manifest');
        $candidate = $manifest->value('product.profile_status') === 'registered-candidate';
        $results = array($candidate
            ? $this->pass('product-manifest', $manifestHash)
            : $this->blocked('product-manifest', array('product-profile-not-registered-candidate'), $manifestHash));
        try {
            $manifest->assertCertifiedAccessPoint();
            $results[] = $candidate ? $this->pass('access-point-certification', $manifestHash)
                : $this->blocked('access-point-certification', array('product-profile-not-registered-candidate'), $manifestHash);
        } catch (Throwable $e) {
            $results[] = $this->blocked('access-point-certification', array('access-point-certification-not-ready'), $manifestHash);
        }
        $attestation = $this->checkGate($entity, $deploymentId, 'deployment-attestation', 'deployment-attestation', $now);
        $backup = $this->checkGate($entity, $deploymentId, 'backup-restore', 'backup-retention-restore', $now);
        $results[] = $attestation['gate'];
        $results[] = $backup['gate'];
        $configuration = $this->readFreshCheck($entity, $deploymentId, 'configuration', $now);
        $sources = array($configuration, $attestation['check'], $backup['check']);
        $reasons = array();
        $failed = false;
        foreach ($sources as $source) {
            if ($source === null) $reasons[] = 'missing-or-stale-monitor-check';
            elseif ($source['status'] !== 'ok') { $failed = true; $reasons = array_merge($reasons, $source['reason_codes']); }
        }
        $monitorHash = hash('sha256', DkP0ReleaseGate::canonicalJson(array_map(function ($source) {
            return $source === null ? null : array('check_uuid' => $source['check_uuid'], 'evidence_sha256' => $source['evidence_sha256'], 'status' => $source['status']);
        }, $sources)));
        $results[] = $failed ? $this->fail('compliance-monitoring', $reasons, $monitorHash)
            : (count($reasons) ? $this->blocked('compliance-monitoring', $reasons, $monitorHash) : $this->pass('compliance-monitoring', $monitorHash));
        $results[] = $this->blocked('security-risk-evidence', array('verified-security-risk-evidence-not-connected'), null);
        $report = DkP0ReleaseGate::evaluate($manifest->value('product.id'), $manifest->value('product.release'), $deploymentId, $results, $now);
        return array('report' => $report, 'report_uuid' => $this->repository->appendReport($entity, $report));
    }

    private function checkGate($entity, $deploymentId, $checkType, $gateId, DateTimeImmutable $now)
    {
        $check = $this->readFreshCheck($entity, $deploymentId, $checkType, $now);
        if ($check === null) return array('check' => null, 'gate' => $this->blocked($gateId, array('missing-or-stale-'.$checkType.'-check'), null));
        if ($check['status'] !== 'ok') return array('check' => $check, 'gate' => $this->fail($gateId, $check['reason_codes'], $check['evidence_sha256']));
        return array('check' => $check, 'gate' => $this->pass($gateId, $check['evidence_sha256']));
    }

    private function readFreshCheck($entity, $deploymentId, $checkType, DateTimeImmutable $now)
    {
        $check = $this->repository->latestCheck($entity, $deploymentId, $checkType);
        if ($check === null || !preg_match('/^[a-f0-9]{64}$/', (string) ($check['evidence_sha256'] ?? ''))) return null;
        try { $checkedAt = new DateTimeImmutable($check['checked_at']); $nextDueAt = new DateTimeImmutable($check['next_due_at']); }
        catch (Throwable $e) { return null; }
        return ($checkedAt > $now || $nextDueAt < $now) ? null : $check;
    }

    private function pass($id, $hash) { return array('gate_id' => $id, 'status' => 'PASS', 'evidence_sha256' => $hash, 'reason_codes' => array()); }
    private function fail($id, array $reasons, $hash) { return array('gate_id' => $id, 'status' => 'FAIL', 'evidence_sha256' => $hash, 'reason_codes' => count($reasons) ? $reasons : array('runtime-check-failed')); }
    private function blocked($id, array $reasons, $hash) { return array('gate_id' => $id, 'status' => 'BLOCKED', 'evidence_sha256' => $hash, 'reason_codes' => $reasons); }
}
