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
    public function resolve($docType, $docId, $accountCode, $docDetailId = 0)
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

        if ($docType === 'saft_import') {
            return $this->resolveImportedSaftLine($docId, (int) $docDetailId);
        }

        return array();
    }

    private function resolveImportedSaftLine($importId, $stagedLineId)
    {
        if ($importId <= 0 || $stagedLineId <= 0) {
            return array();
        }

        $sql = 'SELECT l.tax_information_json';
        $sql .= ' FROM '.$this->db->prefix().'dk_saft_import_line l';
        $sql .= ' INNER JOIN '.$this->db->prefix().'dk_saft_import_transaction t';
        $sql .= ' ON t.rowid = l.fk_import_transaction';
        $sql .= ' INNER JOIN '.$this->db->prefix().'dk_saft_import i';
        $sql .= ' ON i.rowid = t.fk_import';
        $sql .= ' WHERE l.rowid = '.((int) $stagedLineId);
        $sql .= ' AND i.rowid = '.((int) $importId);
        $sql .= ' AND i.entity = '.$this->entity;
        $sql .= ' LIMIT 1';

        $resql = $this->db->query($sql);
        if (!$resql) {
            throw new RuntimeException('Unable to resolve imported SAF-T tax information: '.$this->db->lasterror());
        }

        $row = $this->db->fetch_object($resql);
        if (!$row || trim((string) $row->tax_information_json) === '') {
            return array();
        }

        $decoded = json_decode((string) $row->tax_information_json, true);
        if (!is_array($decoded)) {
            throw new RuntimeException('Imported SAF-T tax provenance is not valid JSON');
        }

        $result = array();
        foreach ($decoded as $item) {
            if (!is_array($item) || empty($item['taxCode'])) {
                continue;
            }

            $result[] = new DkCanonicalTaxInformation(array(
                'taxType' => $item['taxType'] ?? 'VAT',
                'taxCode' => $item['taxCode'],
                'standardTaxCode' => $item['standardTaxCode'] ?? null,
                'taxPercentage' => $item['taxPercentage'] ?? null,
                'taxBase' => $item['taxBase'] ?? null,
                'taxAmount' => $item['taxAmount'] ?? null,
                'countryCode' => $item['countryCode'] ?? $this->countryCode,
                'description' => $item['description'] ?? null,
            ));
        }

        return $result;
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
