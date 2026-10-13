<?php
require_once __DIR__.'/../../dkmodul/class/Compliance/DkAccessPointCertificationPolicy.php';
$manifest=array('certification_id'=>'NH-TEST-123','certificate_sha256'=>str_repeat('a',64),'valid_from'=>'2026-01-01','valid_until'=>'2027-01-01');
$evidence=array('certification_id'=>'NH-TEST-123','certificate_sha256'=>str_repeat('a',64),'status'=>'certified','valid_from'=>'2026-01-01','valid_until'=>'2027-01-01','evidence_reference'=>'evidence/access-point/certification.json','reviewed_by'=>'approved reviewer','reviewed_at'=>'2026-10-13');
$result=DkAccessPointCertificationPolicy::validate($manifest,$evidence,new DateTimeImmutable('2026-10-13')); assert($result['compliant']===true);
$result=DkAccessPointCertificationPolicy::validate($manifest,null,new DateTimeImmutable('2026-10-13')); assert(in_array('access-point-certification-evidence-missing',$result['reasons'],true));
$expired=$evidence; $expired['valid_until']='2026-01-01'; $result=DkAccessPointCertificationPolicy::validate($manifest,$expired,new DateTimeImmutable('2026-10-13')); assert(in_array('access-point-certification-evidence-expired-or-future',$result['reasons'],true));
$mismatch=$evidence; $mismatch['certification_id']='NH-OTHER'; $result=DkAccessPointCertificationPolicy::validate($manifest,$mismatch,new DateTimeImmutable('2026-10-13')); assert(in_array('access-point-certification-id-mismatch',$result['reasons'],true));
echo "Access-point certification lifecycle policy tests passed\n";
