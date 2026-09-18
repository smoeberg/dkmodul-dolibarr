<?php

require_once __DIR__.'/Canonical/TaxInformation.php';

/**
 * Reconstructs VAT provenance from Dolibarr source documents.
 *
 * Dolibarr deliberately aggregates invoice details into bookkeeping lines and
 * often stores fk_docdet=0. We therefore resolve by source document + the
 * accounting account that source lines were ventilated to.
 */
class DkDolibarrTaxInformationResolver
{
    private $db;
    private $entity;
    private $countryCode;

    public function __construct($db, $entity, $countryCode = 'DK')
    {
        $this->db = $db;
        $this->entity = (int) $entity;
        $this->countryCode = trim((string) $countryCode) ?: 'DK';
    }

    /**
     * @return DkCanonicalTaxInformation[]
     */
    public function resolve($docType, $docId, $accountCode)
    {
        $docType = (string) $docType;
        $docId = (int) $docId;
        $accountCode = trim((string) $accountCode);

        if ($docId <= 0 || $accountCode === '') {
            return array();
        }

        if ($docType === 'customer_invoice') {
            return $this->resolveInvoice(
                'facturedet',
                'fk_facture',
                'total_tva',
                $docId,
                $accountCode
            );
        }

        if ($docType === 'supplier_invoice') {
            return $this->resolveInvoice(
                'facture_fourn_det',
                'fk_facture_fourn',
                'tva',
                $docId,
                $accountCode
            );
        }

        return array();
    }

    private function resolveInvoice($detailTable, $invoiceForeignKey, $vatAmountColumn, $docId, $accountCode)
    {
        $sql = 'SELECT d.vat_src_code,d.tva_tx,';
        $sql .= ' SUM(d.total_ht) AS tax_base,';
        $sql .= ' SUM(d.'.$vatAmountColumn.') AS tax_amount';
        $sql .= ' FROM '.$this->db->prefix().$detailTable.' d';
        $sql .= ' INNER JOIN '.$this->db->prefix().'accounting_account aa';
        $sql .= ' ON aa.rowid = d.fk_code_ventilation';
        $sql .= ' WHERE d.'.$invoiceForeignKey.' = '.((int) $docId);
        $sql .= " AND aa.account_number = '".$this->db->escape($accountCode)."'";
        $sql .= " AND d.vat_src_code IS NOT NULL AND d.vat_src_code <> ''";
        $sql .= ' GROUP BY d.vat_src_code,d.tva_tx';
        $sql .= ' ORDER BY d.vat_src_code ASC,d.tva_tx ASC';

        $resql = $this->db->query($sql);
        if (!$resql) {
            throw new RuntimeException('Unable to resolve source VAT information: '.$this->db->lasterror());
        }

        $taxInformation = array();

        while ($row = $this->db->fetch_object($resql)) {
            $taxInformation[] = new DkCanonicalTaxInformation(array(
                'taxType' => 'VAT',
                'taxCode' => (string) $row->vat_src_code,
                'taxPercentage' => $this->dbDecimal($row->tva_tx),
                'taxBase' => $this->dbDecimal($row->tax_base),
                'taxAmount' => $this->dbDecimal($row->tax_amount),
                'countryCode' => $this->countryCode,
            ));
        }

        return $taxInformation;
    }

    private function dbDecimal($value)
    {
        if (is_float($value)) {
            return number_format($value, DkCanonicalDecimal::SCALE, '.', '');
        }

        return DkCanonicalDecimal::normalize((string) $value);
    }
}
