<?php

final readonly class DkCanonicalInvoiceParty
{
    public string $endpointId;
    public string $endpointScheme;
    public string $registrationName;
    public string $companyId;
    public string $street;
    public string $city;
    public string $postalCode;
    public string $countryCode;
    public ?string $contactName;
    public ?string $email;

    public function __construct(array $data)
    {
        foreach (array('endpointId', 'endpointScheme', 'registrationName', 'companyId', 'street', 'city', 'postalCode', 'countryCode') as $field) {
            $value = trim((string) ($data[$field] ?? ''));
            if ($value === '') {
                throw new InvalidArgumentException('Canonical invoice party requires '.$field);
            }
            $this->{$field} = $value;
        }
        $this->contactName = isset($data['contactName']) && trim((string) $data['contactName']) !== '' ? trim((string) $data['contactName']) : null;
        $this->email = isset($data['email']) && trim((string) $data['email']) !== '' ? trim((string) $data['email']) : null;
    }
}
