<?php

require_once DOL_DOCUMENT_ROOT.'/custom/dkmodul/class/Compliance/ImportPolicy.php';

/**
 * Dolibarr imports hook for DK compliance enforcement.
 *
 * Dolibarr's imports/import.php invokes ImportInsert immediately before
 * AccountancyImport performs the row insertion.
 */
class ActionsDkmodul
{
    public $errors = array();
    public $error = '';

    public function ImportInsert($parameters, &$object, &$action, $hookmanager)
    {
        $datatoimport = isset($parameters['datatoimport']) ? (string) $parameters['datatoimport'] : '';
        $complianceMode = (bool) getDolGlobalInt('DKMODUL_COMPLIANCE_MODE');

        try {
            DkComplianceImportPolicy::assertAllowed($datatoimport, $complianceMode);
        } catch (Throwable $e) {
            $this->error = $e->getMessage();
            $this->errors[] = $this->error;
            dol_syslog($this->error, LOG_WARNING);
            return -1;
        }

        return 0;
    }
}
