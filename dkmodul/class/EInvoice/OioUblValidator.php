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

    /** Official compiled Schematron XSLT 2.0 returns Error elements for violations. */
    public function validateSchematron(string $xml, string $xsltPath, string $saxonJar = '/usr/share/java/Saxon-HE.jar'): void
    {
        if (!is_file($xsltPath) || !is_file($saxonJar)) {
            throw new RuntimeException('OIOUBL Schematron validation requires the official XSLT and Saxon-HE');
        }

        $inputPath = tempnam(sys_get_temp_dir(), 'dk-oioubl-in-');
        $outputPath = tempnam(sys_get_temp_dir(), 'dk-oioubl-out-');
        if ($inputPath === false || $outputPath === false || file_put_contents($inputPath, $xml) === false) {
            throw new RuntimeException('Unable to prepare OIOUBL Schematron validation');
        }

        try {
            $command = array('java', '-jar', $saxonJar, '-s:'.$inputPath, '-xsl:'.$xsltPath, '-o:'.$outputPath);
            $pipes = array();
            $process = proc_open($command, array(1 => array('pipe', 'w'), 2 => array('pipe', 'w')), $pipes);
            if (!is_resource($process)) {
                throw new RuntimeException('Unable to start Saxon-HE for OIOUBL Schematron validation');
            }
            $stdout = stream_get_contents($pipes[1]);
            $stderr = stream_get_contents($pipes[2]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            $exitCode = proc_close($process);
            if ($exitCode !== 0) {
                throw new RuntimeException('Unable to execute official OIOUBL Schematron: '.trim($stderr ?: $stdout));
            }

            $result = new DOMDocument();
            if (!$result->load($outputPath, LIBXML_NONET)) {
                throw new RuntimeException('Official OIOUBL Schematron returned invalid XML');
            }
        } finally {
            @unlink($inputPath);
            @unlink($outputPath);
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
