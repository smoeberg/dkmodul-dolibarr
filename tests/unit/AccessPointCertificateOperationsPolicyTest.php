<?php
require_once __DIR__.'/../../dkmodul/class/Compliance/DkAccessPointCertificateOperationsPolicy.php';

$evidence=array(
    'certificate_serial'=>'NH-CERT-2026-001',
    'issuer'=>'NemHandel/Peppol certification authority',
    'renewal_contact'=>'cert-operations@example.invalid',
    'revocation_contact'=>'revocation@example.invalid',
    'last_rotation_at'=>'2026-09-01T10:00:00Z',
    'revocation_tested_at'=>'2026-10-01T10:00:00Z',
    'evidence_reference'=>'evidence/access-point/certificate-operations.json',
);
$result=DkAccessPointCertificateOperationsPolicy::validate($evidence,new DateTimeImmutable('2026-10-13T12:00:00Z'));
assert($result['compliant']===true);

$future=$evidence;
$future['revocation_tested_at']='2026-12-01T10:00:00Z';
$result=DkAccessPointCertificateOperationsPolicy::validate($future,new DateTimeImmutable('2026-10-13T12:00:00Z'));
assert(in_array('access-point-certificate-operations-future-revocation_tested_at',$result['reasons'],true));

$invalid=$evidence;
$invalid['last_rotation_at']='2026-09-01T10:00:00+02:00';
$result=DkAccessPointCertificateOperationsPolicy::validate($invalid,new DateTimeImmutable('2026-10-13T12:00:00Z'));
assert(in_array('access-point-certificate-operations-invalid-last_rotation_at',$result['reasons'],true));

$missing=$evidence;
unset($missing['revocation_contact']);
$result=DkAccessPointCertificateOperationsPolicy::validate($missing,new DateTimeImmutable('2026-10-13T12:00:00Z'));
assert(in_array('access-point-certificate-operations-missing-revocation_contact',$result['reasons'],true));

echo "Access-point certificate operations policy tests passed\n";
