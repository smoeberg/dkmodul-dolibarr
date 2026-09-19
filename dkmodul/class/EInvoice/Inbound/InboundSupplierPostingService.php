<?php

require_once dirname(__DIR__, 2).'/Audit/AuditLedger.php';

final class DkInboundSupplierPostingService
{
    private $db;
    private DkAuditLedger $audit;

    public function __construct($db, ?DkAuditLedger $audit = null)
    {
        $this->db = $db;
        $this->audit = $audit ?: new DkAuditLedger($db);
    }

    public function post(int $entity, int $supplierValidationRowId, int $journalRowId, int $actorId, $user): array
    {
        global $conf;

        if ($entity <= 0 || $supplierValidationRowId <= 0 || $journalRowId <= 0 || $actorId <= 0 || !is_object($user) || (int) $user->id !== $actorId) {
            throw new InvalidArgumentException('Entity, validation, purchase journal and matching posting actor are required');
        }
        if ((int) $conf->entity !== $entity) throw new InvalidArgumentException('Posting must run in the active Dolibarr entity');
        if (empty($user->admin) && (!method_exists($user, 'hasRight') || !$user->hasRight('accounting', 'bind', 'write'))) {
            throw new RuntimeException('Actor is not authorized to transfer supplier invoices to bookkeeping');
        }

        require_once DOL_DOCUMENT_ROOT.'/accountancy/class/bookkeeping.class.php';
        $this->db->begin('DK inbound supplier posting');
        try {
            $existing = $this->existingPosting($entity, $supplierValidationRowId, true);
            if ($existing) {
                $this->assertExistingPosting($entity, $existing);
                $this->db->commit('DK inbound supplier posting reused');
                return array('rowid' => (int) $existing->rowid, 'pieceNum' => (int) $existing->piece_num, 'lineCount' => (int) $existing->line_count, 'reused' => true);
            }

            $source = $this->validatedSource($entity, $supplierValidationRowId, true);
            $journal = $this->purchaseJournal($entity, $journalRowId);
            $this->assertNoExistingTransfer($entity, (int) $source->supplier_invoice_rowid);
            $lines = $this->buildLines($entity, $source);
            $this->assertBalanced($lines, $this->cents($source->total_ttc));
            $this->lockBookkeepingSequence($entity);

            $allocator = new BookKeeping($this->db);
            $pieceNum = (int) $allocator->getNextNumMvt();
            if ($pieceNum <= 0) throw new RuntimeException('Unable to allocate Dolibarr bookkeeping piece number');

            foreach ($lines as $line) {
                $bookkeeping = new BookKeeping($this->db);
                $bookkeeping->doc_date = $this->db->jdate($source->datef);
                $bookkeeping->date_lim_reglement = $source->date_lim_reglement ? $this->db->jdate($source->date_lim_reglement) : null;
                $bookkeeping->doc_type = 'supplier_invoice';
                $bookkeeping->doc_ref = (string) $source->invoice_ref;
                $bookkeeping->fk_doc = (int) $source->supplier_invoice_rowid;
                $bookkeeping->fk_docdet = 0;
                $bookkeeping->thirdparty_code = (string) $source->code_fournisseur;
                $bookkeeping->subledger_account = $line['subledger'];
                $bookkeeping->subledger_label = $line['subledger'] !== '' ? (string) $source->supplier_name : '';
                $bookkeeping->numero_compte = $line['account'];
                $bookkeeping->label_compte = $line['accountLabel'];
                $bookkeeping->label_operation = $line['operation'];
                $bookkeeping->debit = $line['debitCents'] / 100;
                $bookkeeping->credit = $line['creditCents'] / 100;
                $bookkeeping->montant = ($line['debitCents'] - $line['creditCents']) / 100;
                $bookkeeping->sens = $line['debitCents'] > 0 ? 'D' : 'C';
                $bookkeeping->code_journal = (string) $journal->code;
                $bookkeeping->journal_label = (string) $journal->label;
                $bookkeeping->piece_num = $pieceNum;
                $bookkeeping->ref = 'DK-EINV-'.$supplierValidationRowId;
                $bookkeeping->entity = $entity;

                $rowId = $bookkeeping->createStd($user, 0, '');
                if ($rowId <= 0) {
                    $errors = !empty($bookkeeping->errors) ? implode('; ', $bookkeeping->errors) : (string) $bookkeeping->error;
                    throw new RuntimeException('Dolibarr rejected supplier bookkeeping line'.($errors !== '' ? ': '.$errors : ''));
                }
            }

            $actual = $this->movementTotals($entity, (int) $source->supplier_invoice_rowid, false);
            if ((int) $actual->line_count !== count($lines) || (int) $actual->piece_count !== 1 || (int) $actual->piece_num !== $pieceNum
                || $this->cents($actual->debit_total) !== $this->cents($actual->credit_total)
                || $this->cents($actual->debit_total) !== $this->cents($source->total_ttc)) {
                throw new RuntimeException('Created supplier bookkeeping movement failed balance or completeness verification');
            }

            $sql = 'UPDATE '.$this->db->prefix().'accounting_bookkeeping SET date_validated=UTC_TIMESTAMP()';
            $sql .= " WHERE entity=".$entity." AND doc_type='supplier_invoice' AND fk_doc=".(int) $source->supplier_invoice_rowid.' AND date_validated IS NULL';
            $lockResult = $this->db->query($sql);
            if (!$lockResult || $this->db->affected_rows($lockResult) !== count($lines)) {
                throw new RuntimeException('Unable to lock all supplier bookkeeping entries');
            }

            $total = number_format($this->cents($actual->debit_total) / 100, 2, '.', '');
            $sql = 'INSERT INTO '.$this->db->prefix().'dk_einvoice_inbound_posting';
            $sql .= ' (entity,supplier_validation_rowid,supplier_invoice_rowid,journal_rowid,piece_num,line_count,debit_total,credit_total,fk_user_poster,posted_at) VALUES (';
            $sql .= $entity.','.$supplierValidationRowId.','.(int) $source->supplier_invoice_rowid.','.$journalRowId.','.$pieceNum.','.count($lines).','.$total.','.$total.','.$actorId.',UTC_TIMESTAMP())';
            if (!$this->db->query($sql)) throw new RuntimeException('Unable to record inbound posting evidence: '.$this->db->lasterror());
            $postingRowId = (int) $this->db->last_insert_id($this->db->prefix().'dk_einvoice_inbound_posting');
            $this->audit->append($entity, 'einvoice.inbound.supplier_posted', 'dk_einvoice_inbound_posting', $postingRowId, $actorId, array(
                'supplierInvoiceRowId' => (int) $source->supplier_invoice_rowid,
                'pieceNum' => $pieceNum,
                'lineCount' => count($lines),
                'total' => $total,
            ));
            if ($this->db->commit('DK inbound supplier posting') <= 0) throw new RuntimeException('Unable to commit inbound supplier posting');
        } catch (Throwable $e) {
            $this->db->rollback('DK inbound supplier posting failed');
            throw $e;
        }

        return array('rowid' => $postingRowId, 'pieceNum' => $pieceNum, 'lineCount' => count($lines), 'reused' => false);
    }

