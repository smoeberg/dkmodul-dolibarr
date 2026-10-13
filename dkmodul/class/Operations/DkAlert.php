<?php

/**
 * Immutable structured alert payload.
 */
final class DkAlert
{
    /**
     * @param array<string,mixed> $details
     */
    public static function create(
        string $control,
        string $severity,
        string $message,
        int $actorId,
        array $details = array()
    ): array {
        $control = trim($control);
        $severity = strtolower(trim($severity));
        $message = trim($message);

        if ($control === '' || $message === '') {
            throw new InvalidArgumentException('Alert control and message are required');
        }
        if (!in_array($severity, array('critical', 'error', 'warning'), true)) {
            throw new InvalidArgumentException('Alert severity is invalid');
        }
        if ($actorId <= 0) {
            throw new InvalidArgumentException('Alert actor is required');
        }

        return array(
            'schemaVersion' => '1',
            'control' => $control,
            'severity' => $severity,
            'message' => $message,
            'actorId' => $actorId,
            'createdAt' => gmdate('Y-m-d\TH:i:s\Z'),
            'details' => $details,
        );
    }
}
