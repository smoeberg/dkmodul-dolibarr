<?php

require_once __DIR__.'/Canonical/AccountingDataProviderInterface.php';
require_once __DIR__.'/Canonical/Transaction.php';
require_once __DIR__.'/DolibarrTaxInformationResolver.php';

/**
 * Read-only adapter from Dolibarr 24.0.x accounting data into the
 * version-neutral Dolibarr DK Canonical Accounting Model.
 */
class DkDolibarrAccountingDataProvider implements DkAccountingDataProviderInterface
{
    private $db;
    private $entity;

    public function __construct($db, $entity)
    {
        $this->db = $db;
        $this->entity = (int) $entity;

        if ($this->entity <= 0) {
            throw new InvalidArgumentException('A positive Dolibarr entity id is required');
        }
    }

    public function getCompanyContext()
    {
        global $mysoc, $conf;

        if (!function_exists('getDolGlobalString')) {
            throw new RuntimeException('Dolibarr runtime is required to read company context');
        }

        return array(
            'id' => (string) $this->entity,
            'name' => getDolGlobalString('MAIN_INFO_SOCIETE_NOM'),
            'registrationNumber' => getDolGlobalString('MAIN_INFO_SIREN'),
            'taxRegistrationNumber' => getDolGlobalString('MAIN_INFO_TVAINTRA'),
            'currencyCode' => isset($conf->currency) ? (string) $conf->currency : getDolGlobalString('MAIN_MONNAIE'),
            'regionCode' => getDolGlobalString('MAIN_INFO_SOCIETE_REGION'),
            'address' => array(
                // SAF-T StreetName explicitly permits house number in the same field.
                'streetName' => getDolGlobalString('MAIN_INFO_SOCIETE_ADDRESS'),
                'postalCode' => getDolGlobalString('MAIN_INFO_SOCIETE_ZIP'),
                'city' => getDolGlobalString('MAIN_INFO_SOCIETE_TOWN'),
                'region' => getDolGlobalString('MAIN_INFO_SOCIETE_REGION'),
                'countryCode' => isset($mysoc->country_code) ? (string) $mysoc->country_code : '',
            ),
            'phone' => getDolGlobalString('MAIN_INFO_SOCIETE_TEL'),
            'email' => getDolGlobalString('MAIN_INFO_SOCIETE_MAIL'),
            'bankAccounts' => $this->getCompanyBankAccounts(),
        );
    }

    public function getAccounts($fromDate, $toDate)
    {
        $fromDate = $fromDate !== null && $fromDate !== '' ? $this->normalizeDate($fromDate) : null;
        $toDate = $toDate !== null && $toDate !== '' ? $this->normalizeDate($toDate) : null;

        $sql = 'SELECT b.numero_compte AS account_code,';
        $sql .= ' COALESCE(MAX(NULLIF(aa.label, \'\')), MAX(b.label_compte)) AS account_label,';
        $sql .= ' MAX(aa.pcg_type) AS account_type, MIN(aa.datec) AS account_creation_date';
        $sql .= ' FROM '.$this->db->prefix().'accounting_bookkeeping b';
        $sql .= ' LEFT JOIN '.$this->db->prefix().'accounting_account aa';
        $sql .= ' ON aa.entity = b.entity AND aa.account_number = b.numero_compte';
        $sql .= ' WHERE b.entity = '.$this->entity;
        $sql .= $this->dateRangeSql('b.doc_date', $fromDate, $toDate);
        $sql .= ' GROUP BY b.numero_compte';
        $sql .= ' ORDER BY b.numero_compte ASC';

        $resql = $this->db->query($sql);
        if (!$resql) {
            throw new RuntimeException('Unable to load canonical accounts: '.$this->db->lasterror());
        }

        $accounts = array();
        while ($row = $this->db->fetch_object($resql)) {
            $accountCode = (string) $row->account_code;
            $balances = $this->getAccountBalances($accountCode, $fromDate, $toDate);

            $accounts[] = array(
                'accountCode' => $accountCode,
                'label' => (string) $row->account_label,
                'accountType' => $row->account_type !== null ? (string) $row->account_type : 'OTHER',
                'creationDate' => $row->account_creation_date !== null ? (string) $row->account_creation_date : null,
                'openingBalance' => $balances['opening'],
                'closingBalance' => $balances['closing'],
            );
        }

        return $accounts;
    }