    private function validatedSource(int $entity, int $validationRowId, bool $lock)
    {
        $sql = 'SELECT v.rowid AS validation_rowid,v.supplier_invoice_rowid,v.supplier_invoice_ref,';
        $sql .= ' f.ref AS invoice_ref,f.ref_supplier,f.datef,f.date_lim_reglement,f.total_ht,f.total_tva,f.total_ttc,f.total_localtax1,f.total_localtax2,f.multicurrency_code,f.fk_statut,';
        $sql .= ' s.rowid AS supplier_rowid,s.nom AS supplier_name,s.code_fournisseur,s.code_compta_fournisseur,s.accountancy_code_supplier_general,s.fk_pays';
        $sql .= ' FROM '.$this->db->prefix().'dk_einvoice_inbound_supplier_validation v';
        $sql .= ' JOIN '.$this->db->prefix().'facture_fourn f ON f.rowid=v.supplier_invoice_rowid AND f.entity=v.entity';
        $sql .= ' JOIN '.$this->db->prefix().'societe s ON s.rowid=f.fk_soc AND s.entity=f.entity';
        $sql .= ' WHERE v.entity='.$entity.' AND v.rowid='.$validationRowId.($lock ? ' FOR UPDATE' : '');
        $resql = $this->db->query($sql);
        $row = $resql ? $this->db->fetch_object($resql) : false;
        if (!$row || (int) $row->fk_statut !== 1 || (string) $row->invoice_ref !== (string) $row->supplier_invoice_ref) {
            throw new RuntimeException('Only a controlled, validated inbound supplier invoice can be posted');
        }
        if (trim((string) $row->accountancy_code_supplier_general) === '' || trim((string) $row->code_compta_fournisseur) === '') {
            throw new RuntimeException('Supplier general and subledger accounts must be explicitly configured');
        }
        if (abs((float) $row->total_localtax1) > 0.000001 || abs((float) $row->total_localtax2) > 0.000001) {
            throw new RuntimeException('Inbound posting does not support local taxes');
        }
        $currency = strtoupper(trim((string) $row->multicurrency_code));
        if ($currency !== '' && $currency !== 'DKK') throw new RuntimeException('Inbound posting currently requires DKK');
        return $row;
    }

