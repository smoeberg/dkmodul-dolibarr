<?php

require_once __DIR__.'/P0ReleaseGate.php';

final class DkP0ReleaseReportSigner
{
    public static function sign(array $report, $privateKeyPem, $keyId)
    {
        if (!is_string($privateKeyPem) || trim($privateKeyPem) === '') {
            throw new RuntimeException('P0 release report signing requires a private key');
        }
        if (!is_string($keyId) || trim($keyId) === '') {
            throw new RuntimeException('P0 release report signing requires a key id');
        }
        if (!function_exists('openssl_sign')) {
            throw new RuntimeException('OpenSSL signing support is required');
        }

        $payload = DkP0ReleaseGate::canonicalJson($report);
        $signature = '';
        if (!openssl_sign($payload, $signature, $privateKeyPem, OPENSSL_ALGO_SHA256)) {
            throw new RuntimeException('Unable to sign P0 release report');
        }

        return array(
            'schema_version' => 1,
            'signature_type' => 'openssl-rsa-sha256',
            'key_id' => trim($keyId),
            'report_sha256' => (string) ($report['report_sha256'] ?? ''),
            'signature_base64' => base64_encode($signature),
        );
    }

    public static function verify(array $report, array $signature, $publicKeyPem)
    {
        if (!is_string($publicKeyPem) || trim($publicKeyPem) === '') {
            return false;
        }
        if (!function_exists('openssl_verify')) {
            return false;
        }

        if (($signature['signature_type'] ?? null) !== 'openssl-rsa-sha256') {
            return false;
        }
        if (!isset($signature['report_sha256']) || $signature['report_sha256'] !== ($report['report_sha256'] ?? null)) {
            return false;
        }
        if (!isset($signature['signature_base64']) || !is_string($signature['signature_base64'])) {
            return false;
        }

        $decoded = base64_decode($signature['signature_base64'], true);
        if ($decoded === false) {
            return false;
        }

        return openssl_verify(
            DkP0ReleaseGate::canonicalJson($report),
            $decoded,
            $publicKeyPem,
            OPENSSL_ALGO_SHA256
        ) === 1;
    }
}
