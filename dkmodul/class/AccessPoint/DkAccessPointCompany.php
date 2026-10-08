<?php

final class DkAccessPointCompany
{
    private $organizationNumber;
    private $name;
    private $participantId;

    public function __construct($organizationNumber, $name, $participantId = null)
    {
        if (!is_string($organizationNumber) || trim($organizationNumber) === '') {
            throw new InvalidArgumentException('Company organization number is required');
        }
        if (!is_string($name) || trim($name) === '') {
            throw new InvalidArgumentException('Company name is required');
        }

        $this->organizationNumber = trim($organizationNumber);
        $this->name = trim($name);
        $this->participantId = $participantId === null ? null : trim((string) $participantId);
    }

    public function organizationNumber()
    {
        return $this->organizationNumber;
    }

    public function name()
    {
        return $this->name;
    }

    public function participantId()
    {
        return $this->participantId;
    }
}
