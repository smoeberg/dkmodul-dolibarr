<?php

class DkSaft21ImportPackage
{
    public $sourceHash;
    public $header = array();
    public $accounts = array();
    public $standardAccountName;
    public $standardAccountVersion;
    public $taxCodes = array();
    public $transactions = array();
    public $declaredNumberOfEntries;
    public $declaredTotalDebit;
    public $declaredTotalCredit;

    public function __construct(array $data)
    {
        $this->sourceHash = (string) ($data['sourceHash'] ?? '');
        $this->header = (array) ($data['header'] ?? array());
        $this->accounts = (array) ($data['accounts'] ?? array());
        $this->standardAccountName = isset($data['standardAccountName']) ? (string) $data['standardAccountName'] : null;
        $this->standardAccountVersion = isset($data['standardAccountVersion']) ? (string) $data['standardAccountVersion'] : null;
        $this->taxCodes = (array) ($data['taxCodes'] ?? array());
        $this->transactions = (array) ($data['transactions'] ?? array());
        $this->declaredNumberOfEntries = (int) ($data['declaredNumberOfEntries'] ?? 0);
        $this->declaredTotalDebit = DkCanonicalDecimal::normalize($data['declaredTotalDebit'] ?? '0');
        $this->declaredTotalCredit = DkCanonicalDecimal::normalize($data['declaredTotalCredit'] ?? '0');
    }

    public function lineCount()
    {
        $count = 0;
        foreach ($this->transactions as $transaction) {
            $count += count($transaction->lines);
        }
        return $count;
    }

    public function totalDebit()
    {
        $total = DkCanonicalDecimal::normalize('0');
        foreach ($this->transactions as $transaction) {
            foreach ($transaction->lines as $line) {
                $total = DkCanonicalDecimal::add($total, $line->debit);
            }
        }
        return $total;
    }

    public function totalCredit()
    {
        $total = DkCanonicalDecimal::normalize('0');
        foreach ($this->transactions as $transaction) {
            foreach ($transaction->lines as $line) {
                $total = DkCanonicalDecimal::add($total, $line->credit);
            }
        }
        return $total;
    }
}