    public function getParties($fromDate, $toDate)
    {
        $sql = 'SELECT DISTINCT b.subledger_account AS party_id, b.subledger_label AS party_label';
        $sql .= ' FROM '.$this->db->prefix().'accounting_bookkeeping b';
        $sql .= ' WHERE b.entity = '.$this->entity;
        $sql .= " AND b.subledger_account IS NOT NULL AND b.subledger_account <> ''";
        $sql .= $this->dateRangeSql('b.doc_date', $fromDate, $toDate);
        $sql .= ' ORDER BY b.subledger_account ASC';

        $resql = $this->db->query($sql);
        if (!$resql) {
            throw new RuntimeException('Unable to load canonical parties: '.$this->db->lasterror());
        }

        $parties = array();
        while ($row = $this->db->fetch_object($resql)) {
            $parties[] = array(
                'partyId' => (string) $row->party_id,
                'label' => (string) $row->party_label,
            );
        }

        return $parties;
    }

    public function getTaxCodes($fromDate, $toDate)
    {
        global $mysoc;

        $countryCode = isset($mysoc->country_code) && $mysoc->country_code !== ''
            ? (string) $mysoc->country_code
            : 'DK';

        $sql = 'SELECT DISTINCT t.code, t.taux, t.note, t.type_vat, c.code AS country_code';
        $sql .= ' FROM '.$this->db->prefix().'c_tva t';
        $sql .= ' INNER JOIN '.$this->db->prefix().'c_country c ON c.rowid = t.fk_pays';
        $sql .= ' WHERE t.active = 1';
        $sql .= ' AND t.entity IN (0, '.$this->entity.')';
        $sql .= " AND c.code = '".$this->db->escape($countryCode)."'";
        $sql .= " AND t.code IS NOT NULL AND t.code <> ''";
        $sql .= ' ORDER BY t.taux ASC, t.code ASC';

        $resql = $this->db->query($sql);
        if (!$resql) {
            throw new RuntimeException('Unable to load canonical tax codes: '.$this->db->lasterror());
        }

        $taxCodes = array();
        while ($row = $this->db->fetch_object($resql)) {
            $taxCodes[] = array(
                'taxCode' => (string) $row->code,
                'taxType' => 'VAT',
                'description' => trim((string) $row->note) !== '' ? (string) $row->note : 'VAT '.$row->taux.'%',
                'standardTaxCode' => null,
                'standardTaxCodeDescription' => null,
                'effectiveDate' => null,
                'expirationDate' => null,
                'taxPercentage' => $this->dbDecimal($row->taux),
                'countryCode' => (string) $row->country_code,
                'sourceVatType' => (int) $row->type_vat,
            );
        }

        return $taxCodes;
    }

    public function getTransactions($fromDate, $toDate)
    {
        $sql = 'SELECT b.rowid, b.ref, b.piece_num, b.doc_date, b.doc_type, b.doc_ref,';
        $sql .= ' b.fk_doc, b.fk_docdet, b.subledger_account, b.numero_compte, b.label_operation, b.debit, b.credit,';
        $sql .= ' b.multicurrency_amount, b.multicurrency_code, b.fk_user_author,';
        $sql .= ' b.code_journal, b.journal_label, b.date_creation, b.date_validated';
        $sql .= ' FROM '.$this->db->prefix().'accounting_bookkeeping b';
        $sql .= ' WHERE b.entity = '.$this->entity;
        $sql .= $this->dateRangeSql('b.doc_date', $fromDate, $toDate);
        $sql .= ' ORDER BY b.piece_num ASC, b.rowid ASC';

        $resql = $this->db->query($sql);
        if (!$resql) {
            throw new RuntimeException('Unable to load Dolibarr bookkeeping transactions: '.$this->db->lasterror());
        }

        $groups = array();
        while ($row = $this->db->fetch_object($resql)) {
            $piece = (int) $row->piece_num;
            if (!isset($groups[$piece])) {
                $groups[$piece] = array();
            }
            $groups[$piece][] = $row;
        }

        $transactions = array();
        foreach ($groups as $pieceNum => $rows) {
            $transactions[] = $this->mapPiece($pieceNum, $rows);
        }

        return $transactions;
    }

