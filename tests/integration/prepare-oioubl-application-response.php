<?php

$source = '/tmp/OIOUBL_ApplicationResponse_v2.1.xml';
$invoicePath = '/tmp/DKVAT-1.xml';
$target = '/tmp/DKVAT-1-ApplicationResponse.xml';
$dom = new DOMDocument();
$invoice = new DOMDocument();
if (!$dom->load($source, LIBXML_NONET) || !$invoice->load($invoicePath, LIBXML_NONET)) {
    throw new RuntimeException('Official application-response fixture or outbound invoice is missing');
}
$xp = new DOMXPath($dom);
$xp->registerNamespace('doc', 'urn:oasis:names:specification:ubl:schema:xsd:ApplicationResponse-2');
$xp->registerNamespace('cbc', 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2');
$xp->registerNamespace('cac', 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2');
$invoiceXp = new DOMXPath($invoice);
$invoiceXp->registerNamespace('doc', 'urn:oasis:names:specification:ubl:schema:xsd:Invoice-2');
$invoiceXp->registerNamespace('cbc', 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2');
$invoiceUuid = trim((string) $invoiceXp->evaluate('string(/doc:Invoice/cbc:UUID)'));
if ($invoiceUuid === '') throw new RuntimeException('Outbound invoice UUID is missing');

$set = static function (string $query, string $value) use ($xp): void {
    $node = $xp->query($query)->item(0);
    if (!$node) throw new RuntimeException('Official application-response fixture is missing '.$query);
    $node->nodeValue = $value;
};
$base = '/doc:ApplicationResponse';
$set($base.'/cbc:ID', 'APR-DKVAT-1');
$set($base.'/cbc:UUID', '11648352-3c61-4f86-a949-28ecfbc24ef1');
$set($base.'/cbc:IssueDate', '2026-09-20');
$set($base.'/cac:SenderParty/cbc:EndpointID', '5790001968502');
$set($base.'/cac:SenderParty/cbc:EndpointID/@schemeID', 'GLN');
$set($base.'/cac:ReceiverParty/cbc:EndpointID', 'DK12345678');
$set($base.'/cac:ReceiverParty/cbc:EndpointID/@schemeID', 'DK:CVR');
$set($base.'/cac:DocumentResponse/cac:Response/cbc:ReferenceID', 'DKVAT-1');
$set($base.'/cac:DocumentResponse/cac:Response/cbc:ResponseCode', 'BusinessAccept');
$set($base.'/cac:DocumentResponse/cac:Response/cbc:Description', 'Invoice accepted');
$set($base.'/cac:DocumentResponse/cac:DocumentReference/cbc:ID', 'DKVAT-1');
$set($base.'/cac:DocumentResponse/cac:DocumentReference/cbc:UUID', $invoiceUuid);
$set($base.'/cac:DocumentResponse/cac:DocumentReference/cbc:IssueDate', '2026-09-18');
$set($base.'/cac:DocumentResponse/cac:DocumentReference/cbc:DocumentTypeCode', 'Invoice');

if ($dom->save($target) === false) throw new RuntimeException('Unable to write controlled OIOUBL application response');
echo "Prepared controlled OIOUBL application response\n";
