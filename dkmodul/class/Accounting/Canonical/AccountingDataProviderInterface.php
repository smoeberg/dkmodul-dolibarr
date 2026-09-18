<?php

/**
 * Contract between a source ERP/accounting system and reporting/export layers.
 *
 * SAF-T mappers must depend on this interface/canonical model, not on Dolibarr SQL.
 */
interface DkAccountingDataProviderInterface
{
    public function getCompanyContext();

    public function getAccounts($fromDate, $toDate);

    public function getParties($fromDate, $toDate);

    /**
     * Tax/VAT code catalogue relevant for the export period.
     * Standard-tax-code mapping may be supplied by a separate DK mapping service.
     */
    public function getTaxCodes($fromDate, $toDate);

    /**
     * @return DkCanonicalTransaction[]
     */
    public function getTransactions($fromDate, $toDate);
}
