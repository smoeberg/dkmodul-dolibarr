<?php

/**
 * Contract between a source ERP/accounting system and reporting/export layers.
 *
 * SAF-T mappers must depend on this interface/canonical model, not on Dolibarr SQL.
 */
interface DkAccountingDataProviderInterface
{
    /** @return DkCanonicalCompanyContext */
    public function getCompanyContext();

    /** @return DkCanonicalAccount[] */
    public function getAccounts($fromDate, $toDate);

    /** @return DkCanonicalParty[] */
    public function getParties($fromDate, $toDate);

    /**
     * Tax/VAT code catalogue relevant for the export period.
     * Standard-tax-code mapping may be supplied by a separate DK mapping service.
     *
     * @return DkCanonicalTaxCode[]
     */
    public function getTaxCodes($fromDate, $toDate);

    /**
     * @return DkCanonicalTransaction[]
     */
    public function getTransactions($fromDate, $toDate);
}
