<?php

require_once __DIR__.'/Canonical/Decimal.php';

/**
 * Reconstructs VAT provenance for aggregated Dolibarr bookkeeping lines.
 *
 * Dolibarr customer/supplier accounting journals aggregate source invoice
 * lines by accounting account and set bookkeeping fk_docdet=0. The original
 * VAT code therefore has to be resolved from the source document lines.
 */
class DkDolibarrVatProvenanceResolver
{
    private $db;
    private $entity;

    public function __construct($db, $entity)
    {
        $this->db = $db;
        $this->entity = (int) $entity;

        if ($this->entity <= 0) {
            throw new InvalidArgumentException('A positive entity id is required');
        }
    }

    public function resolve($docType, $documentId, $accountCode)
    {
        $docType = (string) $docType;
        $documentId = (int) $documentId;
        $accountCode = trim((string) $accountCode);

        if ($documentId <= 0 || $accountCode === '') {
            return array();
        }

        if ($docType === 'customer_invoice') {
            return $this->resolveInvoiceLines(
                'facturedet',
                'fk_facture',
                'total_tva',
                $documentId,
                $accountCode
            );
        }

        if ($docType === 'supplier_invoice') {
            return $this->resolveInvoiceLines(
                'facture_fourn_det',
                'fk_facture_fourn',
                'tva',
                $documentId,
                $accountCode
            );
        }

        return array();
    }

    private function resolveInvoiceLines($detailTable, $documentForeignKey, $taxAmountColumn, $documentId, $accountCode)
    {
        $sql = 'SELECT COALESCE(d.vat_src_code, \'\') AS tax_code,';
        $sql .= ' d.tva_tx AS tax_percentage,';
        $sql .= ' SUM(d.total_ht) AS tax_base,';
        $sql .= ' SUM(d.'.$taxAmountColumn.') AS tax_amount';
        $sql .= ' FROM '.$this->db->prefix().$detailTable.' d';
        $sql .= ' INNER JOIN '.$this->db->prefix().'accounting_account aa';
        $sql .= ' ON aa.rowid = d.fk_code_ventilation';
        $sql .= ' WHERE d.'.$documentForeignKey.' = '.((int) $documentId);
        $sql .= ' AND aa.entity = '.$this->entity;
        $sql .= " AND aa.account_number = '".$this->db->escape($accountCode)."'";
        $sql .= ' AND d.product_type IN (0,1)';
        $sql .= ' GROUP BY COALESCE(d.vat_src_code, \'\'), d.tva_tx';
        $sql .= ' ORDER BY COALESCE(d.vat_src_code, \'\'), d.tva_tx';

        $resql = $this->db->query($sql);
        if (!$resql) {
            throw new RuntimeException('Unable to resolve VAT provenance: '.$this->db->lasterror());
        }

        $components = array();

        while ($row = $this->db->fetch_object($resql)) {
            $taxCode = trim((string) $row->tax_code);
            $percentage = $this->decimal($row->tax_percentage);
            $taxBase = $this->decimal($row->tax_base);
            $taxAmount = $this->decimal($row->tax_amount);

            if ($taxCode === '') {
                if (!DkCanonicalDecimal::equals($percentage, '0') || !DkCanonicalDecimal::equals($taxAmount, '0')) {
                    throw new RuntimeException(
                        'Taxable source lines for account '.$accountCode.' have no Dolibarr VAT source code'
                    );
                }

                // A true no-VAT line with no source code carries no TaxInformation.
                continue;
            }

            $components[] = array(
                'taxCode' => $taxCode,
                'taxPercentage' => $percentage,
                'taxBase' => $taxBase,
                'taxAmount' => $taxAmount,
                'taxBaseDescription' => 'Source invoice lines aggregated by accounting account and VAT code',
            );
        }

        return $components;
    }

    private function decimal($value)
    {
        if (is_float($value)) {
            return number_format($value, DkCanonicalDecimal::SCALE, '.', '');
        }

        return DkCanonicalDecimal::normalize((string) ($value ?? '0'));
    }
}
