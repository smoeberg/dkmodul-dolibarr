<?php

require_once __DIR__.'/Saft21ImportPackage.php';

class DkSaft21ImportStagingService
{
    private $db;

    public function __construct($db)
    {
        $this->db = $db;
    }

    public function stage($entity, DkSaft21ImportPackage $package, $userId)
    {
        $entity = (int) $entity;
        $userId = (int) $userId;

        if ($entity <= 0 || $userId <= 0) {
            throw new InvalidArgumentException('Entity and importing user are required');
        }

        $this->assertNotAlreadyImported($entity, $package->sourceHash);

        $uuid = $this->uuidV4();
        $this->db->begin();

        try {
            $sql = 'INSERT INTO '.$this->db->prefix().'dk_saft_import';
            $sql .= ' (entity,import_uuid,source_hash,audit_file_version,source_company_registration,source_company_name,';
            $sql .= ' default_currency_code,standard_account_name,standard_account_version,selection_start_date,selection_end_date,status,transaction_count,line_count,';
            $sql .= ' total_debit,total_credit,fk_user_author,date_creation)';
            $sql .= ' VALUES ('.$entity;
            $sql .= ",'".$this->db->escape($uuid)."'";
            $sql .= ",'".$this->db->escape($package->sourceHash)."'";
            $sql .= ",'".$this->db->escape((string) ($package->header['auditFileVersion'] ?? ''))."'";
            $sql .= ','.$this->nullable($package->header['companyRegistrationNumber'] ?? null);
            $sql .= ",'".$this->db->escape((string) ($package->header['companyName'] ?? ''))."'";
            $sql .= ",'".$this->db->escape((string) ($package->header['defaultCurrencyCode'] ?? ''))."'";
            $sql .= ','.$this->nullable($package->standardAccountName);
            $sql .= ','.$this->nullable($package->standardAccountVersion);
            $sql .= ','.$this->nullable($package->header['selectionStartDate'] ?? null);
            $sql .= ','.$this->nullable($package->header['selectionEndDate'] ?? null);
            $sql .= ",'staged'";
            $sql .= ','.count($package->transactions);
            $sql .= ','.$package->lineCount();
            $sql .= ",'".$this->db->escape($package->totalDebit())."'";
            $sql .= ",'".$this->db->escape($package->totalCredit())."'";
            $sql .= ','.$userId.',NOW())';

            if (!$this->db->query($sql)) {
                throw new RuntimeException($this->db->lasterror());
            }

            $importId = (int) $this->db->last_insert_id($this->db->prefix().'dk_saft_import');
            if ($importId <= 0) {
                throw new RuntimeException('Unable to obtain SAF-T import id');
            }

            foreach ($package->accounts as $account) {
                $this->insertAccount($importId, $account);
            }

            foreach ($package->taxCodes as $taxCode) {
                $this->insertTaxCode($importId, $taxCode);
            }

            foreach ($package->transactions as $transaction) {
                $transactionId = $this->insertTransaction($importId, $transaction);

                foreach ($transaction->lines as $line) {
                    $this->insertLine($transactionId, $line);
                }
            }

            $this->db->commit();

            return array(
                'id' => $importId,
                'uuid' => $uuid,
                'status' => 'staged',
                'transactionCount' => count($package->transactions),
                'lineCount' => $package->lineCount(),
            );
        } catch (Throwable $e) {
            $this->db->rollback();
            throw new RuntimeException('Unable to stage SAF-T import: '.$e->getMessage(), 0, $e);
        }
    }

    private function insertAccount($importId, array $account)
    {
        $sql = 'INSERT INTO '.$this->db->prefix().'dk_saft_import_account';
        $sql .= ' (fk_import,source_account_id,description,standard_account_id,account_type,opening_debit,opening_credit,closing_debit,closing_credit)';
        $sql .= ' VALUES ('.((int) $importId);
        $sql .= ",'".$this->db->escape((string) $account['accountId'])."'";
        $sql .= ",'".$this->db->escape((string) $account['description'])."'";
        $sql .= ','.$this->nullable($account['standardAccountId'] ?? null);
        $sql .= ",'".$this->db->escape((string) $account['accountType'])."'";
        $sql .= ','.$this->nullable($account['openingDebit'] ?? null);
        $sql .= ','.$this->nullable($account['openingCredit'] ?? null);
        $sql .= ','.$this->nullable($account['closingDebit'] ?? null);
        $sql .= ','.$this->nullable($account['closingCredit'] ?? null).')';

        if (!$this->db->query($sql)) {
            throw new RuntimeException($this->db->lasterror());
        }
    }

