<?php
require_once __DIR__.'/../../dkmodul/class/Compliance/P0ReleaseKeyCeremonyEvidence.php';

$dir=sys_get_temp_dir().'/dkmodul-p0-key-ceremony-'.bin2hex(random_bytes(4));
mkdir($dir,0700,true);
$key=openssl_pkey_new(array('private_key_type'=>OPENSSL_KEYTYPE_RSA,'private_key_bits'=>2048));
openssl_pkey_export($key,$privatePem);
$details=openssl_pkey_get_details($key);
$publicPem=$details['key'];
$keyId='ceremony-test-key';
$trustStore=array('schema_version'=>1,'keys'=>array(array('key_id'=>$keyId,'algorithm'=>'RSA-SHA256','status'=>'active','public_key_pem'=>$publicPem)));
file_put_contents($dir.'/trust.json',json_encode($trustStore,JSON_UNESCAPED_SLASHES));
$payload=array(
    'schema_version'=>1,
    'key_id'=>$keyId,
    'public_key_sha256'=>hash('sha256',$publicPem),
    'approved_owner'=>'approved owner',
    'approved_by'=>'independent approver',
    'ceremony_at'=>'2026-10-10',
    'custody_reference'=>'HSM-CUSTODY-001',
    'rotation_policy'=>'rotate on compromise or scheduled expiry',
    'revocation_policy'=>'revoke on compromise or owner withdrawal',
    'reviewed_at'=>'2026-10-12'
);
$payloadJson=json_encode($payload,JSON_UNESCAPED_SLASHES);
openssl_sign($payloadJson,$signature,$privatePem,OPENSSL_ALGO_SHA256);
$envelope=array('schema_version'=>1,'signed_payload'=>base64_encode($payloadJson),'signature'=>array('key_id'=>$keyId,'algorithm'=>'RSA-SHA256','value'=>base64_encode($signature)));
file_put_contents($dir.'/evidence.json',json_encode($envelope,JSON_UNESCAPED_SLASHES));

$result=DkP0ReleaseKeyCeremonyEvidence::loadSigned($dir.'/evidence.json',$dir.'/trust.json',hash_file('sha256',$dir.'/trust.json'),new DateTimeImmutable('2026-10-13'));
assert($result['evidence']['key_id']===$keyId);
DkP0ReleaseKeyCeremonyEvidence::assertMatchesManifest($result['evidence'],array('key_id'=>$keyId,'public_key_sha256'=>hash('sha256',$publicPem)));

$mismatch=$result['evidence'];
$mismatch['public_key_sha256']=str_repeat('0',64);
$failed=false;
try { DkP0ReleaseKeyCeremonyEvidence::assertMatchesManifest($mismatch,array('key_id'=>$keyId,'public_key_sha256'=>hash('sha256',$publicPem))); } catch (Throwable $e) { $failed=true; }
assert($failed===true);

$expired=$result['evidence'];
$expired['reviewed_at']='2027-01-01';
$payloadJson=json_encode($expired,JSON_UNESCAPED_SLASHES);
openssl_sign($payloadJson,$signature,$privatePem,OPENSSL_ALGO_SHA256);
file_put_contents($dir.'/evidence.json',json_encode(array('schema_version'=>1,'signed_payload'=>base64_encode($payloadJson),'signature'=>array('key_id'=>$keyId,'algorithm'=>'RSA-SHA256','value'=>base64_encode($signature))),JSON_UNESCAPED_SLASHES));
$failed=false;
try { DkP0ReleaseKeyCeremonyEvidence::loadSigned($dir.'/evidence.json',$dir.'/trust.json',hash_file('sha256',$dir.'/trust.json'),new DateTimeImmutable('2026-10-13')); } catch (Throwable $e) { $failed=true; }
assert($failed===true);

echo "P0 release key ceremony evidence tests passed\n";
