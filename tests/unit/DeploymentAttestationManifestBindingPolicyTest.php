<?php
require_once __DIR__.'/../../dkmodul/class/Compliance/P0ReleaseGate.php';
require_once __DIR__.'/../../dkmodul/class/Compliance/DkDeploymentAttestationManifestBindingPolicy.php';

$attestation=array(
    'schema_version'=>1,
    'deployment_id'=>'deployment-001',
    'hosting'=>array('legal_name'=>'Hosting ApS'),
);
$hash=hash('sha256',DkP0ReleaseGate::canonicalJson($attestation));
$manifest=array(
    'schemaVersion'=>'1',
    'deploymentId'=>'deployment-001',
    'items'=>array(
        array('controlId'=>'production-deployment-attestation','status'=>'evidenced','evidenceRef'=>'attestation.json','evidenceSha256'=>$hash,'approvedBy'=>'deployment-owner','approvedAt'=>'2026-10-10T10:00:00Z'),
    ),
);
assert(DkDeploymentAttestationManifestBindingPolicy::assertMatches($manifest,$attestation,'deployment-001')===true);

$failed=false;
try {
    $bad=$manifest;
    $bad['items'][0]['evidenceSha256']=str_repeat('a',64);
    DkDeploymentAttestationManifestBindingPolicy::assertMatches($bad,$attestation,'deployment-001');
} catch (Throwable $e) { $failed=true; }
assert($failed===true);

$failed=false;
try { DkDeploymentAttestationManifestBindingPolicy::assertMatches($manifest,$attestation,'other-deployment'); } catch (Throwable $e) { $failed=true; }
assert($failed===true);

$failed=false;
try {
    $open=$manifest;
    $open['items'][0]['status']='open';
    DkDeploymentAttestationManifestBindingPolicy::assertMatches($open,$attestation,'deployment-001');
} catch (Throwable $e) { $failed=true; }
assert($failed===true);

echo "Deployment attestation manifest binding tests passed\n";
