<?php

require_once __DIR__.'/Record.php';

final readonly class DkCanonicalCompanyContext extends DkCanonicalRecord
{
    public function __construct(array $data)
    {
        $address = (array) ($data['address'] ?? array());
        $bankAccounts = array_values((array) ($data['bankAccounts'] ?? array()));

        $this->setValues(array(
            'id' => self::required($data, 'id'),
            'name' => self::required($data, 'name'),
            'registrationNumber' => isset($data['registrationNumber']) ? trim((string) $data['registrationNumber']) : null,
            'taxRegistrationNumber' => isset($data['taxRegistrationNumber']) ? trim((string) $data['taxRegistrationNumber']) : null,
            'currencyCode' => self::required($data, 'currencyCode'),
            'regionCode' => isset($data['regionCode']) ? trim((string) $data['regionCode']) : null,
            'address' => $address,
            'phone' => isset($data['phone']) ? trim((string) $data['phone']) : null,
            'email' => isset($data['email']) ? trim((string) $data['email']) : null,
            'bankAccounts' => $bankAccounts,
        ));
    }
}
