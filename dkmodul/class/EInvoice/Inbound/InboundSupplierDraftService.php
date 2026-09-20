<?php

require_once dirname(__DIR__, 2).'/Audit/AuditLedger.php';

final class DkInboundSupplierDraftService
{
    private $db;
    private string $storageRoot;
    private DkAuditLedger $audit;

    public function __construct($db, string $storageRoot, ?DkAuditLedger $audit = null)
    {
        $this->db = $db;
        $this->storageRoot = rtrim($storageRoot, DIRECTORY_SEPARATOR);
        $this->audit = $audit ?: new DkAuditLedger($db);
    }

    public function resolveSupplier(int $entity, int $inboundRowId): array
    {
        $source = $this->validatedSource($entity, $inboundRowId);
        $invoice = $this->parse($this->readVerifiedPayload($source));
        $cvr = $this->dkCvr($invoice['supplierCompanyId']);
        $sql = 'SELECT rowid,nom,siren FROM '.$this->db->prefix().'societe';
        $sql .= " WHERE entity=".$entity." AND fournisseur=1 AND status=1 AND REPLACE(UPPER(TRIM(siren)),'DK','')='".$this->db->escape($cvr)."'";
        $resql = $this->db->query($sql);
        $matches = array();
        while ($resql && $row = $this->db->fetch_object($resql)) $matches[] = array('rowid' => (int) $row->rowid, 'name' => (string) $row->nom, 'cvr' => $cvr);
        if (count($matches) !== 1) throw new RuntimeException('Inbound supplier CVR must resolve to exactly one active Dolibarr supplier');
        return $matches[0];
    }

    public function approveAndCreateDraft(int $entity, int $inboundRowId, int $supplierRowId, int $actorId, $user): array
    {
        if ($entity <= 0 || $inboundRowId <= 0 || $supplierRowId <= 0 || $actorId <= 0 || (int) $user->id !== $actorId) {
            throw new InvalidArgumentException('Entity, inbound invoice, supplier and approving actor are required');
        }
        if (empty($user->admin) && (!method_exists($user, 'hasRight') || !$user->hasRight('dkmodul', 'inbound', 'approve'))) {
            throw new RuntimeException('Actor is not authorized to approve inbound supplier drafts');
        }
        $this->db->begin();
        try {
            $existing = $this->existingDraft($entity, $inboundRowId, true);
            if ($existing) {
                $this->db->commit();
                return array('rowid' => (int) $existing->rowid, 'supplierInvoiceRowId' => (int) $existing->supplier_invoice_rowid, 'reused' => true);
            }
            $source = $this->validatedSource($entity, $inboundRowId, true);
            $invoiceData = $this->parse($this->readVerifiedPayload($source));
            $resolved = $this->resolveSupplier($entity, $inboundRowId);
            if ($resolved['rowid'] !== $supplierRowId) throw new RuntimeException('Approved supplier does not match inbound supplier CVR');
            $this->assertNoSupplierDuplicate($entity, $supplierRowId, $invoiceData['invoiceId']);

            require_once DOL_DOCUMENT_ROOT.'/fourn/class/fournisseur.facture.class.php';
            $draft = new FactureFournisseur($this->db);
            $draft->socid = $supplierRowId;
            $draft->type = $invoiceData['documentType'] === 'CreditNote' ? FactureFournisseur::TYPE_CREDIT_NOTE : FactureFournisseur::TYPE_STANDARD;
            if ($draft->type === FactureFournisseur::TYPE_CREDIT_NOTE) {
                $draft->fk_facture_source = $this->originalInvoice($entity, $supplierRowId, $invoiceData['creditedInvoiceId']);
            }
            $draft->ref_supplier = $invoiceData['invoiceId'];
            $draft->date = strtotime($invoiceData['issueDate'].' UTC');
            $draft->date_echeance = $invoiceData['dueDate'] === '' ? null : strtotime($invoiceData['dueDate'].' UTC');
            $draft->multicurrency_code = $invoiceData['currencyCode'];
            $draft->note_private = 'Created from validated inbound OIOUBL '.$source->inbound_uuid;
            $supplierInvoiceId = $draft->create($user);
            if ($supplierInvoiceId <= 0) throw new RuntimeException('Unable to create supplier invoice draft: '.$draft->error);
            foreach ($invoiceData['lines'] as $line) {
                $result = $draft->addline($line['description'], $line['unitPrice'], $line['vatPercentage'], 0, 0, $line['quantity']);
                if ($result <= 0) throw new RuntimeException('Unable to add supplier invoice line: '.$draft->error);
            }
            if ($draft->fetch($supplierInvoiceId) <= 0 || (int) $draft->statut !== 0) throw new RuntimeException('Inbound supplier invoice was not left as draft');
            $expectedTotal = ($invoiceData['documentType'] === 'CreditNote' ? -1 : 1) * (float) $invoiceData['payableAmount'];
            if (abs((float) $draft->total_ttc - $expectedTotal) > 0.01) throw new RuntimeException('Supplier draft total differs from validated OIOUBL payable amount');

            $sql = 'INSERT INTO '.$this->db->prefix().'dk_einvoice_inbound_draft';
            $sql .= ' (entity,inbound_rowid,supplier_rowid,supplier_invoice_rowid,supplier_invoice_ref,source_content_hash,fk_user_approver,approved_at) VALUES (';
            $sql .= $entity.','.$inboundRowId.','.$supplierRowId.','.$supplierInvoiceId.",'".$this->db->escape($invoiceData['invoiceId'])."','".$source->content_hash."',".$actorId.',UTC_TIMESTAMP())';
            if (!$this->db->query($sql)) throw new RuntimeException('Unable to record inbound supplier draft provenance: '.$this->db->lasterror());
            $linkRowId = (int) $this->db->last_insert_id($this->db->prefix().'dk_einvoice_inbound_draft');
            $this->audit->append($entity, 'einvoice.inbound.draft_approved', 'dk_einvoice_inbound_draft', $linkRowId, $actorId, array(
                'inboundRowId' => $inboundRowId, 'supplierInvoiceRowId' => $supplierInvoiceId, 'supplierRowId' => $supplierRowId,
            ));
            if ($this->db->commit() <= 0) throw new RuntimeException('Unable to commit inbound supplier invoice draft');
        } catch (Throwable $e) {
            $this->db->rollback();
            throw $e;
        }
        return array('rowid' => $linkRowId, 'supplierInvoiceRowId' => $supplierInvoiceId, 'reused' => false);
    }

