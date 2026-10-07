<?php

final class DkP0ReleaseSigningKeyProvider
{
    private $keyId;
    private $privateKeyPem;
    private $publicKeyPem;

    public function __construct($keyId, $privateKeyPem)
    {
        if (!is_string($keyId) || trim($keyId) === '') {
            throw new RuntimeException('P0 release signing key id is required');
        }
        if (!is_string($privateKeyPem) || trim($privateKeyPem) === '') {
            throw new RuntimeException('P0 release signing private key is required');
        }
        if (!function_exists('openssl_pkey_get_private') || !function_exists('openssl_pkey_get_details')) {
            throw new RuntimeException('OpenSSL key support is required');
        }

        $key = openssl_pkey_get_private($privateKeyPem);
        if ($key === false) {
            throw new RuntimeException('Invalid P0 release signing private key');
        }
        $details = openssl_pkey_get_details($key);
        if (!is_array($details) || ($details['type'] ?? null) !== OPENSSL_KEYTYPE_RSA || empty($details['key'])) {
            throw new RuntimeException('P0 release signing key must be RSA');
        }

        $this->keyId = trim($keyId);
        $this->privateKeyPem = $privateKeyPem;
        $this->publicKeyPem = $details['key'];
    }

    public static function fromEnvironment(array $environment = null)
    {
        $environment = $environment === null ? $_ENV : $environment;
        $keyId = $environment['DKMODUL_P0_SIGNING_KEY_ID'] ?? getenv('DKMODUL_P0_SIGNING_KEY_ID');
        $path = $environment['DKMODUL_P0_SIGNING_PRIVATE_KEY_PATH'] ?? getenv('DKMODUL_P0_SIGNING_PRIVATE_KEY_PATH');

        if (is_string($path) && trim($path) !== '') {
            $privateKeyPem = @file_get_contents($path);
            if ($privateKeyPem === false) {
                throw new RuntimeException('Unable to read P0 release signing private key file');
            }
        } else {
            $privateKeyPem = $environment['DKMODUL_P0_SIGNING_PRIVATE_KEY'] ?? getenv('DKMODUL_P0_SIGNING_PRIVATE_KEY');
        }

        return new self($keyId, $privateKeyPem);
    }

    public function keyId() { return $this->keyId; }

    public function privateKeyPem() { return $this->privateKeyPem; }

    public function publicKeyPem() { return $this->publicKeyPem; }

    public function publicKeySha256()
    {
        return hash('sha256', $this->publicKeyPem);
    }
}
