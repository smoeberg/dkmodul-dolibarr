<?php
/**
 * Dolibarr DK module descriptor.
 */

include_once DOL_DOCUMENT_ROOT.'/core/modules/DolibarrModules.class.php';

class modDkmodul extends DolibarrModules
{
    public function __construct($db)
    {
        global $conf;

        $this->db = $db;

        // Temporary project id. Reserve a permanent Dolibarr module id before release.
        $this->numero = 500500;
        $this->rights_class = 'dkmodul';
        $this->family = 'financial';
        $this->module_position = '90';
        $this->name = preg_replace('/^mod/i', '', get_class($this));
        $this->description = 'Danish bookkeeping compliance layer for Dolibarr';
        $this->version = 'development';
        $this->const_name = 'MAIN_MODULE_'.strtoupper($this->name);
        $this->picto = 'accounting';

        $this->module_parts = array(
            'triggers' => 1,
            'hooks' => array(),
        );

        $this->dirs = array('/dkmodul');
        $this->config_page_url = array();

        // Dolibarr Advanced Accounting is part of the product boundary.
        $this->depends = array('modAccounting');
        $this->requiredby = array();
        $this->conflictwith = array();

        $this->langfiles = array();
        $this->phpmin = array(8, 1);
        // Minimum supported Dolibarr version remains provisional until compatibility CI is established.
        $this->need_dolibarr_version = array(22, 0);

        $this->const = array(
            1 => array('DKMODUL_COMPLIANCE_MODE', 'yesno', '1', 'Enable Danish compliance mode', 0, 'current', 1),
        );

        if (!isModEnabled('dkmodul')) {
            $conf->dkmodul = new stdClass();
            $conf->dkmodul->enabled = 0;
        }

        $this->tabs = array();
        $this->dictionaries = array();
        $this->boxes = array();
        $this->cronjobs = array();

        $this->rights = array();
        $r = 0;

        $this->rights[$r][0] = $this->numero + 1;
        $this->rights[$r][1] = 'Read Danish compliance status';
        $this->rights[$r][4] = 'compliance';
        $this->rights[$r][5] = 'read';
        $r++;

        $this->rights[$r][0] = $this->numero + 2;
        $this->rights[$r][1] = 'Administer Danish compliance settings';
        $this->rights[$r][4] = 'compliance';
        $this->rights[$r][5] = 'admin';
    }

    public function init($options = '')
    {
        $sql = array();
        return $this->_init($sql, $options);
    }

    public function remove($options = '')
    {
        $sql = array();
        return $this->_remove($sql, $options);
    }
}
