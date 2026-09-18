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
     * @return DkCanonicalTransaction[]
     */
    public function getTransactions($fromDate, $toDate);
}
