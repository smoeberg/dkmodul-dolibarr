<?php

/**
 * Validates the external signature/custody envelope attached to evidence.
 */
final class DkCustodyEvidence
{
    /**
     * @param array<string,mixed> $evidence
     * @param array<string,mixed> $signature
     * @return array<string,mixed>
     */
    public static function attach(array $evidence, array $signature): array
    {
        if (!isset($signature['algorithm'], $signature['keyId'], $signature['signature'])) {
            throw new InvalidArgumentException('Evidence signature envelope is incomplete');
        }

        $algorithm = strtoupper(trim((string) $signature['algorithm']));
        $keyId = trim((string) $signature['keyId']);
        $value = trim((string) $signature['signature']);

        if ($algorithm === '' || $keyId === '' || $value === '') {
            throw new InvalidArgumentException('Evidence signature envelope is invalid');
        }
        if (strlen($keyId) > 255 || strlen($value) > 4096) {
            throw new InvalidArgumentException('Evidence signature envelope is oversized');
        }

        $result = $evidence;
        $result['custody'] = array(
            'algorithm' => $algorithm,
            'keyId' => $keyId,
            'signature' => $value,
        );

        return $result;
    }
}