    private function purchaseJournal(int $entity, int $journalRowId)
    {
        $sql = 'SELECT rowid,code,label FROM '.$this->db->prefix().'accounting_journal WHERE entity='.$entity.' AND rowid='.$journalRowId.' AND nature=3 AND active=1';
        $resql = $this->db->query($sql);
        $row = $resql ? $this->db->fetch_object($resql) : false;
        if (!$row || trim((string) $row->code) === '') throw new RuntimeException('Selected journal is not an active purchase journal');
        return $row;
    }

    private function buildLines(int $entity, $source): array
    {
        $purchase = array();
        $vat = array();
        $sql = 'SELECT d.rowid,d.description,d.total_ht,d.tva,d.tva_tx,d.vat_src_code,d.total_localtax1,d.total_localtax2,aa.account_number,aa.label AS account_label';
        $sql .= ' FROM '.$this->db->prefix().'facture_fourn_det d';
        $sql .= ' LEFT JOIN '.$this->db->prefix().'accounting_account aa ON aa.rowid=d.fk_code_ventilation AND aa.entity='.$entity.' AND aa.active=1';
        $sql .= ' WHERE d.fk_facture_fourn='.(int) $source->supplier_invoice_rowid.' ORDER BY d.rowid';
        $resql = $this->db->query($sql);
        if (!$resql) throw new RuntimeException('Unable to load supplier invoice lines: '.$this->db->lasterror());
        $count = 0;
        while ($row = $this->db->fetch_object($resql)) {
            $count++;
            if (trim((string) $row->account_number) === '') throw new RuntimeException('Every supplier invoice line requires active purchase-account ventilation');
            if (abs((float) $row->total_localtax1) > 0.000001 || abs((float) $row->total_localtax2) > 0.000001) throw new RuntimeException('Inbound posting does not support line local taxes');
            $account = (string) $row->account_number;
            if (!isset($purchase[$account])) $purchase[$account] = array('label' => (string) $row->account_label, 'cents' => 0);
            $purchase[$account]['cents'] += $this->cents($row->total_ht);
            $vatCents = $this->cents($row->tva);
            if ($vatCents !== 0) {
                $vatAccount = $this->vatAccount($entity, (int) $source->fk_pays, (string) $row->vat_src_code, (float) $row->tva_tx);
                if (!isset($vat[$vatAccount['account']])) $vat[$vatAccount['account']] = array('label' => $vatAccount['label'], 'cents' => 0);
                $vat[$vatAccount['account']]['cents'] += $vatCents;
            }
        }
        if ($count === 0) throw new RuntimeException('Validated supplier invoice has no lines');

        $lines = array();
        foreach ($purchase as $account => $value) $lines[] = $this->debitLine($account, $value['label'], 'Inbound purchase '.$source->ref_supplier, $value['cents']);
        foreach ($vat as $account => $value) $lines[] = $this->debitLine($account, $value['label'], 'Inbound purchase VAT '.$source->ref_supplier, $value['cents']);
        $supplierAccount = $this->account($entity, (string) $source->accountancy_code_supplier_general);
        $lines[] = array(
            'account' => $supplierAccount['account'], 'accountLabel' => $supplierAccount['label'],
            'operation' => 'Supplier payable '.$source->ref_supplier, 'subledger' => (string) $source->code_compta_fournisseur,
            'debitCents' => 0, 'creditCents' => $this->cents($source->total_ttc),
        );
        return $lines;
    }

    private function debitLine(string $account, string $label, string $operation, int $cents): array
    {
        if ($cents <= 0) throw new RuntimeException('Inbound invoice posting requires positive debit amounts');
        return array('account' => $account, 'accountLabel' => $label, 'operation' => $operation, 'subledger' => '', 'debitCents' => $cents, 'creditCents' => 0);
    }

