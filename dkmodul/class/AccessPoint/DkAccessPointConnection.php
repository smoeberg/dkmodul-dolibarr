<?php

final class DkAccessPointConnection
{
    private $adapterId;
    private $configuration;

    public function __construct($adapterId, array $configuration)
    {
        if (!is_string($adapterId) || trim($adapterId) === '') {
            throw new InvalidArgumentException('Access Point adapter id is required');
        }

        $this->adapterId = trim($adapterId);
        $this->configuration = $configuration;
    }

    public function adapterId()
    {
        return $this->adapterId;
    }

    public function configuration()
    {
        return $this->configuration;
    }
}