    private function mapPiece($pieceNum, array $rows)
    {
        if (count($rows) < 2) {
            throw new RuntimeException('Bookkeeping piece '.$pieceNum.' has fewer than two lines');
        }

        $first = $rows[0];
        $journal = (string) $first->code_journal;
        $journalDescription = (string) $first->journal_label;
        $docDate = (string) $first->doc_date;
        $actorId = (int) $first->fk_user_author;
        $docType = (string) $first->doc_type;
        $docRef = (string) $first->doc_ref;
        $registration = (string) $first->date_creation;
        $validatedAt = (string) $first->date_validated;
        $ref = trim((string) $first->ref);

        $lines = array();
        foreach ($rows as $row) {
            $this->assertSame($pieceNum, 'code_journal', $journal, (string) $row->code_journal);
            $this->assertSame($pieceNum, 'journal_label', $journalDescription, (string) $row->journal_label);
            $this->assertSame($pieceNum, 'doc_date', $docDate, (string) $row->doc_date);
            $this->assertSame($pieceNum, 'fk_user_author', (string) $actorId, (string) ((int) $row->fk_user_author));
            $this->assertSame($pieceNum, 'doc_type', $docType, (string) $row->doc_type);
            $this->assertSame($pieceNum, 'doc_ref', $docRef, (string) $row->doc_ref);

            if ($registration === '' || ((string) $row->date_creation !== '' && (string) $row->date_creation < $registration)) {
                $registration = (string) $row->date_creation;
            }

            if ((string) $row->date_validated === '') {
                $validatedAt = '';
            } elseif ($validatedAt !== '' && (string) $row->date_validated !== $validatedAt) {
                // The piece is considered fully validated, but preserve the latest lock timestamp.
                if ((string) $row->date_validated > $validatedAt) {
                    $validatedAt = (string) $row->date_validated;
                }
            }

            $lines[] = array(
                'lineId' => (string) $row->rowid,
                'accountCode' => (string) $row->numero_compte,
                'debit' => $this->dbDecimal($row->debit),
                'credit' => $this->dbDecimal($row->credit),
                'partyId' => $row->subledger_account !== null && (string) $row->subledger_account !== ''
                    ? (string) $row->subledger_account
                    : null,
                'currencyCode' => $row->multicurrency_code !== null && (string) $row->multicurrency_code !== ''
                    ? (string) $row->multicurrency_code
                    : null,
                'currencyAmount' => $row->multicurrency_amount !== null
                    ? $this->dbDecimal($row->multicurrency_amount)
                    : null,
                'description' => (string) $row->label_operation,
                'sourceDocumentRef' => (string) $row->doc_ref,
                'taxInformation' => $this->resolveTaxInformation(
                    (string) $row->doc_type,
                    (int) $row->fk_doc,
                    (string) $row->numero_compte
                ),
            );
        }

        return new DkCanonicalTransaction(array(
            'transactionId' => $ref !== '' ? $ref : (string) $pieceNum,
            'sourcePieceNumber' => $pieceNum,
            'journalCode' => $journal,
            'journalDescription' => $journalDescription !== '' ? $journalDescription : $journal,
            'transactionDate' => $docDate,
            'registrationDateTime' => $registration,
            'validatedAt' => $validatedAt !== '' ? $validatedAt : null,
            'actor' => 'user:'.$actorId,
            'sourceType' => $docType,
            'documentRef' => $docRef,
            'description' => (string) $first->label_operation,
            'lines' => $lines,
        ));
    }

