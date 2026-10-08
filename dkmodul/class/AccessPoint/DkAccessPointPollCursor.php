<?php

final class DkAccessPointPollCursor
{
    private $value;

    public function __construct($value = null)
    {
        $this->value = $value === null ? null : trim((string) $value);
    }

    public function value() { return $this->value; }
}
