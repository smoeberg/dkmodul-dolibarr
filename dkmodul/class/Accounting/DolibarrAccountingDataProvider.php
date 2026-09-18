<?php

require_once __DIR__.'/Canonical/AccountingDataProviderInterface.php';
require_once __DIR__.'/Canonical/Transaction.php';

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
        global $mysoc;

        if (!function_exists('getDolGlobalString')) {
            throw new RuntimeException('Dolibarr runtime is required to read company context');
        }

        return array(
            'id' => (string) $this->entity,
            'name' => getDolGlobalString('MAIN_INFO_SOCIETE_NOM'),
            'address' => getDolGlobalString('MAIN_INFO_SOCIETE_ADDRESS'),
            'postalCode' => getDolGlobalString('MAIN_INFO_SOCIETE_ZIP'),
            'city' => getDolGlobalString('MAIN_INFO_SOCIETE_TOWN'),
            'countryCode' => isset($mysoc->country_code) ? (string) $mysoc->country_code : '',
            'registrationNumber' => getDolGlobalString('MAIN_INFO_SIREN'),
        );
    }

    public function getAccounts($fromDate, $toDate)
    {
        $sql = 'SELECT DISTINCT b.numero_compte AS account_code, b.label_compte AS account_label';
        $sql .= ' FROM '.$this->db->prefix().'accounting_bookkeeping b';
        $sql .= ' WHERE b.entity = '.$this->entity;
        $sql .= $this->dateRangeSql('b.doc_date', $fromDate, $toDate);
        $sql .= ' ORDER BY b.numero_compte ASC';

        $resql = $this->db->query($sql);
        if (!$resql) {
            throw new RuntimeException('Unable to load canonical accounts: '.$this->db->lasterror());
        }

        $accounts = array();
        while ($row = $this->db->fetch_object($resql)) {
            $accounts[] = array(
                'accountCode' => (string) $row->account_code,
                'label' => (string) $row->account_label,
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

    public function getTransactions($fromDate, $toDate)
    {
        $sql = 'SELECT b.rowid, b.ref, b.piece_num, b.doc_date, b.doc_type, b.doc_ref,';
        $sql .= ' b.subledger_account, b.numero_compte, b.label_operation, b.debit, b.credit,';
        $sql .= ' b.multicurrency_amount, b.multicurrency_code, b.fk_user_author,';
        $sql .= ' b.code_journal, b.date_creation, b.date_validated';
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
            );
        }

        return new DkCanonicalTransaction(array(
            'transactionId' => $ref !== '' ? $ref : (string) $pieceNum,
            'sourcePieceNumber' => $pieceNum,
            'journalCode' => $journal,
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