    private function resolveTaxInformation($docType, $docId, $accountCode)
    {
        global $mysoc;

        $countryCode = isset($mysoc->country_code) && $mysoc->country_code !== ''
            ? (string) $mysoc->country_code
            : 'DK';

        $resolver = new DkDolibarrTaxInformationResolver(
            $this->db,
            $this->entity,
            $countryCode
        );

        return $resolver->resolve($docType, $docId, $accountCode);
    }

    private function getCompanyBankAccounts()
    {
        $sql = 'SELECT iban_prefix, bic, number, code_banque, code_guichet, currency_code, account_number';
        $sql .= ' FROM '.$this->db->prefix().'bank_account';
        $sql .= ' WHERE entity = '.$this->entity.' AND clos = 0';
        $sql .= ' ORDER BY rowid ASC';

        $resql = $this->db->query($sql);
        if (!$resql) {
            throw new RuntimeException('Unable to load company bank accounts: '.$this->db->lasterror());
        }

        $accounts = array();

        while ($row = $this->db->fetch_object($resql)) {
            $iban = trim((string) $row->iban_prefix);
            $number = trim((string) $row->number);
            $sortCode = trim((string) $row->code_banque.(string) $row->code_guichet);

            if ($iban === '' && ($number === '' || $sortCode === '')) {
                continue;
            }

            $accounts[] = array(
                'iban' => $iban !== '' ? $iban : null,
                'number' => $number !== '' ? $number : null,
                'sortCode' => $sortCode !== '' ? $sortCode : null,
                'bic' => trim((string) $row->bic),
                'currencyCode' => trim((string) $row->currency_code),
                'accountId' => trim((string) $row->account_number),
            );
        }

        return $accounts;
    }

    private function getAccountBalances($accountCode, $fromDate, $toDate)
    {
        $openingExpression = '0';
        if ($fromDate !== null) {
            $openingExpression = "SUM(CASE WHEN doc_date < '".$this->db->escape($fromDate)."' THEN debit - credit ELSE 0 END)";
        }

        $closingExpression = 'SUM(debit - credit)';
        $sql = 'SELECT '.$openingExpression.' AS opening_balance, '.$closingExpression.' AS closing_balance';
        $sql .= ' FROM '.$this->db->prefix().'accounting_bookkeeping';
        $sql .= ' WHERE entity = '.$this->entity;
        $sql .= " AND numero_compte = '".$this->db->escape($accountCode)."'";
        if ($toDate !== null) {
            $sql .= " AND doc_date <= '".$this->db->escape($toDate)."'";
        }

        $resql = $this->db->query($sql);
        if (!$resql) {
            throw new RuntimeException('Unable to calculate canonical account balances: '.$this->db->lasterror());
        }

        $row = $this->db->fetch_object($resql);

        return array(
            'opening' => $this->dbDecimal($row && $row->opening_balance !== null ? $row->opening_balance : '0'),
            'closing' => $this->dbDecimal($row && $row->closing_balance !== null ? $row->closing_balance : '0'),
        );
    }

    private function dateRangeSql($column, $fromDate, $toDate)
    {
        $sql = '';

        if ($fromDate !== null && $fromDate !== '') {
            $sql .= " AND ".$column." >= '".$this->db->escape($this->normalizeDate($fromDate))."'";
        }
        if ($toDate !== null && $toDate !== '') {
            $sql .= " AND ".$column." <= '".$this->db->escape($this->normalizeDate($toDate))."'";
        }

        return $sql;
    }

    private function normalizeDate($value)
    {
        $value = trim((string) $value);
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            throw new InvalidArgumentException('Accounting date filters must use YYYY-MM-DD');
        }
        return $value;
    }

    private function dbDecimal($value)
    {
        if (is_float($value)) {
            // DoliDB drivers may return DECIMAL/DOUBLE columns as floats.
            // Convert without scientific notation before canonical normalization.
            return number_format($value, DkCanonicalDecimal::SCALE, '.', '');
        }

        return DkCanonicalDecimal::normalize((string) $value);
    }

    private function assertSame($pieceNum, $field, $expected, $actual)
    {
        if ($expected !== $actual) {
            throw new RuntimeException(
                'Inconsistent '.$field.' within bookkeeping piece '.$pieceNum
            );
        }
    }
}