    private function validatedSource(int $entity, int $rowId, bool $lock = false)
    {
        $sql = 'SELECT i.* FROM '.$this->db->prefix().'dk_einvoice_inbound i JOIN '.$this->db->prefix().'dk_einvoice_inbound_validation v ON v.inbound_rowid=i.rowid AND v.entity=i.entity';
        $sql .= " WHERE i.entity=".$entity.' AND i.rowid='.$rowId." AND v.event_type='validated'".($lock ? ' FOR UPDATE' : '');
        $resql = $this->db->query($sql);
        $row = $resql ? $this->db->fetch_object($resql) : false;
        if (!$row) throw new RuntimeException('Only officially validated inbound OIOUBL can create a supplier draft');
        return $row;
    }

    private function readVerifiedPayload($row): string
    {
        $path = $this->storageRoot.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $row->storage_key);
        $xml = is_file($path) ? file_get_contents($path) : false;
        if ($xml === false || strlen($xml) !== (int) $row->byte_size || !hash_equals((string) $row->content_hash, hash('sha256', $xml))) throw new RuntimeException('Inbound OIOUBL bytes failed integrity verification');
        return $xml;
    }

    private function parse(string $xml): array
    {
        $dom = new DOMDocument();
        if (!$dom->loadXML($xml, LIBXML_NONET)) throw new RuntimeException('Unable to parse validated inbound OIOUBL');
        $root = $dom->documentElement;
        $documentType = $root ? $root->localName : '';
        if (!in_array($documentType, array('Invoice', 'CreditNote'), true)) throw new RuntimeException('Inbound document must be an Invoice or CreditNote');
        $lineElement = $documentType === 'CreditNote' ? 'CreditNoteLine' : 'InvoiceLine';
        $quantityElement = $documentType === 'CreditNote' ? 'CreditedQuantity' : 'InvoicedQuantity';
        $xp = new DOMXPath($dom);
        $xp->registerNamespace('doc', 'urn:oasis:names:specification:ubl:schema:xsd:'.$documentType.'-2');
        $xp->registerNamespace('cbc', 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2');
        $xp->registerNamespace('cac', 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2');
        $value = static fn(string $path, ?DOMNode $context = null): string => trim((string) $xp->evaluate('string('.$path.')', $context));
        $lines = array();
        foreach ($xp->query('/doc:'.$documentType.'/cac:'.$lineElement) as $node) {
            $lines[] = array(
                'description' => $value('cac:Item/cbc:Description', $node) ?: $value('cac:Item/cbc:Name', $node),
                'quantity' => $value('cbc:'.$quantityElement, $node),
                'unitPrice' => $value('cac:Price/cbc:PriceAmount', $node),
                'vatPercentage' => $value('cac:TaxTotal/cac:TaxSubtotal/cac:TaxCategory/cbc:Percent', $node),
            );
        }
        if (!$lines) throw new RuntimeException('Validated inbound OIOUBL has no invoice lines');
        $base = '/doc:'.$documentType;
        return array(
            'documentType' => $documentType,
            'invoiceId' => $value($base.'/cbc:ID'), 'issueDate' => $value($base.'/cbc:IssueDate'),
            'dueDate' => $value($base.'/cbc:DueDate') ?: $value($base.'/cac:PaymentMeans/cbc:PaymentDueDate'),
            'currencyCode' => $value($base.'/cbc:DocumentCurrencyCode'),
            'supplierCompanyId' => $value($base.'/cac:AccountingSupplierParty/cac:Party/cac:PartyLegalEntity/cbc:CompanyID'),
            'creditedInvoiceId' => $documentType === 'CreditNote' ? $value($base.'/cac:BillingReference/cac:InvoiceDocumentReference/cbc:ID') : '',
            'payableAmount' => $value($base.'/cac:LegalMonetaryTotal/cbc:PayableAmount'), 'lines' => $lines,
        );
    }

    private function originalInvoice(int $entity, int $supplierId, string $reference): int
    {
        if (trim($reference) === '') throw new RuntimeException('Inbound credit note must reference the credited supplier invoice');
        $sql = 'SELECT rowid FROM '.$this->db->prefix().'facture_fourn WHERE entity='.$entity.' AND fk_soc='.$supplierId;
        $sql .= " AND ref_supplier='".$this->db->escape($reference)."' AND type<>2 AND fk_statut=1";
        $resql = $this->db->query($sql);
        $matches = array();
        while ($resql && $row = $this->db->fetch_object($resql)) $matches[] = (int) $row->rowid;
        if (count($matches) !== 1) throw new RuntimeException('Credited supplier invoice reference must resolve to exactly one validated invoice');
        return $matches[0];
    }

    private function dkCvr(string $value): string
    {
        $value = preg_replace('/^DK/i', '', preg_replace('/\s+/', '', trim($value)));
        if (!preg_match('/^\d{8}$/', $value)) throw new RuntimeException('Inbound supplier must contain a Danish eight-digit CVR');
        return $value;
    }

    private function assertNoSupplierDuplicate(int $entity, int $supplierId, string $reference): void
    {
        $sql = 'SELECT rowid FROM '.$this->db->prefix().'facture_fourn WHERE entity='.$entity.' AND fk_soc='.$supplierId." AND ref_supplier='".$this->db->escape($reference)."' LIMIT 1";
        $resql = $this->db->query($sql);
        if ($resql && $this->db->fetch_object($resql)) throw new RuntimeException('Supplier invoice reference already exists for this supplier');
    }

    private function existingDraft(int $entity, int $inboundRowId, bool $lock)
    {
        $sql = 'SELECT rowid,supplier_invoice_rowid FROM '.$this->db->prefix().'dk_einvoice_inbound_draft WHERE entity='.$entity.' AND inbound_rowid='.$inboundRowId.($lock ? ' FOR UPDATE' : '');
        $resql = $this->db->query($sql);
        return $resql ? $this->db->fetch_object($resql) : false;
    }
}
