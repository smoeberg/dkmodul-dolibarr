<?php

require_once __DIR__.'/Saft21ImportedLine.php';

/**
 * Raw-but-normalized SAF-T transaction.
 *
 * An XSD-valid source transaction is deliberately allowed to be unbalanced at
 * parse time. Balance is a blocking analysis rule before Apply.
 */
class DkSaft21ImportedTransaction
{
    public $transactionId;
    public $journalCode;
    public $journalDescription;
    public $transactionDate;
    public $registrationDateTime;
    public $actor;
    public $description;
    public $lines = array();

    public function __construct(array $data)
    {
        $this->transactionId = trim((string) ($data['transactionId'] ?? ''));
        $this->journalCode = trim((string) ($data['journalCode'] ?? ''));
        $this->journalDescription = isset($data['journalDescription'])
            ? (string) $data['journalDescription']
            : null;
        $this->transactionDate = (string) ($data['transactionDate'] ?? '');
        $this->registrationDateTime = (string) ($data['registrationDateTime'] ?? '');
        $this->actor = trim((string) ($data['actor'] ?? ''));
        $this->description = isset($data['description']) ? (string) $data['description'] : null;

        foreach (($data['lines'] ?? array()) as $line) {
            $this->lines[] = $line instanceof DkSaft21ImportedLine
                ? $line
                : new DkSaft21ImportedLine((array) $line);
        }

        if ($this->transactionId === '' || $this->journalCode === '') {
            throw new InvalidArgumentException('Imported SAF-T transaction requires TransactionID and JournalID');
        }
        if ($this->transactionDate === '' || $this->registrationDateTime === '') {
            throw new InvalidArgumentException('Imported SAF-T transaction dates are required');
        }
        if ($this->actor === '') {
            $this->actor = 'saft-import';
        }
        if (count($this->lines) === 0) {
            throw new InvalidArgumentException('Imported SAF-T transaction contains no lines');
        }
    }
}
