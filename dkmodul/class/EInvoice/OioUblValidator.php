<?php

final class DkOioUblValidator
{
    public function validateXsd(string $xml, string $schemaPath): void
    {
        $previous = libxml_use_internal_errors(true);
        libxml_clear_errors();
        $dom = new DOMDocument();
        if (!$dom->loadXML($xml, LIBXML_NONET)) {
            $message = $this->errors();
            libxml_use_internal_errors($previous);
            throw new InvalidArgumentException('OIOUBL XML is not well formed: '.$message);
        }
        if (!$dom->schemaValidate($schemaPath)) {
            $message = $this->errors();
            libxml_use_internal_errors($previous);
            throw new InvalidArgumentException('OIOUBL XML failed official XSD: '.$message);
        }
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
    }

    /** Official compiled Schematron XSL returns Error elements for violations. */
    public function validateSchematron(string $xml, string $xsltPath): void
    {
        if (!class_exists('XSLTProcessor')) {
            throw new RuntimeException('OIOUBL Schematron validation requires the PHP XSL extension');
        }
        $xmlDom = new DOMDocument();
        $xslDom = new DOMDocument();
        if (!$xmlDom->loadXML($xml, LIBXML_NONET) || !$xslDom->load($xsltPath, LIBXML_NONET)) {
            throw new InvalidArgumentException('Unable to load OIOUBL Schematron inputs');
        }
        $processor = new XSLTProcessor();
        $processor->importStylesheet($xslDom);
        $result = $processor->transformToDoc($xmlDom);
        if (!$result) {
            throw new RuntimeException('Unable to execute official OIOUBL Schematron');
        }
        $errors = $result->getElementsByTagName('Error');
        if ($errors->length > 0) {
            $messages = array();
            foreach ($errors as $error) {
                $messages[] = trim($error->textContent);
                if (count($messages) === 5) break;
            }
            throw new InvalidArgumentException('OIOUBL XML failed official Schematron: '.implode(' | ', $messages));
        }
    }

    private function errors(): string
    {
        $messages = array();
        foreach (libxml_get_errors() as $error) {
            $messages[] = trim($error->message).' (line '.$error->line.')';
        }
        libxml_clear_errors();
        return implode('; ', array_slice($messages, 0, 5));
    }
}
