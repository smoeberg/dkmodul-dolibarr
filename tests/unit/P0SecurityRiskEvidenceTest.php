<?php
require_once __DIR__.'/../../dkmodul/class/Compliance/P0SecurityRiskEvidence.php';

$dir=sys_get_temp_dir().'/dkmodul-p0-risk-'.bin2hex(random_bytes(4));
mkdir($dir,0700,true);
$key=openssl_pkey_new(array('private_key_type'=>OPENSSL_KEYTYPE_RSA,'private_key_bits'=>2048));
openssl_pkey_export($key,$privatePem);
$details=openssl_pkey_get_details($key);
$publicPem=$details['key'];
$keyId='risk-test-key';
$trustStore=array('schema_version'=>1,'keys'=>array(array('key_id'=>$keyId,'algorithm'=>'RSA-SHA256','status'=>'active','public_key_pem'=>$publicPem)));
file_put_contents($dir.'/trust.json',json_encode($trustStore,JSON_UNESCAPED_SLASHES));

$payload=array(
    'schema_version'=>1,
    'status'=>'approved',
    'deployment_id'=>'deployment-001',
    'reviewer'=>'risk-reviewer',
    'assessed_at'=>'2026-10-10T10:00:00Z',
    'valid_until'=>'2026-11-10T10:00:00Z',
    'risk_register_sha256'=>str_repeat('a',64),
    'controls_sha256'=>str_repeat('b',64)
);
$payloadJson=json_encode($payload,JSON_UNESCAPED_SLASHES);
openssl_sign($payloadJson,$signature,$privatePem,OPENSSL_ALGO_SHA256);
$envelope=array('schema_version'=>1,'signed_payload'=>base64_encode($payloadJson),'signature'=>array('key_id'=>$keyId,'algorithm'=>'RSA-SHA256','value'=>base64_encode($signature)));
file_put_contents($dir.'/evidence.json',json_encode($envelope,JSON_UNESCAPED_SLASHES));

$result=DkP0SecurityRiskEvidence::loadSigned($dir.'/evidence.json',$dir.'/trust.json',hash_file('sha256',$dir.'/trust.json'),new DateTimeImmutable('2026-10-14T12:00:00Z'));
assert($result['evidence']['deployment_id']==='deployment-001');

foreach (array(
    '2026-10-10T10:00:00+02:00',
    '2026-02-30T10:00:00Z'
) as $invalid) {
    $invalidPayload=$payload;
    $invalidPayload['assessed_at']=$invalid;
    $invalidJson=json_encode($invalidPayload,JSON_UNESCAPED_SLASHES);
    openssl_sign($invalidJson,$invalidSignature,$privatePem,OPENSSL_ALGO_SHA256);
    file_put_contents($dir.'/evidence.json',json_encode(array('schema_version'=>1,'signed_payload'=>base64_encode($invalidJson),'signature'=>array('key_id'=>$keyId,'algorithm'=>'RSA-SHA256','value'=>base64_encode($invalidSignature))),JSON_UNESCAPED_SLASHES));
    $failed=false;
    try { DkP0SecurityRiskEvidence::loadSigned($dir.'/evidence.json',$dir.'/trust.json',hash_file('sha256',$dir.'/trust.json'),new DateTimeImmutable('2026-10-14T12:00:00Z')); } catch (Throwable $e) { $failed=true; }
    assert($failed===true);
}

unlink($dir.'/evidence.json');
unlink($dir.'/trust.json');
rmdir($dir);
echo "P0 security risk evidence tests passed
";
