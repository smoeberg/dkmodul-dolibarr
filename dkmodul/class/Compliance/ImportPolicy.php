<?php

/**
 * Central policy for generic Dolibarr import entry points.
 */
final class DkComplianceImportPolicy
{
    public static function assertAllowed($datatoimport, $complianceMode)
    {
        if (!$complianceMode) {
            return;
        }

        if (preg_match('/^accounting(?:_|$)/i', (string) $datatoimport)) {
            throw new RuntimeException(
                'DK compliance: generic Dolibarr accounting import is disabled; use the controlled DK importer'
            );
        }
    }
}
