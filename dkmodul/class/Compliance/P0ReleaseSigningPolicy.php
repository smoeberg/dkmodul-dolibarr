<?php

final class DkP0ReleaseSigningPolicy
{
    public static function assertApproved(
        DkP0ReleaseSigningKeyProvider $provider,
        $approvedKeyId,
        $approvedPublicKeySha256
    ) {
        if (!is_string($approvedKeyId) || trim($approvedKeyId) === '') {
            throw new RuntimeException('Approved P0 release signing key id is required');
        }
        if (!is_string($approvedPublicKeySha256)
            || !preg_match('/^[a-f0-9]{64}$/', strtolower($approvedPublicKeySha256))) {
            throw new RuntimeException('Approved P0 release signing public-key fingerprint is required');
        }

        if ($provider->keyId() !== trim($approvedKeyId)) {
            throw new RuntimeException('Configured P0 release signing key id is not approved');
        }
        if (!hash_equals(strtolower($approvedPublicKeySha256), strtolower($provider->publicKeySha256()))) {
            throw new RuntimeException('Configured P0 release signing public-key fingerprint is not approved');
        }

        return true;
    }

    public static function assertSignatureMatchesProvider(
        array $report,
        array $signature,
        DkP0ReleaseSigningKeyProvider $provider
    ) {
        if (($signature['key_id'] ?? null) !== $provider->keyId()) {
            throw new RuntimeException('P0 release report signature key id does not match configured provider');
        }
        if (!DkP0ReleaseReportSigner::verify($report, $signature, $provider->publicKeyPem())) {
            throw new RuntimeException('P0 release report signature is invalid');
        }

        return true;
    }
}
