<?php

require_once __DIR__.'/Record.php';
require_once __DIR__.'/Decimal.php';

final readonly class DkCanonicalAccount extends DkCanonicalRecord
{
    public function __construct(array $data)
    {
        $this->setValues(array(
            'accountCode' => self::required($data, 'accountCode'),
            'label' => self::required($data, 'label'),
            'accountType' => trim((string) ($data['accountType'] ?? 'OTHER')) ?: 'OTHER',
            'creationDate' => isset($data['creationDate']) ? (string) $data['creationDate'] : null,
            'openingBalance' => DkCanonicalDecimal::normalize($data['openingBalance'] ?? '0'),
            'closingBalance' => DkCanonicalDecimal::normalize($data['closingBalance'] ?? '0'),
            'standardAccountId' => isset($data['standardAccountId']) ? trim((string) $data['standardAccountId']) : null,
        ));
    }
}
