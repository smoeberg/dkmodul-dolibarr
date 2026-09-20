<?php

final class DkInboundWorkflowService
{
    private $db;

    public function __construct($db)
    {
        $this->db = $db;
    }

    public function list(int $entity, int $limit = 100): array
    {
        if ($entity <= 0) throw new InvalidArgumentException('Entity is required');
        $limit = max(1, min(250, $limit));
        $sql = $this->selectSql().' WHERE i.entity='.$entity.' ORDER BY i.received_at DESC,i.rowid DESC LIMIT '.$limit;
        $resql = $this->db->query($sql);
        if (!$resql) throw new RuntimeException('Unable to load inbound workflow: '.$this->db->lasterror());
        $rows = array();
        while ($row = $this->db->fetch_object($resql)) $rows[] = $this->normalize($row);
        return $rows;
    }

    public function get(int $entity, int $inboundRowId): array
    {
        if ($entity <= 0 || $inboundRowId <= 0) throw new InvalidArgumentException('Entity and inbound row are required');
        $sql = $this->selectSql().' WHERE i.entity='.$entity.' AND i.rowid='.$inboundRowId;
        $resql = $this->db->query($sql);
        $row = $resql ? $this->db->fetch_object($resql) : false;
        if (!$row) throw new InvalidArgumentException('Inbound workflow row was not found');
        return $this->normalize($row);
    }

    public function purchaseJournals(int $entity): array
    {
        $sql = 'SELECT rowid,code,label FROM '.$this->db->prefix().'accounting_journal';
        $sql .= ' WHERE entity='.$entity.' AND nature=3 AND active=1 ORDER BY code,rowid';
        $resql = $this->db->query($sql);
        if (!$resql) throw new RuntimeException('Unable to load purchase journals: '.$this->db->lasterror());
        $rows = array();
        while ($row = $this->db->fetch_object($resql)) {
            $rows[] = array('rowid' => (int) $row->rowid, 'code' => (string) $row->code, 'label' => (string) $row->label);
        }
        return $rows;
    }

    private function selectSql(): string
    {
        $p = $this->db->prefix();
        $sql = 'SELECT i.rowid,i.inbound_uuid,i.channel,i.provider_message_id,i.sender_endpoint_id,i.received_at,';
        $sql .= ' tv.rowid AS technical_validation_rowid,tv.event_type,tv.invoice_id,tv.issue_date,tv.currency_code,tv.payable_amount,';
        $sql .= ' d.rowid AS draft_rowid,d.supplier_rowid,d.supplier_invoice_rowid,d.supplier_invoice_ref,';
        $sql .= ' s.nom AS supplier_name,sv.rowid AS supplier_validation_rowid,sv.supplier_invoice_ref AS validated_invoice_ref,';
        $sql .= ' p.rowid AS posting_rowid,p.piece_num,p.line_count,p.debit_total,p.posted_at';
        $sql .= ' FROM '.$p.'dk_einvoice_inbound i';
        $sql .= ' LEFT JOIN '.$p.'dk_einvoice_inbound_validation tv ON tv.entity=i.entity AND tv.inbound_rowid=i.rowid';
        $sql .= ' LEFT JOIN '.$p.'dk_einvoice_inbound_draft d ON d.entity=i.entity AND d.inbound_rowid=i.rowid';
        $sql .= ' LEFT JOIN '.$p.'societe s ON s.entity=i.entity AND s.rowid=d.supplier_rowid';
        $sql .= ' LEFT JOIN '.$p.'dk_einvoice_inbound_supplier_validation sv ON sv.entity=i.entity AND sv.inbound_draft_rowid=d.rowid';
        $sql .= ' LEFT JOIN '.$p.'dk_einvoice_inbound_posting p ON p.entity=i.entity AND p.supplier_validation_rowid=sv.rowid';
        return $sql;
    }

    private function normalize($row): array
    {
        $state = 'received';
        $next = null;
        if ((string) $row->event_type === 'rejected') $state = 'rejected';
        elseif ((int) $row->technical_validation_rowid > 0) {
            $state = 'validated';
            $next = 'approve';
        }
        if ((int) $row->draft_rowid > 0) {
            $state = 'draft';
            $next = 'validate';
        }
        if ((int) $row->supplier_validation_rowid > 0) {
            $state = 'supplier_validated';
            $next = 'post';
        }
        if ((int) $row->posting_rowid > 0) {
            $state = 'posted';
            $next = null;
        }
        return array(
            'inboundRowId' => (int) $row->rowid,
            'inboundUuid' => (string) $row->inbound_uuid,
            'channel' => (string) $row->channel,
            'providerMessageId' => (string) $row->provider_message_id,
            'senderEndpoint' => (string) $row->sender_endpoint_id,
            'receivedAt' => (string) $row->received_at,
            'invoiceId' => (string) $row->invoice_id,
            'issueDate' => (string) $row->issue_date,
            'currencyCode' => (string) $row->currency_code,
            'payableAmount' => $row->payable_amount === null ? null : (string) $row->payable_amount,
            'draftRowId' => (int) $row->draft_rowid,
            'supplierRowId' => (int) $row->supplier_rowid,
            'supplierName' => (string) $row->supplier_name,
            'supplierInvoiceRowId' => (int) $row->supplier_invoice_rowid,
            'supplierValidationRowId' => (int) $row->supplier_validation_rowid,
            'postingRowId' => (int) $row->posting_rowid,
            'pieceNum' => (int) $row->piece_num,
            'state' => $state,
            'nextAction' => $next,
        );
    }
}
