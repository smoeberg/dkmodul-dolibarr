<?php

class DkSaftValidator
{
    public function validateXml($xml, $xsdPath)
    {
        if (!is_file($xsdPath)) {
            throw new InvalidArgumentException('SAF-T XSD not found: '.$xsdPath);
        }

        $previous = libxml_use_internal_errors(true);
        libxml_clear_errors();

        try {
            $dom = new DOMDocument();
            $dom->preserveWhiteSpace = false;

            if (!$dom->loadXML((string) $xml, LIBXML_NONET)) {
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
