<?php

require_once __DIR__.'/P0ReleaseGate.php';
require_once __DIR__.'/P0ReleaseGateRepository.php';
require_once __DIR__.'/P0SecurityRiskEvidence.php';
require_once __DIR__.'/P0ReleaseReportSigner.php';
require_once __DIR__.'/P0ReleaseSigningKeyProvider.php';
require_once __DIR__.'/P0ReleaseSigningPolicy.php';
require_once __DIR__.'/P0ReleaseCandidatePolicy.php';
require_once __DIR__.'/P0ReleaseKeyCeremonyEvidence.php';
require_once __DIR__.'/ProductManifest.php';
require_once __DIR__.'/ProductionEvidenceManifest.php';
require_once __DIR__.'/P0RiskEvidenceManifestBindingPolicy.php';

final class DkP0ReleaseGateCollector
{
    private $repository;
    private $signingKeyProvider;

    public function __construct(DkP0ReleaseGateRepository $repository, DkP0ReleaseSigningKeyProvider $signingKeyProvider = null)
    {
        $this->repository = $repository;
        $this->signingKeyProvider = $signingKeyProvider;
    }

    public function collectAndPersist($entity, $deploymentId, $manifestPath, DateTimeImmutable $now)
    {
        $manifest = DkProductManifest::load($manifestPath);
        $manifestHash = hash_file('sha256', $manifestPath);
        if (!is_string($manifestHash)) throw new RuntimeException('Unable to hash P0 product manifest');
        $candidate = $manifest->value('product.profile_status') === 'registered-candidate';
        $signingApproved = false;
        $manifestGate = $candidate
            ? $this->pass('product-manifest', $manifestHash)
            : $this->blocked('product-manifest', array('product-profile-not-registered-candidate'), $manifestHash);
        if ($candidate) {
            try {
                $evidenceManifestPath = getenv('DKMODUL_P0_PRODUCTION_EVIDENCE_MANIFEST_PATH');
                $evidenceManifest = DkProductionEvidenceManifest::loadJson($evidenceManifestPath, $deploymentId, $now);
                $manifest->assertReleaseSigningContract();
                if ($this->signingKeyProvider === null) {
                    $manifestGate = $this->blocked('product-manifest', array('release-signing-not-configured'), $manifestHash);
                } else {
                    $ceremonyPath = getenv('DKMODUL_P0_SIGNING_CEREMONY_EVIDENCE_PATH');
                    $trustStorePath = getenv('DKMODUL_ATTESTATION_TRUST_STORE_PATH');
                    $ceremony = DkP0ReleaseKeyCeremonyEvidence::loadSigned(
                        $ceremonyPath,
                        $trustStorePath,
                        $manifest->value('deployment.deployment_attestation.trust_store_sha256'),
                        $now
                    );
                    DkP0ReleaseKeyCeremonyEvidence::assertMatchesManifest(
                        $ceremony['evidence'],
                        array(
                            'key_id' => $manifest->value('release_signing.key_id'),
                            'public_key_sha256' => $manifest->value('release_signing.public_key_sha256'),
                        )
                    );
                    DkP0ReleaseSigningPolicy::assertApproved(
                        $this->signingKeyProvider,
                        $manifest->value('release_signing.key_id'),
                        $manifest->value('release_signing.public_key_sha256')
                    );
                    $signingApproved = true;
                }
            } catch (Throwable $e) {
                $reason = strpos($e->getMessage(), 'Production evidence manifest') === 0
                    ? 'production-evidence-manifest-not-complete'
                    : 'release-signing-key-not-approved';
                $manifestGate = $this->blocked('product-manifest', array($reason), $manifestHash);
            }
        }
        $results = array($manifestGate);
        $accessPointManifestReady = true;
        try {
            $manifest->assertCertifiedAccessPoint();
        } catch (Throwable $e) {
            $accessPointManifestReady = false;
        }
        $attestation = $this->checkGate($entity, $deploymentId, 'deployment-attestation', 'deployment-attestation', $now);
        $backup = $this->checkGate($entity, $deploymentId, 'backup-restore', 'backup-retention-restore', $now);
        $accessPoint = $this->checkGate($entity, $deploymentId, 'access-point-certification', 'access-point-certification', $now);
        if (!$accessPointManifestReady) {
            $accessPoint['gate'] = $this->blocked('access-point-certification', array('access-point-certification-not-ready'), $manifestHash);
        }
        $results[] = $attestation['gate'];
        $results[] = $backup['gate'];
        $results[] = $accessPoint['gate'];
        $configuration = $this->readFreshCheck($entity, $deploymentId, 'configuration', $now);
        $sources = array($configuration, $attestation['check'], $backup['check'], $accessPoint['check']);
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

        $results[] = $this->collectSecurityRiskEvidence($deploymentId, $manifest, $now, isset($evidenceManifest) ? $evidenceManifest['manifest'] : null);

        $report = DkP0ReleaseGate::evaluate($manifest->value('product.id'), $manifest->value('product.release'), $deploymentId, $results, $now);
        if ($signingApproved) {
            $signature = DkP0ReleaseReportSigner::sign(
                $report,
                $this->signingKeyProvider->privateKeyPem(),
                $this->signingKeyProvider->keyId()
            );
            DkP0ReleaseSigningPolicy::assertSignatureMatchesProvider($report, $signature, $this->signingKeyProvider);
            $report['signature'] = $signature;
        }

        if ($candidate && $report['decision'] === 'PASS') {
            if (!isset($report['signature']) || !is_array($report['signature'])) {
                throw new RuntimeException('Registered-candidate P0 release requires a signed release report');
            }
            DkP0ReleaseCandidatePolicy::assertEligible($report, $report['signature'], $this->signingKeyProvider);
        }

        return array('report' => $report, 'report_uuid' => $this->repository->appendReport($entity, $report));
    }

