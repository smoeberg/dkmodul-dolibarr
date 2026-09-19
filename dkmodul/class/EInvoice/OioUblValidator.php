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

    /** Validate the XML report produced by applying the official compiled Schematron. */
    public function validateSchematronResult(string $resultXml): void
    {
        $result = new DOMDocument();
        if (!$result->loadXML($resultXml, LIBXML_NONET)) {
            throw new InvalidArgumentException('Official OIOUBL Schematron returned invalid XML');
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
