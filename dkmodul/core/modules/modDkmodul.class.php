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
        $this->version = '0.1.0-p0';
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
        $this->phpmin = array(8, 2);
        // Initial registered product baseline: Dolibarr 24.0.x.
        $this->need_dolibarr_version = array(24, 0);

        $this->const = array(
            1 => array('DKMODUL_COMPLIANCE_MODE', 'yesno', '1', 'Enable Danish compliance mode', 0, 'current', 1),
            2 => array('DKMODUL_REGISTERED_PROFILE', 'yesno', '0', 'Lock the registered Danish compliance profile', 0, 'current', 1),
            3 => array('DKMODUL_DEPLOYMENT_ATTESTATION_PATH', 'chaine', '', 'Absolute path to the deployment attestation JSON file', 0, 'current', 1),
        );

        if (!isModEnabled('dkmodul')) {
            $conf->dkmodul = new stdClass();
            $conf->dkmodul->enabled = 0;
        }

        $this->tabs = array();
        $this->dictionaries = array();
        $this->boxes = array();
        $this->cronjobs = array();

        $this->menu = array();
        $this->menu[] = array(
            'fk_menu' => 'fk_mainmenu=billing',
            'type' => 'left',
            'titre' => 'Inbound OIOUBL',
            'mainmenu' => 'billing',
            'leftmenu' => 'dkmodul_inbound',
            'url' => '/dkmodul/einvoice/inbound.php',
            'langs' => '',
            'position' => 100,
            'enabled' => 'isModEnabled("dkmodul")',
            'perms' => '$user->hasRight("dkmodul", "inbound", "read")',
            'target' => '',
            'user' => 2,
        );

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
        $r++;

        $this->rights[$r][0] = $this->numero + 10;
        $this->rights[$r][1] = 'Read inbound OIOUBL workflow';
        $this->rights[$r][4] = 'inbound';
        $this->rights[$r][5] = 'read';
        $r++;

        $this->rights[$r][0] = $this->numero + 11;
        $this->rights[$r][1] = 'Approve inbound OIOUBL supplier drafts';
        $this->rights[$r][4] = 'inbound';
        $this->rights[$r][5] = 'approve';
        $r++;

        $this->rights[$r][0] = $this->numero + 12;
        $this->rights[$r][1] = 'Validate inbound OIOUBL supplier invoices';
        $this->rights[$r][4] = 'inbound';
        $this->rights[$r][5] = 'validate';
        $r++;

        $this->rights[$r][0] = $this->numero + 13;
        $this->rights[$r][1] = 'Post inbound OIOUBL supplier invoices';
        $this->rights[$r][4] = 'inbound';
        $this->rights[$r][5] = 'post';
    }

    public function init($options = '')
    {
        // Module tables must exist before database guards/provenance triggers are installed.
        $result = $this->_load_tables('/dkmodul/sql/');
        if ($result < 0) {
            return -1;
        }

        $sql = array();
        $result = $this->_init($sql, $options);
        if ($result <= 0) {
            return $result;
        }

        require_once dirname(__DIR__, 2).'/class/Compliance/DatabaseGuardInstaller.php';

        try {
            $installer = new DkDatabaseGuardInstaller($this->db);
            return $installer->install();
        } catch (Throwable $e) {
            $this->error = $e->getMessage();
            return -1;
        }
    }

    public function remove($options = '')
    {
        require_once dirname(__DIR__, 2).'/class/Compliance/ComplianceLock.php';
        require_once dirname(__DIR__, 2).'/class/Compliance/DatabaseGuardInstaller.php';

        try {
            DkComplianceLock::assertRemovalAllowed((bool) getDolGlobalInt('DKMODUL_REGISTERED_PROFILE'));
            $installer = new DkDatabaseGuardInstaller($this->db);
            $installer->drop();
        } catch (Throwable $e) {
            $this->error = $e->getMessage();
            return -1;
        }

        $sql = array();
        return $this->_remove($sql, $options);
    }
}
