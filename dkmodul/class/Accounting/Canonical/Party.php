<?php

require_once __DIR__.'/Record.php';

final readonly class DkCanonicalParty extends DkCanonicalRecord
{
    public function __construct(array $data)
    {
        $this->setValues(array(
            'partyId' => self::required($data, 'partyId'),
            'label' => self::required($data, 'label'),
            'partyType' => isset($data['partyType']) ? trim((string) $data['partyType']) : null,
        ));
    }
}
