<?php

require_once __DIR__.'/../../dkmodul/class/Compliance/ProductionEvidenceManifest.php';

final class ProductionEvidenceManifestLoadTest
{
    public static function run()
    {
        $path = tempnam(sys_get_temp_dir(), 'dkmodul-evidence-');
        if ($path === false) throw new RuntimeException('Unable to create temp file');

        $manifest = array(
            'schemaVersion' => '1',
            'deploymentId' => 'dep-001',
            'items' => array(
                array(
                    'controlId' => 'backup-restore',
                    'status' => 'evidenced',
                    'evidenceRef' => 's3://evidence/restore.json',
                    'evidenceSha256' => str_repeat('a', 64),
                    'approvedBy' => 'approver@example.invalid',
                    'approvedAt' => '2026-10-14T12:00:00Z',
                ),
            ),
        );

        file_put_contents($path, json_encode($manifest, JSON_THROW_ON_ERROR));
        $loaded = DkProductionEvidenceManifest::loadJson($path, 'dep-001');
        if ($loaded['summary']['open_count'] !== 0 || !preg_match('/^[a-f0-9]{64}$/', $loaded['sha256'])) {
            throw new RuntimeException('Valid production evidence manifest was not accepted');
        }

        $failed = false;
        try {
            DkProductionEvidenceManifest::loadJson($path, 'other-deployment');
        } catch (RuntimeException $e) {
            $failed = true;
        }
        if (!$failed) throw new RuntimeException('Deployment mismatch was not rejected');

        $manifest['items'][0]['status'] = 'open';
        unset($manifest['items'][0]['evidenceRef'], $manifest['items'][0]['evidenceSha256'], $manifest['items'][0]['approvedBy'], $manifest['items'][0]['approvedAt']);
        file_put_contents($path, json_encode($manifest, JSON_THROW_ON_ERROR));

        $failed = false;
        try {
            DkProductionEvidenceManifest::loadJson($path, 'dep-001');
        } catch (RuntimeException $e) {
            $failed = true;
        }
        unlink($path);
        if (!$failed) throw new RuntimeException('Incomplete production evidence manifest was not rejected');

        echo "ProductionEvidenceManifestLoadTest: PASS\n";
    }
}

ProductionEvidenceManifestLoadTest::run();
