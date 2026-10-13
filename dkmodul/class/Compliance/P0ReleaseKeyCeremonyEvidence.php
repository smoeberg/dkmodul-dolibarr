<?php

final class DkP0ReleaseKeyCeremonyEvidence
{
    public static function loadSigned($path, $trustStorePath, $expectedTrustStoreSha256, DateTimeImmutable $now)
    {
        if (!is_string($path) || trim($path) === '' || !is_readable($path)) {
            throw new RuntimeException('P0 release key ceremony evidence is required');
        }
        if (!is_string($trustStorePath) || trim($trustStorePath) === '' || !is_readable($trustStorePath)) {
            throw new RuntimeException('P0 release key ceremony evidence trust store is required');
        }

        $envelope=self::readJson($path);
        $trustStore=self::readJson($trustStorePath);
        if (($envelope['schema_version'] ?? null)!==1
            || !is_string($envelope['signed_payload'] ?? null)
            || !is_array($envelope['signature'] ?? null)) {
            throw new RuntimeException('Invalid P0 release key ceremony evidence envelope');
        }
        $actualTrustStoreSha256=hash_file('sha256',$trustStorePath);
        if (!is_string($expectedTrustStoreSha256) || !is_string($actualTrustStoreSha256)
            || !hash_equals($expectedTrustStoreSha256,$actualTrustStoreSha256)) {
            throw new RuntimeException('P0 release key ceremony trust store does not match the product manifest');
        }

        $signature=$envelope['signature'];
        if (($signature['algorithm'] ?? null)!=='RSA-SHA256'
            || !is_string($signature['key_id'] ?? null)
            || !is_string($signature['value'] ?? null)) {
            throw new RuntimeException('Invalid P0 release key ceremony signature metadata');
        }
        $payloadJson=base64_decode($envelope['signed_payload'],true);
        $signatureBytes=base64_decode($signature['value'],true);
        if ($payloadJson===false || $signatureBytes===false) {
            throw new RuntimeException('P0 release key ceremony evidence contains invalid base64');
        }
        $publicKey=self::trustedPublicKey($trustStore,$signature['key_id']);
        if (openssl_verify($payloadJson,$signatureBytes,$publicKey,OPENSSL_ALGO_SHA256)!==1) {
            throw new RuntimeException('P0 release key ceremony evidence signature verification failed');
        }
        $data=json_decode($payloadJson,true);
        if (!is_array($data) || json_last_error()!==JSON_ERROR_NONE) {
            throw new RuntimeException('P0 release key ceremony evidence payload must be valid JSON');
        }
        self::assertValid($data,$now);
        return array('evidence'=>$data,'evidence_sha256'=>hash('sha256',$payloadJson),'signing_key_id'=>$signature['key_id']);
    }

    public static function assertMatchesManifest(array $evidence,array $releaseSigning)
    {
        foreach (array('key_id','public_key_sha256') as $field) {
            if (!is_string($releaseSigning[$field] ?? null) || trim($releaseSigning[$field])==='') {
                throw new RuntimeException('Release signing manifest field is required: '.$field);
            }
            if ((string)$evidence[$field] !== (string)$releaseSigning[$field]) {
                throw new RuntimeException('Release signing ceremony evidence does not match manifest: '.$field);
            }
        }
        return true;
    }

    private static function assertValid(array $data,DateTimeImmutable $now)
    {
        foreach (array('key_id','public_key_sha256','approved_owner','approved_by','ceremony_at','custody_reference','rotation_policy','revocation_policy','reviewed_at') as $field) {
            if (!is_string($data[$field] ?? null) || trim($data[$field])==='') {
                throw new RuntimeException('P0 release key ceremony evidence field is required: '.$field);
            }
        }
        if (!preg_match('/^[a-f0-9]{64}$/',strtolower($data['public_key_sha256']))) {
            throw new RuntimeException('P0 release key ceremony public-key fingerprint must be SHA-256');
        }
        foreach (array('ceremony_at','reviewed_at') as $field) {
            $date=DateTimeImmutable::createFromFormat('!Y-m-d',$data[$field]);
            if (!$date || $date->format('Y-m-d')!==$data[$field] || $date>$now) {
                throw new RuntimeException('P0 release key ceremony evidence has invalid '.$field);
            }
        }
        return true;
    }

    private static function trustedPublicKey(array $trustStore,$keyId)
    {
        if (($trustStore['schema_version'] ?? null)!==1 || !is_array($trustStore['keys'] ?? null)) {
            throw new RuntimeException('Invalid P0 release key ceremony trust store');
        }
        foreach ($trustStore['keys'] as $key) {
            if (($key['key_id'] ?? null)===$keyId
                && ($key['algorithm'] ?? null)==='RSA-SHA256'
                && ($key['status'] ?? null)==='active'
                && is_string($key['public_key_pem'] ?? null)) {
                return $key['public_key_pem'];
            }
        }
        throw new RuntimeException('P0 release key ceremony evidence signer is not trusted or active');
    }

    private static function readJson($path)
    {
        $data=json_decode(file_get_contents($path),true);
        if (!is_array($data) || json_last_error()!==JSON_ERROR_NONE) {
            throw new RuntimeException('P0 release key ceremony evidence JSON is invalid');
        }
        return $data;
    }
}