    private function collectSecurityRiskEvidence($deploymentId, $manifest, DateTimeImmutable $now, $productionEvidenceManifest = null)
    {
        $pathEnv = $manifest->value('deployment.deployment_attestation.security_risk_evidence.path_env');
        $path = getenv($pathEnv);
        $trustStorePath = getenv('DKMODUL_ATTESTATION_TRUST_STORE_PATH');
        $trustStoreSha256 = $manifest->value('deployment.deployment_attestation.security_risk_evidence.trust_store_sha256');
        if ($manifest->value('deployment.deployment_attestation.security_risk_evidence.required') !== true
            || $manifest->value('deployment.deployment_attestation.security_risk_evidence.signature_required') !== true
            || $manifest->value('deployment.deployment_attestation.security_risk_evidence.signature_algorithms') !== array('RSA-SHA256')) {
            return $this->blocked('security-risk-evidence', array('security-risk-evidence-contract-invalid'), null);
        }
        if (!is_string($path) || trim($path) === '' || !is_string($trustStorePath) || trim($trustStorePath) === '') {
            return $this->blocked('security-risk-evidence', array('security-risk-evidence-not-configured'), null);
        }

        try {
            $result = DkP0SecurityRiskEvidence::loadSigned($path, $trustStorePath, $trustStoreSha256, $now);
            if ($result['evidence']['deployment_id'] !== $deploymentId) {
                return $this->blocked('security-risk-evidence', array('security-risk-evidence-deployment-mismatch'), $result['evidence_sha256']);
            }
            if (is_array($productionEvidenceManifest)) {
                DkP0RiskEvidenceManifestBindingPolicy::assertMatches($productionEvidenceManifest, $result['evidence']);
            }

            return $this->pass('security-risk-evidence', $result['evidence_sha256']);
        } catch (Throwable $e) {
            return $this->blocked('security-risk-evidence', array('invalid-security-risk-evidence'), null);
        }
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
