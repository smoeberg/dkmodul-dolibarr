<?php
require_once __DIR__.'/../../dkmodul/class/Compliance/P0RiskEvidenceManifestBindingPolicy.php';

$riskEvidence=array(
    'risk_register_sha256'=>str_repeat('a',64),
    'controls_sha256'=>str_repeat('b',64),
);

$manifest=array(
    'schemaVersion'=>'1',
    'deploymentId'=>'deployment-001',
    'items'=>array(
        array('controlId'=>'formal-risk-register','status'=>'evidenced','evidenceRef'=>'risk-register.json','evidenceSha256'=>str_repeat('a',64),'approvedBy'=>'risk-owner','approvedAt'=>'2026-10-10T10:00:00Z'),
        array('controlId'=>'security-operational-controls','status'=>'evidenced','evidenceRef'=>'controls.json','evidenceSha256'=>str_repeat('b',64),'approvedBy'=>'security-owner','approvedAt'=>'2026-10-10T10:00:00Z'),
    ),
);
assert(DkP0RiskEvidenceManifestBindingPolicy::assertMatches($manifest,$riskEvidence)===true);

foreach (array(
    'formal-risk-register',
    'security-operational-controls',
) as $controlId) {
    $invalid=$manifest;
    foreach ($invalid['items'] as &$item) {
        if ($item['controlId']===$controlId) $item['evidenceSha256']=str_repeat('c',64);
    }
    unset($item);
    $failed=false;
    try { DkP0RiskEvidenceManifestBindingPolicy::assertMatches($invalid,$riskEvidence); } catch (Throwable $e) { $failed=true; }
    assert($failed===true);
}

$open=$manifest;
$open['items'][0]['status']='open';
$failed=false;
try { DkP0RiskEvidenceManifestBindingPolicy::assertMatches($open,$riskEvidence); } catch (Throwable $e) { $failed=true; }
assert($failed===true);

echo "P0 risk evidence manifest binding tests passed\n";