    private function insertTaxCode($importId, array $taxCode)
    {
        $sql = 'INSERT INTO '.$this->db->prefix().'dk_saft_import_tax_code';
        $sql .= ' (fk_import,tax_type,source_tax_code,standard_tax_code,effective_date,expiration_date,description,tax_percentage,country_code)';
        $sql .= ' VALUES ('.((int) $importId);
        $sql .= ",'".$this->db->escape((string) $taxCode['taxType'])."'";
        $sql .= ",'".$this->db->escape((string) $taxCode['taxCode'])."'";
        $sql .= ','.$this->nullable($taxCode['standardTaxCode'] ?? null);
        $sql .= ",'".$this->db->escape((string) $taxCode['effectiveDate'])."'";
        $sql .= ','.$this->nullable($taxCode['expirationDate'] ?? null);
        $sql .= ",'".$this->db->escape((string) $taxCode['description'])."'";
        $sql .= ','.$this->nullable($taxCode['taxPercentage'] ?? null);
        $sql .= ','.$this->nullable($taxCode['countryCode'] ?? null).')';

        if (!$this->db->query($sql)) {
            throw new RuntimeException($this->db->lasterror());
        }
    }

    private function insertTransaction($importId, $transaction)
    {
        $sql = 'INSERT INTO '.$this->db->prefix().'dk_saft_import_transaction';
        $sql .= ' (fk_import,external_transaction_id,journal_id,journal_description,transaction_date,registration_datetime,actor,description)';
        $sql .= ' VALUES ('.((int) $importId);
        $sql .= ",'".$this->db->escape($transaction->transactionId)."'";
        $sql .= ",'".$this->db->escape($transaction->journalCode)."'";
        $sql .= ",'".$this->db->escape((string) ($transaction->journalDescription ?: $transaction->journalCode))."'";
        $sql .= ",'".$this->db->escape($transaction->transactionDate)."'";
        $sql .= ",'".$this->db->escape($transaction->registrationDateTime)."'";
        $sql .= ",'".$this->db->escape($transaction->actor)."'";
        $sql .= ','.$this->nullable($transaction->description).')';

        if (!$this->db->query($sql)) {
            throw new RuntimeException($this->db->lasterror());
        }

        return (int) $this->db->last_insert_id($this->db->prefix().'dk_saft_import_transaction');
    }

    private function insertLine($transactionId, $line)
    {
        $tax = array();
        foreach ($line->taxInformation as $item) {
            $tax[] = array(
                'taxType' => $item->taxType,
                'taxCode' => $item->taxCode,
                'standardTaxCode' => $item->standardTaxCode,
                'taxPercentage' => $item->taxPercentage,
                'taxBase' => $item->taxBase,
                'taxAmount' => $item->taxAmount,
                'countryCode' => $item->countryCode,
                'description' => $item->description,
            );
        }

        $taxJson = json_encode($tax, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($taxJson === false) {
            throw new RuntimeException('Unable to serialize imported SAF-T tax information');
        }

        $sql = 'INSERT INTO '.$this->db->prefix().'dk_saft_import_line';
        $sql .= ' (fk_import_transaction,external_record_id,source_account_id,debit,credit,currency_code,currency_amount,description,source_document_ref,tax_information_json)';
        $sql .= ' VALUES ('.((int) $transactionId);
        $sql .= ",'".$this->db->escape($line->lineId)."'";
        $sql .= ",'".$this->db->escape($line->accountCode)."'";
        $sql .= ",'".$this->db->escape($line->debit)."'";
        $sql .= ",'".$this->db->escape($line->credit)."'";
        $sql .= ','.$this->nullable($line->currencyCode);
        $sql .= ','.$this->nullable($line->currencyAmount);
        $sql .= ','.$this->nullable($line->description);
        $sql .= ','.$this->nullable($line->sourceDocumentRef);
        $sql .= ",'".$this->db->escape($taxJson)."')";

        if (!$this->db->query($sql)) {
            throw new RuntimeException($this->db->lasterror());
        }
    }

    private function assertNotAlreadyImported($entity, $sourceHash)
    {
        $sql = 'SELECT rowid,status FROM '.$this->db->prefix().'dk_saft_import';
        $sql .= ' WHERE entity = '.((int) $entity);
        $sql .= " AND source_hash = '".$this->db->escape($sourceHash)."' LIMIT 1";

        $resql = $this->db->query($sql);
        if (!$resql) {
            throw new RuntimeException($this->db->lasterror());
        }

        if ($this->db->fetch_object($resql)) {
            throw new InvalidArgumentException('This exact SAF-T file has already been imported/staged');
        }
    }

    private function nullable($value)
    {
        if ($value === null || (is_string($value) && trim($value) === '')) {
            return 'NULL';
        }

        return "'".$this->db->escape((string) $value)."'";
    }

    private function uuidV4()
    {
        $data = random_bytes(16);
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}
