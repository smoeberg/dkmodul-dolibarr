<?php

define('NOLOGIN', 1);
define('NOREQUIREMENU', 1);
define('NOREQUIREHTML', 1);

require '/var/www/html/main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/custom/dkmodul/class/actions_dkmodul.class.php';

function setComplianceMode($db, $enabled)
{
    $value = $enabled ? '1' : '0';
    $sql = "UPDATE ".$db->prefix()."const SET value='".$db->escape($value)."' WHERE name='DKMODUL_COMPLIANCE_MODE' AND entity=1";
    if (!$db->query($sql)) {
        throw new RuntimeException($db->lasterror());
    }
}

function assertHookDecision($datatoimport, $expectedBlocked)
{
    $hook = new ActionsDkmodul();
    $object = new stdClass();
    $action = '';
    $hookmanager = new stdClass();

    $result = $hook->ImportInsert(
        array('datatoimport' => $datatoimport),
        $object,
        $action,
        $hookmanager
    );

    if ($expectedBlocked && $result >= 0) {
        throw new RuntimeException('Expected DK compliance import hook to reject '.$datatoimport);
    }

    if (!$expectedBlocked && $result < 0) {
        throw new RuntimeException('Expected DK compliance import hook to allow '.$datatoimport.': '.$hook->error);
    }
}

setComplianceMode($db, true);
assertHookDecision('accounting_2', true);
assertHookDecision('accounting_3', true);
assertHookDecision('accounting_2_fec', true);
assertHookDecision('invoice', false);

setComplianceMode($db, false);
assertHookDecision('accounting_2', false);

setComplianceMode($db, true);

echo "DK compliance import policy evidence passed\n";
