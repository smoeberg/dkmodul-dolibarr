<?php

/**
 * Signing/custody boundary for operational evidence.
 *
 * Implementations must use an external key/custody mechanism. This interface
 * deliberately contains no private-key storage or cryptographic provider.
 */
interface DkEvidenceSigner
{
    /**
     * @param array<string,mixed> $evidence
     * @return array{algorithm:string,keyId:string,signature:string}
     */
    public function sign(array $evidence): array;
}
