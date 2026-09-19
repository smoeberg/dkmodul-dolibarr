<?php

require_once __DIR__.'/Canonical/Invoice.php';

final class DkOioUblInvoiceGenerator
{
    public const CUSTOMIZATION_ID = 'OIOUBL-2.02';
    public const PROFILE_ID = 'Procurement-OrdAdv-BilSim-1.0';
    private const INVOICE_NS = 'urn:oasis:names:specification:ubl:schema:xsd:Invoice-2';
    private const CAC_NS = 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2';
    private const CBC_NS = 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2';

    public function generate(DkCanonicalInvoice $invoice): string
    {
        $rates = array();
        foreach ($invoice->lines as $line) {
            $rates[$line->vatPercentage] = true;
        }
        if (count($rates) !== 1) {
            throw new InvalidArgumentException('First OIOUBL slice supports one VAT rate per invoice');
        }
        $rate = array_key_first($rates);

        $doc = new DOMDocument('1.0', 'UTF-8');
        $doc->formatOutput = true;
        $root = $doc->createElementNS(self::INVOICE_NS, 'Invoice');
        $root->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:cac', self::CAC_NS);
        $root->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:cbc', self::CBC_NS);
        $doc->appendChild($root);

        $this->cbc($doc, $root, 'UBLVersionID', '2.1');
        $this->cbc($doc, $root, 'CustomizationID', self::CUSTOMIZATION_ID);
        $profile = $this->cbc($doc, $root, 'ProfileID', self::PROFILE_ID);
        $profile->setAttribute('schemeAgencyID', '320');
        $profile->setAttribute('schemeID', 'urn:oioubl:id:profileid-1.2');
        $this->cbc($doc, $root, 'ID', $invoice->invoiceId);
        $this->cbc($doc, $root, 'CopyIndicator', 'false');
        $this->cbc($doc, $root, 'UUID', $invoice->uuid);
        $this->cbc($doc, $root, 'IssueDate', $invoice->issueDate);
        $type = $this->cbc($doc, $root, 'InvoiceTypeCode', '380');
        $type->setAttribute('listAgencyID', '320');
        $type->setAttribute('listID', 'urn:oioubl:codelist:invoicetypecode-1.1');
        $this->cbc($doc, $root, 'DocumentCurrencyCode', $invoice->currencyCode);

        $order = $this->cac($doc, $root, 'OrderReference');
        $this->cbc($doc, $order, 'ID', $invoice->orderReference);

        $this->appendParty($doc, $root, 'AccountingSupplierParty', $invoice->supplier, true);
        $this->appendParty($doc, $root, 'AccountingCustomerParty', $invoice->customer, false);

        $payment = $this->cac($doc, $root, 'PaymentMeans');
        $this->cbc($doc, $payment, 'ID', '1');
        $this->cbc($doc, $payment, 'PaymentMeansCode', $invoice->paymentMeansCode);
        $this->cbc($doc, $payment, 'PaymentDueDate', $invoice->dueDate);
        $channel = $this->cbc($doc, $payment, 'PaymentChannelCode', 'DK:BANK');
        $channel->setAttribute('listAgencyID', '320');
        $channel->setAttribute('listID', 'urn:oioubl:codelist:paymentchannelcode-1.1');
        $account = $this->cac($doc, $payment, 'PayeeFinancialAccount');
        $this->cbc($doc, $account, 'ID', $invoice->bankAccount);
        $this->cbc($doc, $account, 'PaymentNote', $invoice->paymentId);
        $branch = $this->cac($doc, $account, 'FinancialInstitutionBranch');
        $this->cbc($doc, $branch, 'ID', $invoice->bankRegistrationNumber);

        $terms = $this->cac($doc, $root, 'PaymentTerms');
        $this->cbc($doc, $terms, 'ID', '1');
        $this->cbc($doc, $terms, 'PaymentMeansID', '1');
        $this->amount($doc, $terms, 'Amount', $invoice->payableAmount, $invoice->currencyCode);

        $taxTotal = $this->cac($doc, $root, 'TaxTotal');
        $this->amount($doc, $taxTotal, 'TaxAmount', $invoice->taxAmount, $invoice->currencyCode);
        $this->appendTaxSubtotal($doc, $taxTotal, $invoice->taxExclusiveAmount, $invoice->taxAmount, $rate, $invoice->currencyCode);

        $monetary = $this->cac($doc, $root, 'LegalMonetaryTotal');
        $this->amount($doc, $monetary, 'LineExtensionAmount', $invoice->taxExclusiveAmount, $invoice->currencyCode);
        $this->amount($doc, $monetary, 'TaxExclusiveAmount', $invoice->taxExclusiveAmount, $invoice->currencyCode);
        $this->amount($doc, $monetary, 'TaxInclusiveAmount', $invoice->taxInclusiveAmount, $invoice->currencyCode);
        $this->amount($doc, $monetary, 'PayableAmount', $invoice->payableAmount, $invoice->currencyCode);

        foreach ($invoice->lines as $line) {
            $node = $this->cac($doc, $root, 'InvoiceLine');
            $this->cbc($doc, $node, 'ID', $line->id);
            $quantity = $this->cbc($doc, $node, 'InvoicedQuantity', $this->decimal($line->quantity));
            $quantity->setAttribute('unitCode', $line->unitCode);
            $this->amount($doc, $node, 'LineExtensionAmount', $line->lineExtensionAmount, $invoice->currencyCode);
            $lineTax = $this->cac($doc, $node, 'TaxTotal');
            $this->amount($doc, $lineTax, 'TaxAmount', $line->taxAmount, $invoice->currencyCode);
            $this->appendTaxSubtotal($doc, $lineTax, $line->lineExtensionAmount, $line->taxAmount, $line->vatPercentage, $invoice->currencyCode);
            $item = $this->cac($doc, $node, 'Item');
            $this->cbc($doc, $item, 'Description', $line->description);
            $this->cbc($doc, $item, 'Name', $line->description);
            $price = $this->cac($doc, $node, 'Price');
            $this->amount($doc, $price, 'PriceAmount', $line->unitPrice, $invoice->currencyCode);
            $base = $this->cbc($doc, $price, 'BaseQuantity', '1');
            $base->setAttribute('unitCode', $line->unitCode);
        }

        return $doc->saveXML();
    }

