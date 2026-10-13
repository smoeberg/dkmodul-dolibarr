<?php

class DkSaftValidator
{
    public const MAX_XML_BYTES = 10 * 1024 * 1024;

    public function validateXml($xml, $xsdPath)
    {
        if (!is_file($xsdPath)) {
            throw new InvalidArgumentException('SAF-T XSD not found: '.$xsdPath);
        }
        if (!is_string($xml) || $xml === '') {
            throw new InvalidArgumentException('SAF-T XML must be a non-empty string');
        }
        if (strlen($xml) > self::MAX_XML_BYTES) {
            throw new InvalidArgumentException(
                'SAF-T XML exceeds the maximum accepted size of '.self::MAX_XML_BYTES.' bytes'
            );
        }
        $this->assertSafeXml($xml);

        $previous = libxml_use_internal_errors(true);
        libxml_clear_errors();

        try {
            $dom = new DOMDocument();
            $dom->preserveWhiteSpace = false;

            if (!$dom->loadXML($xml, LIBXML_NONET | LIBXML_NOBLANKS)) {
                throw new RuntimeException('Generated SAF-T is not well-formed XML: '.$this->formatErrors());
            }

            if (!$dom->schemaValidate($xsdPath)) {
                throw new RuntimeException('Generated SAF-T does not validate against official XSD: '.$this->formatErrors());
            }

            return true;
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }

    private function assertSafeXml($xml)
    {
        if (preg_match('/<!\s*DOCTYPE\b/i', $xml) || preg_match('/<!\s*ENTITY\b/i', $xml)) {
            throw new InvalidArgumentException(
                'SAF-T XML must not contain DOCTYPE or ENTITY declarations'
            );
        }
    }

    private function formatErrors()
    {
        $messages = array();

        foreach (libxml_get_errors() as $error) {
            $message = trim($error->message);
            if ($error->line) {
                $message .= ' (line '.$error->line.')';
            }
            $messages[] = $message;
        }

        return $messages ? implode('; ', $messages) : 'unknown XML validation error';
    }
}
