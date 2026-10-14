<?php
require_once __DIR__.'/../../dkmodul/class/Compliance/P0DeploymentReceiptEvidence.php';

$dir=sys_get_temp_dir().'/dkmodul-p0-deployment-receipt-'.bin2hex(random_bytes(4));
mkdir($dir,0700,true);
$key=openssl_pkey_new(array('private_key_type'=>OPENSSL_KEYTYPE_RSA,'private_key_bits'=>2048));
openssl_pkey_export($key,$privatePem);
$details=openssl_pkey_get_details($key);
$publicPem=$details['key'];
$keyId='deployment-receipt-test-key';
$trustStore=array('schema_version'=>1,'keys'=>array(array('key_id'=>$keyId,'algorithm'=>'RSA-SHA256','status'=>'active','public_key_pem'=>$publicPem)));
file_put_contents($dir.'/trust.json',json_encode($trustStore,JSON_UNESCAPED_SLASHES));

$payload=array(
    'schema_version'=>1,
    'deployment_id'=>'deployment-001',
    'environment'=>'production',
    'deployed_at'=>'2026-10-13T10:00:00Z',
    'approved_by'=>'release-board',
    'approved_at'=>'2026-10-12',
    'valid_until'=>'2026-11-12',
    'change_reference'=>'release-2026-10-13',
);
$payloadJson=json_encode($payload,JSON_UNESCAPED_SLASHES);
openssl_sign($payloadJson,$signature,$privatePem,OPENSSL_ALGO_SHA256);
file_put_contents($dir.'/receipt.json',json_encode(array(
    'schema_version'=>1,
    'signed_payload'=>base64_encode($payloadJson),
    'signature'=>array('key_id'=>$keyId,'algorithm'=>'RSA-SHA256','value'=>base64_encode($signature)),
),JSON_UNESCAPED_SLASHES));

$attestation=array('deployment_id'=>'deployment-001','approval'=>array('approved_by'=>'release-board','valid_until'=>'2026-11-12'));
$result=DkP0DeploymentReceiptEvidence::loadSigned($dir.'/receipt.json',$dir.'/trust.json',hash_file('sha256',$dir.'/trust.json'),new DateTimeImmutable('2026-10-13T12:00:00Z'));
assert($result['evidence']['deployment_id']==='deployment-001');
DkP0DeploymentReceiptEvidence::assertMatchesAttestation($result['evidence'],$attestation,'deployment-001');

$failed=false;
try { DkP0DeploymentReceiptEvidence::assertMatchesAttestation($result['evidence'],$attestation,'other-deployment'); } catch (Throwable $e) { $failed=true; }
assert($failed===true);

$future=$payload;
$future['deployed_at']='2026-10-14T10:00:00Z';
$payloadJson=json_encode($future,JSON_UNESCAPED_SLASHES);
openssl_sign($payloadJson,$signature,$privatePem,OPENSSL_ALGO_SHA256);
file_put_contents($dir.'/receipt.json',json_encode(array(
    'schema_version'=>1,
    'signed_payload'=>base64_encode($payloadJson),
    'signature'=>array('key_id'=>$keyId,'algorithm'=>'RSA-SHA256','value'=>base64_encode($signature)),
),JSON_UNESCAPED_SLASHES));
$failed=false;
try { DkP0DeploymentReceiptEvidence::loadSigned($dir.'/receipt.json',$dir.'/trust.json',hash_file('sha256',$dir.'/trust.json'),new DateTimeImmutable('2026-10-13T12:00:00Z')); } catch (Throwable $e) { $failed=true; }
assert($failed===true);

echo "P0 deployment receipt evidence tests passed\n";