    private function appendParty(DOMDocument $doc, DOMElement $root, string $role, DkCanonicalInvoiceParty $party, bool $supplier): void
    {
        $wrapper = $this->cac($doc, $root, $role);
        $node = $this->cac($doc, $wrapper, 'Party');
        $endpoint = $this->cbc($doc, $node, 'EndpointID', $party->endpointId);
        $endpoint->setAttribute('schemeID', $party->endpointScheme);
        $identification = $this->cac($doc, $node, 'PartyIdentification');
        $company = $this->cbc($doc, $identification, 'ID', $party->companyId);
        $company->setAttribute('schemeID', 'DK:CVR');
        $name = $this->cac($doc, $node, 'PartyName');
        $this->cbc($doc, $name, 'Name', $party->registrationName);
        $address = $this->cac($doc, $node, 'PostalAddress');
        $format = $this->cbc($doc, $address, 'AddressFormatCode', 'StructuredDK');
        $format->setAttribute('listAgencyID', '320');
        $format->setAttribute('listID', 'urn:oioubl:codelist:addressformatcode-1.1');
        $this->cbc($doc, $address, 'StreetName', $party->street);
        $this->cbc($doc, $address, 'BuildingNumber', $party->buildingNumber);
        $this->cbc($doc, $address, 'CityName', $party->city);
        $this->cbc($doc, $address, 'PostalZone', $party->postalCode);
        $country = $this->cac($doc, $address, 'Country');
        $this->cbc($doc, $country, 'IdentificationCode', $party->countryCode);
        if ($supplier) {
            $tax = $this->cac($doc, $node, 'PartyTaxScheme');
            $taxId = $this->cbc($doc, $tax, 'CompanyID', $party->companyId);
            $taxId->setAttribute('schemeID', 'DK:SE');
            $this->appendTaxScheme($doc, $tax);
        }
        $legal = $this->cac($doc, $node, 'PartyLegalEntity');
        $this->cbc($doc, $legal, 'RegistrationName', $party->registrationName);
        $legalId = $this->cbc($doc, $legal, 'CompanyID', $party->companyId);
        $legalId->setAttribute('schemeID', 'DK:CVR');
        if ($party->contactName !== null || $party->email !== null) {
            $contact = $this->cac($doc, $node, 'Contact');
            $this->cbc($doc, $contact, 'ID', '1');
            if ($party->contactName !== null) $this->cbc($doc, $contact, 'Name', $party->contactName);
            if ($party->email !== null) $this->cbc($doc, $contact, 'ElectronicMail', $party->email);
        }
    }

    private function appendTaxSubtotal(DOMDocument $doc, DOMElement $parent, string $base, string $tax, string $rate, string $currency): void
    {
        $subtotal = $this->cac($doc, $parent, 'TaxSubtotal');
        $this->amount($doc, $subtotal, 'TaxableAmount', $base, $currency);
        $this->amount($doc, $subtotal, 'TaxAmount', $tax, $currency);
        $category = $this->cac($doc, $subtotal, 'TaxCategory');
        $id = $this->cbc($doc, $category, 'ID', DkCanonicalDecimal::equals($rate, '0') ? 'ZeroRated' : 'StandardRated');
        $id->setAttribute('schemeAgencyID', '320');
        $id->setAttribute('schemeID', 'urn:oioubl:id:taxcategoryid-1.1');
        $this->cbc($doc, $category, 'Percent', $this->decimal($rate));
        $this->appendTaxScheme($doc, $category);
    }

    private function appendTaxScheme(DOMDocument $doc, DOMElement $parent): void
    {
        $scheme = $this->cac($doc, $parent, 'TaxScheme');
        $id = $this->cbc($doc, $scheme, 'ID', '63');
        $id->setAttribute('schemeAgencyID', '320');
        $id->setAttribute('schemeID', 'urn:oioubl:id:taxschemeid-1.1');
        $this->cbc($doc, $scheme, 'Name', 'Moms');
    }

    private function amount(DOMDocument $doc, DOMElement $parent, string $name, string $value, string $currency): DOMElement
    {
        $node = $this->cbc($doc, $parent, $name, $this->decimal($value));
        $node->setAttribute('currencyID', $currency);
        return $node;
    }

    private function decimal(string $value): string
    {
        $normalized = DkCanonicalDecimal::normalize($value);
        [$whole, $fraction] = explode('.', $normalized);
        if (trim(substr($fraction, 2), '0') !== '') {
            throw new InvalidArgumentException('OIOUBL amount exceeds two decimal places');
        }
        return $whole.'.'.substr($fraction, 0, 2);
    }

    private function cac(DOMDocument $doc, DOMElement $parent, string $name): DOMElement
    {
        $node = $doc->createElementNS(self::CAC_NS, 'cac:'.$name);
        $parent->appendChild($node);
        return $node;
    }

    private function cbc(DOMDocument $doc, DOMElement $parent, string $name, string $value): DOMElement
    {
        $node = $doc->createElementNS(self::CBC_NS, 'cbc:'.$name);
        $node->appendChild($doc->createTextNode($value));
        $parent->appendChild($node);
        return $node;
    }
}
