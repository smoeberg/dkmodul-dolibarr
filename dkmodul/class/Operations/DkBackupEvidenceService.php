<?php

/**
 * Append-only operational evidence for backups and restore tests.
 *
 * This class does not perform the backup itself. It records independently
 * verifiable evidence that a backup artifact was produced and that a restore
 * test was completed against the recorded artifact fingerprint.
 */
final class DkBackupEvidenceService
{
    public const SCHEMA_VERSION = '1';

    private string $storageRoot;

    public function __construct(string $storageRoot)
    {
        $this->storageRoot = rtrim($storageRoot, DIRECTORY_SEPARATOR);
        if ($this->storageRoot === '') {
            throw new InvalidArgumentException('Backup evidence storage root is required');
        }
    }

    public function record(
        string $backupId,
        string $scope,
        int $artifactBytes,
        string $artifactSha256,
        string $backupCompletedAt,
        string $restoreTestId,
        string $restoreStatus,
        int $actorId
    ): array {
        $backupId = trim($backupId);
        $scope = trim($scope);
        $artifactSha256 = strtolower(trim($artifactSha256));
        $backupCompletedAt = trim($backupCompletedAt);
        $restoreTestId = trim($restoreTestId);
        $restoreStatus = strtolower(trim($restoreStatus));

        if ($backupId === '' || $scope === '' || $restoreTestId === '') {
            throw new InvalidArgumentException('Backup id, scope and restore test id are required');
        }
        if ($artifactBytes < 0) {
            throw new InvalidArgumentException('Backup artifact size must not be negative');
        }
        if (!preg_match('/^[a-f0-9]{64}$/', $artifactSha256)) {
            throw new InvalidArgumentException('Backup artifact SHA-256 is invalid');
        }
        $completed = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i:s\Z', $backupCompletedAt);
        if (!$completed || $completed->format('Y-m-d\TH:i:s\Z') !== $backupCompletedAt) {
            throw new InvalidArgumentException('Backup completion time must be UTC ISO-8601 without fractional seconds');
        }
        if (!in_array($restoreStatus, array('passed', 'failed'), true)) {
            throw new InvalidArgumentException('Restore test status must be passed or failed');
        }
        if ($actorId <= 0) {
            throw new InvalidArgumentException('Evidence actor is required');
        }

        $evidence = array(
            'schemaVersion' => self::SCHEMA_VERSION,
            'backupId' => $backupId,
            'scope' => $scope,
            'artifactBytes' => $artifactBytes,
            'artifactSha256' => $artifactSha256,
            'backupCompletedAt' => $backupCompletedAt,
            'restoreTestId' => $restoreTestId,
            'restoreStatus' => $restoreStatus,
            'actorId' => $actorId,
            'recordedAt' => gmdate('Y-m-d\TH:i:s\Z'),
        );
        $json = json_encode($evidence, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $evidenceHash = hash('sha256', $json);
        $evidence['evidenceSha256'] = $evidenceHash;
        $json = json_encode($evidence, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        $directory = $this->storageRoot.DIRECTORY_SEPARATOR.substr($evidenceHash, 0, 2);
        if (!is_dir($directory) && !mkdir($directory, 0750, true) && !is_dir($directory)) {
            throw new RuntimeException('Unable to create backup evidence directory');
        }
        $target = $directory.DIRECTORY_SEPARATOR.$evidenceHash.'.json';
        $temporary = $target.'.tmp.'.bin2hex(random_bytes(6));
        if (file_put_contents($temporary, $json, LOCK_EX) === false) {
            @unlink($temporary);
            throw new RuntimeException('Unable to write backup evidence');
        }
        chmod($temporary, 0440);

        if (@link($temporary, $target)) {
            @unlink($temporary);
        } else {
            @unlink($temporary);
            if (!is_file($target) || (string) file_get_contents($target) !== $json) {
                throw new RuntimeException('Unable to atomically create backup evidence');
            }
        }

        return array(
            'evidenceSha256' => $evidenceHash,
            'path' => $target,
            'record' => $evidence,
        );
    }

    public function verify(string $path): bool
    {
        if (!is_file($path) || !is_readable($path)) {
            return false;
        }
        $json = file_get_contents($path);
        if ($json === false) {
            return false;
        }
        try {
            $record = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            return false;
        }
        if (!is_array($record) || !isset($record['evidenceSha256'])) {
            return false;
        }

        $expected = $record['evidenceSha256'];
        unset($record['evidenceSha256']);
        try {
            $canonical = json_encode($record, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        } catch (JsonException $e) {
            return false;
        }
        return is_string($expected)
            && hash_equals($expected, hash('sha256', $canonical));
    }
}