    private function vatAccount(int $entity, int $countryId, string $sourceCode, float $rate): array
    {
        if (trim($sourceCode) === '') throw new RuntimeException('Every non-zero VAT line requires a source VAT code');
        $sql = 'SELECT accountancy_code_buy FROM '.$this->db->prefix().'c_tva WHERE entity='.$entity.' AND fk_pays='.$countryId;
        $sql .= " AND active=1 AND type_vat IN (0,2) AND code='".$this->db->escape($sourceCode)."' AND ABS(taux-".$rate.')<0.00001';
        $resql = $this->db->query($sql);
        $codes = array();
        while ($resql && $row = $this->db->fetch_object($resql)) if (trim((string) $row->accountancy_code_buy) !== '') $codes[] = (string) $row->accountancy_code_buy;
        if (count(array_unique($codes)) !== 1) throw new RuntimeException('VAT source code must resolve to exactly one purchase VAT account');
        return $this->account($entity, $codes[0]);
    }

    private function account(int $entity, string $accountCode): array
    {
        $sql = 'SELECT account_number,label FROM '.$this->db->prefix().'accounting_account WHERE entity='.$entity." AND active=1 AND account_number='".$this->db->escape(trim($accountCode))."'";
        $resql = $this->db->query($sql);
        $matches = array();
        while ($resql && $row = $this->db->fetch_object($resql)) $matches[] = array('account' => (string) $row->account_number, 'label' => (string) $row->label);
        if (count($matches) !== 1) throw new RuntimeException('Accounting code must resolve to exactly one active account');
        return $matches[0];
    }

    private function assertBalanced(array $lines, int $invoiceCents): void
    {
        $debit = array_sum(array_column($lines, 'debitCents'));
        $credit = array_sum(array_column($lines, 'creditCents'));
        if ($invoiceCents <= 0 || $debit !== $credit || $debit !== $invoiceCents) throw new RuntimeException('Supplier invoice mappings do not form a balanced movement');
    }

    private function assertNoExistingTransfer(int $entity, int $invoiceId): void
    {
        $totals = $this->movementTotals($entity, $invoiceId, false);
        if ((int) $totals->line_count !== 0) throw new RuntimeException('Supplier invoice already has bookkeeping rows without DK posting evidence');
    }

    private function movementTotals(int $entity, int $invoiceId, bool $requireLocked)
    {
        $sql = 'SELECT COUNT(*) AS line_count,COUNT(DISTINCT piece_num) AS piece_count,COALESCE(MIN(piece_num),0) AS piece_num,COALESCE(SUM(debit),0) AS debit_total,COALESCE(SUM(credit),0) AS credit_total';
        $sql .= ' FROM '.$this->db->prefix()."accounting_bookkeeping WHERE entity=".$entity." AND doc_type='supplier_invoice' AND fk_doc=".$invoiceId;
        if ($requireLocked) $sql .= ' AND date_validated IS NOT NULL';
        $resql = $this->db->query($sql);
        $row = $resql ? $this->db->fetch_object($resql) : false;
        if (!$row) throw new RuntimeException('Unable to verify supplier bookkeeping movement');
        return $row;
    }

    private function lockBookkeepingSequence(int $entity): void
    {
        $sql = 'SELECT rowid,piece_num FROM '.$this->db->prefix().'accounting_bookkeeping WHERE entity='.$entity.' ORDER BY piece_num DESC,rowid DESC LIMIT 1 FOR UPDATE';
        if (!$this->db->query($sql)) throw new RuntimeException('Unable to lock bookkeeping sequence: '.$this->db->lasterror());
    }

    private function existingPosting(int $entity, int $validationRowId, bool $lock)
    {
        $sql = 'SELECT rowid,supplier_invoice_rowid,piece_num,line_count,debit_total,credit_total FROM '.$this->db->prefix().'dk_einvoice_inbound_posting';
        $sql .= ' WHERE entity='.$entity.' AND supplier_validation_rowid='.$validationRowId.($lock ? ' FOR UPDATE' : '');
        $resql = $this->db->query($sql);
        return $resql ? $this->db->fetch_object($resql) : false;
    }

    private function assertExistingPosting(int $entity, $evidence): void
    {
        $actual = $this->movementTotals($entity, (int) $evidence->supplier_invoice_rowid, true);
        if ((int) $actual->line_count !== (int) $evidence->line_count || (int) $actual->piece_count !== 1 || (int) $actual->piece_num !== (int) $evidence->piece_num
            || $this->cents($actual->debit_total) !== $this->cents($evidence->debit_total)
            || $this->cents($actual->credit_total) !== $this->cents($evidence->credit_total)) {
            throw new RuntimeException('Recorded inbound posting evidence no longer matches immutable bookkeeping');
        }
    }

    private function cents($value): int
    {
        return (int) round(((float) $value) * 100, 0, PHP_ROUND_HALF_UP);
    }
}
