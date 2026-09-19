<?php

require_once __DIR__.'/Canonical/Invoice.php';

/** Read-only Dolibarr 24.0.x adapter for outbound electronic invoices. */
final class DkDolibarrOutboundInvoiceProvider
{
    private $db;
    private int $entity;
    private array $supplier;

    public function __construct($db, int $entity, array $supplier)
    {
        if ($entity <= 0) {
            throw new InvalidArgumentException('A positive entity is required');
        }
        $this->db = $db;
        $this->entity = $entity;
        $this->supplier = $supplier;
    }

    public function getInvoice(int $invoiceId, string $customerEndpointId, string $customerEndpointScheme, string $orderReference): DkCanonicalInvoice
    {
        $sql = 'SELECT f.rowid,f.ref,f.datef,f.date_lim_reglement,f.total_ht,f.total_tva,f.total_ttc,';
        $sql .= ' f.multicurrency_code,s.nom,s.address,s.zip,s.town,s.siren,s.email,c.code AS country_code';
        $sql .= ' FROM '.$this->db->prefix().'facture f';
        $sql .= ' INNER JOIN '.$this->db->prefix().'societe s ON s.rowid=f.fk_soc AND s.entity=f.entity';
        $sql .= ' LEFT JOIN '.$this->db->prefix().'c_country c ON c.rowid=s.fk_pays';
        $sql .= ' WHERE f.entity='.$this->entity.' AND f.rowid='.$invoiceId;
        $resql = $this->db->query($sql);
        $invoice = $resql ? $this->db->fetch_object($resql) : false;
        if (!$invoice) {
            throw new InvalidArgumentException('Dolibarr customer invoice was not found');
        }

        $sql = 'SELECT rowid,description,label,qty,subprice,total_ht,total_tva,tva_tx';
        $sql .= ' FROM '.$this->db->prefix().'facturedet WHERE fk_facture='.$invoiceId.' ORDER BY rang ASC,rowid ASC';
        $resql = $this->db->query($sql);
        if (!$resql) {
            throw new RuntimeException('Unable to load Dolibarr invoice lines: '.$this->db->lasterror());
        }
        $lines = array();
        while ($line = $this->db->fetch_object($resql)) {
            $description = trim((string) $line->description) ?: trim((string) $line->label);
            $lines[] = array(
                'id' => (string) $line->rowid,
                'description' => $description,
                'quantity' => $this->dbDecimal($line->qty),
                'unitCode' => 'EA',
                'unitPrice' => $this->dbDecimal($line->subprice),
                'lineExtensionAmount' => $this->dbDecimal($line->total_ht),
                'vatPercentage' => $this->dbDecimal($line->tva_tx),
                'taxAmount' => $this->dbDecimal($line->total_tva),
            );
        }

        $issueDate = substr((string) $invoice->datef, 0, 10);
        $dueDate = $invoice->date_lim_reglement ? substr((string) $invoice->date_lim_reglement, 0, 10) : $issueDate;
        $customerCompanyId = $this->dkCompanyId((string) $invoice->siren);
        $currency = trim((string) $invoice->multicurrency_code);
        if ($currency === '') {
            $currency = trim((string) ($this->supplier['currencyCode'] ?? 'DKK')) ?: 'DKK';
        }
        [$customerStreet, $customerBuildingNumber] = $this->structuredAddress((string) $invoice->address);

        return new DkCanonicalInvoice(array(
            'sourceInvoiceId' => (int) $invoice->rowid,
            'invoiceId' => (string) $invoice->ref,
            'uuid' => $this->deterministicUuid($this->entity.':invoice:'.$invoice->rowid.':'.$invoice->ref),
            'issueDate' => $issueDate,
            'dueDate' => $dueDate,
            'currencyCode' => $currency,
            'orderReference' => trim($orderReference),
            'supplier' => $this->supplier,
            'customer' => array(
                'endpointId' => trim($customerEndpointId),
                'endpointScheme' => trim($customerEndpointScheme),
                'registrationName' => (string) $invoice->nom,
                'companyId' => $customerCompanyId,
                'street' => $customerStreet,
                'buildingNumber' => $customerBuildingNumber,
                'city' => (string) $invoice->town,
                'postalCode' => (string) $invoice->zip,
                'countryCode' => trim((string) $invoice->country_code) ?: 'DK',
                'email' => (string) $invoice->email,
            ),
            'lines' => $lines,
            'taxExclusiveAmount' => $this->dbDecimal($invoice->total_ht),
            'taxAmount' => $this->dbDecimal($invoice->total_tva),
            'taxInclusiveAmount' => $this->dbDecimal($invoice->total_ttc),
            'payableAmount' => $this->dbDecimal($invoice->total_ttc),
            'paymentMeansCode' => (string) ($this->supplier['paymentMeansCode'] ?? '42'),
            'paymentId' => (string) $invoice->ref,
            'bankAccount' => (string) ($this->supplier['bankAccount'] ?? ''),
            'bankRegistrationNumber' => (string) ($this->supplier['bankRegistrationNumber'] ?? ''),
        ));
    }

    private function structuredAddress(string $address): array
    {
        $address = trim($address);
        if (!preg_match('/^(.+?)\s+([0-9]+[A-Za-z]?)$/u', $address, $matches)) {
            throw new InvalidArgumentException('A structured Danish address must end with a building number');
        }
        return array(trim($matches[1]), $matches[2]);
    }

    private function dkCompanyId(string $value): string
    {
        $value = preg_replace('/\s+/', '', trim($value));
        if (preg_match('/^\d{8}$/', $value)) {
            return 'DK'.$value;
        }
        if (!preg_match('/^DK\d{8}$/', $value)) {
            throw new InvalidArgumentException('Customer CVR must contain eight digits');
        }
        return $value;
    }

    private function dbDecimal($value): string
    {
        if (is_float($value)) {
            return number_format($value, DkCanonicalDecimal::SCALE, '.', '');
        }
        return DkCanonicalDecimal::normalize((string) $value);
    }

    private function deterministicUuid(string $name): string
    {
        $hex = hash('sha256', 'dolibarr-dk-oioubl|'.$name);
        $hex[12] = '5';
        $hex[16] = dechex((hexdec($hex[16]) & 0x3) | 0x8);
        return substr($hex, 0, 8).'-'.substr($hex, 8, 4).'-'.substr($hex, 12, 4).'-'.substr($hex, 16, 4).'-'.substr($hex, 20, 12);
    }
}
