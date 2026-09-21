<?php

final class DkComplianceAlertPolicy
{
    public static function transition($lastEventType, $status, $deploymentId, $checkType, array $reasonCodes)
    {
        if (!in_array($status, array('ok', 'failed', 'error'), true)) {
            throw new RuntimeException('Unsupported compliance-check status');
        }
        $fingerprint = hash('sha256', (string) $deploymentId.'|'.(string) $checkType);
        sort($reasonCodes, SORT_STRING);

        if ($status === 'ok') {
            if ($lastEventType !== 'opened') {
                return null;
            }
            return array(
                'fingerprint' => $fingerprint,
                'event_type' => 'recovered',
                'severity' => 'info',
                'reason_codes' => array(),
            );
        }

        if ($lastEventType === 'opened') {
            return null;
        }

        return array(
            'fingerprint' => $fingerprint,
            'event_type' => 'opened',
            'severity' => $status === 'error' ? 'critical' : 'high',
            'reason_codes' => array_values(array_unique($reasonCodes)),
        );
    }
}
