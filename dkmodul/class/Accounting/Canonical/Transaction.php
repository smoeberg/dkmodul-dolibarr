<?php

require_once __DIR__.'/Decimal.php';
require_once __DIR__.'/Line.php';

class DkCanonicalTransaction
{
    public $transactionId;
    public $journalCode;
    public $transactionDate;
    public $registrationDateTime;
    public $actor;
    public $documentRef;
    public $description;
    public $correctionOf;
    public $lines = array();

    public function __construct(array $data)
    {
        $this->transactionId = (string) ($data['transactionId'] ?? '');
        $this->journalCode = (string) ($data['journalCode'] ?? '');
        $this->transactionDate = (string) ($data['transactionDate'] ?? '');
        $this->registrationDateTime = (string) ($data['registrationDateTime'] ?? '');
        $this->actor = (string) ($data['actor'] ?? '');
        $this->documentRef = isset($data['documentRef']) ? (string) $data['documentRef'] : null;
        $this->description = isset($data['description']) ? (string) $data['description'] : null;
        $this->correctionOf = isset($data['correctionOf']) ? (string) $data['correctionOf'] : null;

        foreach (($data['lines'] ?? array()) as $line) {
            $this->lines[] = $line instanceof DkCanonicalLine ? $line : new DkCanonicalLine($line);
        }

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
