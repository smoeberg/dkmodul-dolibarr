<?php

final class DkInboundOioUblEnvelope
{
    public static function inspect(string $xml): array
    {
        if ($xml === '') {
            throw new InvalidArgumentException('Inbound document payload is empty');
        }

        $dom = new DOMDocument();
        $previous = libxml_use_internal_errors(true);
        try {
            if (!$dom->loadXML($xml, LIBXML_NONET | LIBXML_NOBLANKS)) {
                throw new RuntimeException('Inbound document is not valid XML');
            }

            $root = $dom->documentElement;
            $documentType = $root ? (string) $root->localName : '';
            if (!in_array($documentType, array('Invoice', 'CreditNote'), true)) {
                throw new RuntimeException('Inbound document must be an OIOUBL Invoice or CreditNote');
            }

            $xpath = new DOMXPath($dom);
            $xpath->registerNamespace('doc', 'urn:oasis:names:specification:ubl:schema:xsd:'.$documentType.'-2');
            $xpath->registerNamespace('cbc', 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2');
            $xpath->registerNamespace('cac', 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2');

            $endpoint = $xpath->query('/doc:'.$documentType.'/cac:AccountingSupplierParty/cac:Party/cbc:EndpointID')->item(0);
            $senderId = $endpoint ? trim((string) $endpoint->nodeValue) : '';
            $senderScheme = $endpoint && $endpoint->hasAttribute('schemeID')
                ? trim((string) $endpoint->getAttribute('schemeID'))
                : '';

            if ($senderId === '' || $senderScheme === '') {
                throw new RuntimeException('Inbound OIOUBL supplier EndpointID and schemeID are required');
            }

            return array(
                'documentType' => $documentType,
                'senderScheme' => $senderScheme,
                'senderId' => $senderId,
            );
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }
}
