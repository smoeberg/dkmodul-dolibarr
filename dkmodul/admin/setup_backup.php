<?php

/* Admin screen: per-installation backup configuration.
 *
 * Backup configuration is never hardcoded in code. The platform, provider,
 * destination, schedules and EU/EEA location are set per deployment through
 * the module constants and proven by the signed deployment attestation.
 */

require '../../main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/custom/dkmodul/class/Compliance/BackupConfiguration.php';
require_once DOL_DOCUMENT_ROOT.'/custom/dkmodul/class/Compliance/BackupComplianceMonitor.php';

$langs->load('admin');
$langs->load('errors');

if (!$user->admin) {
    accessforbidden();
}

$constKeys = array(
    'DKMODUL_COMPLIANCE_MODE',
    'DKMODUL_REGISTERED_PROFILE',
    'DKMODUL_DEPLOYMENT_ATTESTATION_PATH',
    'DKMODUL_ATTESTATION_TRUST_STORE_PATH',
    'DKMODUL_DEPLOYMENT_ID',
    'DKMODUL_REQUIRED_RETAIN_UNTIL',
    'DKMODUL_HOSTING_REGISTRATION',
);

$action = GETPOST('action', 'aZ09');

if ($action === 'save' && GETPOST('token') === newToken()) {
    foreach ($constKeys as $key) {
        $value = GETPOST($key, 'alphanohtml');
        dolibarr_set_const($db, $key, $value, 'chaine', 0, '', $conf->entity);
    }
    setEventMessages($langs->trans('SetupSaved'), null, 'mesgs');
    header('Location: '.$_SERVER['PHP_SELF']);
    exit;
}

$settings = array();
foreach ($constKeys as $key) {
    $settings[$key] = getDolGlobalString($key);
}

$resolved = null;
$error = null;
$summary = null;

if (!empty($settings['DKMODUL_COMPLIANCE_MODE'])) {
    try {
        $resolved = DkBackupConfiguration::resolve($settings, DOL_DOCUMENT_ROOT.'/custom/dkmodul/product-manifest.json');
        $summary = DkBackupConfiguration::summarize($resolved);
    } catch (RuntimeException $e) {
        $error = $e->getMessage();
    }
}

$complianceStatus = null;
if (is_array($resolved) && !empty($settings['DKMODUL_DEPLOYMENT_ID'])) {
    try {
        $monitor = new DkBackupComplianceMonitor($db);
        $complianceStatus = $monitor->status(
            $conf->entity,
            $settings['DKMODUL_DEPLOYMENT_ID'],
            $settings['DKMODUL_REQUIRED_RETAIN_UNTIL'],
            $settings['DKMODUL_HOSTING_REGISTRATION']
        );
    } catch (RuntimeException $e) {
        $error = $e->getMessage();
    }
}

llxHeader('', 'Dolibarr DK - Backup configuration');

print load_fiche_titre('Dolibarr DK - Backup configuration', '', 'technic');

if ($error !== null) {
    print '<div class="error">'.dol_escape_htmltag($error).'</div>';
}

print '<p>'.dol_escape_htmltag('Backup configuration is set per installation through the signed deployment attestation. No values are hardcoded in code.').'</p>';

print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="action" value="save">';
print '<table class="noborder centpercent">';
print '<tr class="liste_titre"><th>Constant</th><th>Value</th></tr>';
foreach ($constKeys as $key) {
    print '<tr><td>'.dol_escape_htmltag($key).'</td><td>';
    if ($key === 'DKMODUL_COMPLIANCE_MODE' || $key === 'DKMODUL_REGISTERED_PROFILE') {
        print '<input type="checkbox" name="'.$key.'" value="1"'.(empty($settings[$key]) ? '' : ' checked').'>';
    } else {
        print '<input class="flat" size="80" name="'.$key.'" value="'.dol_escape_htmltag((string) $settings[$key]).'">';
    }
    print '</td></tr>';
}
print '</table>';
print '<div class="center"><input type="submit" class="button" value="Save"></div>';
print '</form>';

if (is_array($summary)) {
    print '<h3>Resolved backup configuration (from signed attestation)</h3>';
    print '<table class="noborder centpercent">';
    foreach ($summary as $label => $value) {
        print '<tr><td>'.dol_escape_htmltag($label).'</td><td>'.dol_escape_htmltag((string) $value).'</td></tr>';
    }
    print '</table>';
}

if (is_array($complianceStatus)) {
    print '<h3>Current backup compliance status</h3>';
    print '<p>'.($complianceStatus['compliant'] ? 'COMPLIANT' : 'NOT COMPLIANT').'</p>';
    if (!empty($complianceStatus['reasons'])) {
        print '<ul>';
        foreach ($complianceStatus['reasons'] as $reason) {
            print '<li>'.dol_escape_htmltag((string) $reason).'</li>';
        }
        print '</ul>';
    }
}

llxFooter();
