<?php

/**
 * Immutable, array-readable base for canonical master-data records.
 *
 * ArrayAccess keeps the SAF-T mapper concise while offsetSet/offsetUnset make
 * accidental mutation fail immediately. Arrays returned by offsetGet use
 * PHP copy-on-write semantics and cannot mutate the record's private state.
 */
abstract readonly class DkCanonicalRecord implements ArrayAccess, JsonSerializable
{
    private array $values;

    final protected function setValues(array $values): void
    {
        $this->values = $values;
    }

    final public function offsetExists(mixed $offset): bool
    {
        return array_key_exists((string) $offset, $this->values);
    }

    final public function offsetGet(mixed $offset): mixed
    {
        return $this->values[(string) $offset] ?? null;
    }

    final public function offsetSet(mixed $offset, mixed $value): void
    {
        throw new LogicException('Canonical records are immutable');
    }

    final public function offsetUnset(mixed $offset): void
    {
        throw new LogicException('Canonical records are immutable');
    }

    final public function toArray(): array
    {
        return $this->values;
    }

    final public function jsonSerialize(): array
    {
        return $this->values;
    }

    final protected static function required(array $data, string $field): string
    {
        $value = trim((string) ($data[$field] ?? ''));
        if ($value === '') {
            throw new InvalidArgumentException('Canonical '.$field.' is required');
        }

        return $value;
    }
}
