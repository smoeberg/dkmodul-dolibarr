<?php

require_once dirname(__DIR__, 2).'/Audit/AuditLedger.php';

final class DkInboundSupplierValidationService
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

    public function approveAndValidate(int $entity, int $inboundDraftRowId, int $actorId, $user): array
    {
        if ($entity <= 0 || $inboundDraftRowId <= 0 || $actorId <= 0 || !is_object($user) || (int) $user->id !== $actorId) {
            throw new InvalidArgumentException('Entity, inbound draft and matching validation actor are required');
        }
        if (empty($user->admin) && (!method_exists($user, 'hasRight') || !$user->hasRight('fournisseur', 'facture', 'creer'))) {
            throw new RuntimeException('Actor is not authorized to validate supplier invoices');
        }

        $this->db->begin();
        try {
            $existing = $this->existingValidation($entity, $inboundDraftRowId, true);
            if ($existing) {
                $this->assertValidatedInvoice($entity, $existing);
                $this->db->commit();
                return array('rowid' => (int) $existing->rowid, 'supplierInvoiceRowId' => (int) $existing->supplier_invoice_rowid, 'reused' => true);
            }

            $source = $this->source($entity, $inboundDraftRowId, true);
            $payload = $this->verifiedPayload($source);
            $expected = $this->parseExpected($payload);
            $this->assertSourceMatchesDraft($source, $expected);

            require_once DOL_DOCUMENT_ROOT.'/fourn/class/fournisseur.facture.class.php';
            $invoice = new FactureFournisseur($this->db);
            if ($invoice->fetch((int) $source->supplier_invoice_rowid) <= 0) {
                throw new RuntimeException('Inbound supplier invoice draft no longer exists');
            }
            if ((int) $invoice->entity !== $entity || (int) $invoice->socid !== (int) $source->supplier_rowid) {
                throw new RuntimeException('Supplier invoice identity differs from immutable inbound provenance');
            }
            if ((int) $invoice->statut !== 0) {
                throw new RuntimeException('Only the controlled inbound draft can be validated');
            }
            if ((string) $invoice->ref_supplier !== (string) $source->supplier_invoice_ref || (string) $invoice->ref_supplier !== $expected['invoiceId']) {
                throw new RuntimeException('Supplier invoice reference differs from validated OIOUBL');
            }
            if (abs((float) $invoice->total_ttc - (float) $expected['payableAmount']) > 0.01) {
                throw new RuntimeException('Supplier invoice total differs from validated OIOUBL');
            }

            $result = $invoice->validate($user);
            if ($result <= 0) throw new RuntimeException('Unable to validate inbound supplier invoice: '.$invoice->error);
            if ($invoice->fetch((int) $source->supplier_invoice_rowid) <= 0 || (int) $invoice->statut !== 1) {
                throw new RuntimeException('Dolibarr did not leave the supplier invoice validated');
            }

            $sql = 'INSERT INTO '.$this->db->prefix().'dk_einvoice_inbound_supplier_validation';
            $sql .= ' (entity,inbound_draft_rowid,supplier_invoice_rowid,supplier_invoice_ref,source_content_hash,fk_user_validator,validated_at) VALUES (';
            $sql .= $entity.','.$inboundDraftRowId.','.(int) $source->supplier_invoice_rowid.",'".$this->db->escape((string) $invoice->ref)."','".$source->source_content_hash."',".$actorId.',UTC_TIMESTAMP())';
            if (!$this->db->query($sql)) throw new RuntimeException('Unable to record supplier invoice validation evidence: '.$this->db->lasterror());
            $validationRowId = (int) $this->db->last_insert_id($this->db->prefix().'dk_einvoice_inbound_supplier_validation');
            $this->audit->append($entity, 'einvoice.inbound.supplier_validated', 'dk_einvoice_inbound_supplier_validation', $validationRowId, $actorId, array(
                'inboundDraftRowId' => $inboundDraftRowId,
                'supplierInvoiceRowId' => (int) $source->supplier_invoice_rowid,
                'supplierInvoiceRef' => (string) $invoice->ref,
            ));
            if ($this->db->commit() <= 0) throw new RuntimeException('Unable to commit supplier invoice validation');
        } catch (Throwable $e) {
            $this->db->rollback();
            throw $e;
        }

        return array('rowid' => $validationRowId, 'supplierInvoiceRowId' => (int) $source->supplier_invoice_rowid, 'reused' => false);
    }

    private function source(int $entity, int $draftRowId, bool $lock)
    {
        $sql = 'SELECT d.*,i.storage_key,i.byte_size,i.content_hash,s.siren';
        $sql .= ' FROM '.$this->db->prefix().'dk_einvoice_inbound_draft d';
        $sql .= ' JOIN '.$this->db->prefix().'dk_einvoice_inbound i ON i.rowid=d.inbound_rowid AND i.entity=d.entity';
        $sql .= ' JOIN '.$this->db->prefix().'societe s ON s.rowid=d.supplier_rowid AND s.entity=d.entity';
        $sql .= ' WHERE d.entity='.$entity.' AND d.rowid='.$draftRowId.($lock ? ' FOR UPDATE' : '');
        $resql = $this->db->query($sql);
        $row = $resql ? $this->db->fetch_object($resql) : false;
        if (!$row) throw new RuntimeException('Inbound supplier draft provenance is missing');
        return $row;
    }

    private function verifiedPayload($source): string
    {
        if (!hash_equals((string) $source->source_content_hash, (string) $source->content_hash)) {
            throw new RuntimeException('Inbound source hash differs from draft provenance');
        }
        $path = $this->storageRoot.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, (string) $source->storage_key);
        $xml = is_file($path) ? file_get_contents($path) : false;
        if ($xml === false || strlen($xml) !== (int) $source->byte_size || !hash_equals((string) $source->content_hash, hash('sha256', $xml))) {
            throw new RuntimeException('Inbound OIOUBL bytes failed integrity verification');
        }
        return $xml;
    }

    private function parseExpected(string $xml): array
    {
        $dom = new DOMDocument();
        if (!$dom->loadXML($xml, LIBXML_NONET)) throw new RuntimeException('Unable to parse validated inbound OIOUBL');
        $xp = new DOMXPath($dom);
        $xp->registerNamespace('i', 'urn:oasis:names:specification:ubl:schema:xsd:Invoice-2');
        $xp->registerNamespace('cbc', 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2');
        $xp->registerNamespace('cac', 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2');
        return array(
            'invoiceId' => trim((string) $xp->evaluate('string(/i:Invoice/cbc:ID)')),
            'supplierCompanyId' => trim((string) $xp->evaluate('string(/i:Invoice/cac:AccountingSupplierParty/cac:Party/cac:PartyLegalEntity/cbc:CompanyID)')),
            'payableAmount' => trim((string) $xp->evaluate('string(/i:Invoice/cac:LegalMonetaryTotal/cbc:PayableAmount)')),
        );
    }

    private function assertSourceMatchesDraft($source, array $expected): void
    {
        $cvr = preg_replace('/^DK/i', '', preg_replace('/\s+/', '', $expected['supplierCompanyId']));
        $supplierCvr = preg_replace('/^DK/i', '', preg_replace('/\s+/', '', (string) $source->siren));
        if (!preg_match('/^\d{8}$/', $cvr) || $cvr !== $supplierCvr) {
            throw new RuntimeException('Supplier identity differs from validated OIOUBL');
        }
    }

    private function existingValidation(int $entity, int $draftRowId, bool $lock)
    {
        $sql = 'SELECT rowid,supplier_invoice_rowid,supplier_invoice_ref FROM '.$this->db->prefix().'dk_einvoice_inbound_supplier_validation';
        $sql .= ' WHERE entity='.$entity.' AND inbound_draft_rowid='.$draftRowId.($lock ? ' FOR UPDATE' : '');
        $resql = $this->db->query($sql);
        return $resql ? $this->db->fetch_object($resql) : false;
    }

    private function assertValidatedInvoice(int $entity, $evidence): void
    {
        $sql = 'SELECT rowid FROM '.$this->db->prefix().'facture_fourn WHERE entity='.$entity.' AND rowid='.(int) $evidence->supplier_invoice_rowid;
        $sql .= " AND fk_statut=1 AND ref='".$this->db->escape((string) $evidence->supplier_invoice_ref)."'";
        $resql = $this->db->query($sql);
        if (!$resql || !$this->db->fetch_object($resql)) throw new RuntimeException('Recorded supplier validation no longer matches Dolibarr');
    }
}
