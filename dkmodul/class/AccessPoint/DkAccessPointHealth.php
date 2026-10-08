<?php

final class DkAccessPointHealth
{
    private $connected;
    private $authenticated;
    private $serviceAvailable;
    private $message;

    public function __construct($connected, $authenticated, $serviceAvailable, $message = null)
    {
        foreach (array($connected, $authenticated, $serviceAvailable) as $value) {
            if (!is_bool($value)) {
                throw new InvalidArgumentException('Access Point health flags must be boolean');
            }
        }

        $this->connected = $connected;
        $this->authenticated = $authenticated;
        $this->serviceAvailable = $serviceAvailable;
        $this->message = $message === null ? null : trim((string) $message);
    }

    public function connected() { return $this->connected; }
    public function authenticated() { return $this->authenticated; }
    public function serviceAvailable() { return $this->serviceAvailable; }
    public function message() { return $this->message; }
}
