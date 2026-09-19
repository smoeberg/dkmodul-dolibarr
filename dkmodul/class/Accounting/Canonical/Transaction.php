<?php

require_once __DIR__.'/Decimal.php';
require_once __DIR__.'/Line.php';

final readonly class DkCanonicalTransaction
{
    public string $transactionId;
    public ?int $sourcePieceNumber;
    public string $journalCode;
    public ?string $journalDescription;
    public string $transactionDate;
    public string $registrationDateTime;
    public ?string $validatedAt;
    public string $actor;
    public ?string $sourceType;
    public ?string $documentRef;
    public ?string $description;
    public ?string $correctionOf;
    public array $lines;

    public function __construct(array $data)
    {
        $this->transactionId = (string) ($data['transactionId'] ?? '');
        $this->sourcePieceNumber = isset($data['sourcePieceNumber']) ? (int) $data['sourcePieceNumber'] : null;
        $this->journalCode = (string) ($data['journalCode'] ?? '');
        $this->journalDescription = isset($data['journalDescription']) ? (string) $data['journalDescription'] : null;
        $this->transactionDate = (string) ($data['transactionDate'] ?? '');
        $this->registrationDateTime = (string) ($data['registrationDateTime'] ?? '');
        $this->validatedAt = isset($data['validatedAt']) && $data['validatedAt'] !== ''
            ? (string) $data['validatedAt']
            : null;
        $this->actor = (string) ($data['actor'] ?? '');
        $this->sourceType = isset($data['sourceType']) ? (string) $data['sourceType'] : null;
        $this->documentRef = isset($data['documentRef']) ? (string) $data['documentRef'] : null;
        $this->description = isset($data['description']) ? (string) $data['description'] : null;
        $this->correctionOf = isset($data['correctionOf']) ? (string) $data['correctionOf'] : null;

        $lines = array();
        foreach (($data['lines'] ?? array()) as $line) {
            $lines[] = $line instanceof DkCanonicalLine ? $line : new DkCanonicalLine($line);
        }
        $this->lines = $lines;

        $this->validate();
    }

    public function validate()
    {
        if ($this->transactionId === '' || $this->journalCode === '') {
            throw new InvalidArgumentException('Canonical transaction id and journal are required');
        }
        if ($this->transactionDate === '' || $this->registrationDateTime === '') {
            throw new InvalidArgumentException('Canonical transaction and registration dates are required');
        }
        if ($this->actor === '') {
            throw new InvalidArgumentException('Canonical transaction actor/program identity is required');
        }
        if (count($this->lines) < 2) {
            throw new InvalidArgumentException('Canonical transaction must contain at least two lines');
        }

        $debit = DkCanonicalDecimal::normalize('0');
        $credit = DkCanonicalDecimal::normalize('0');
        $lineIds = array();

        foreach ($this->lines as $line) {
            if (isset($lineIds[$line->lineId])) {
                throw new InvalidArgumentException('Canonical line ids must be unique within a transaction');
            }
            $lineIds[$line->lineId] = true;
            $debit = DkCanonicalDecimal::add($debit, $line->debit);
            $credit = DkCanonicalDecimal::add($credit, $line->credit);
        }

        if (!DkCanonicalDecimal::equals($debit, $credit)) {
            throw new InvalidArgumentException('Canonical transaction must balance');
        }

        return true;
    }
}
