<?php

require '../../main.inc.php';

require_once __DIR__.'/../class/AccessPoint/DkAccessPointConnection.php';
require_once __DIR__.'/../class/AccessPoint/DkAccessPointCurlHttpClient.php';
require_once __DIR__.'/../class/AccessPoint/DkInexchangeAccessPointProvider.php';

if (!$user->hasRight('dkmodul', 'compliance', 'admin')) {
    accessforbidden();
}

$langs->loadLangs(array('admin', 'dkmodul'));

$action = GETPOST('action', 'aZ09');

$baseUrl = isset($conf->global->DKMODUL_INEXCHANGE_BASE_URL)
    ? trim((string) $conf->global->DKMODUL_INEXCHANGE_BASE_URL)
    : 'https://api.inexchange.com';

$erpId = isset($conf->global->DKMODUL_INEXCHANGE_ERP_ID)
    ? trim((string) $conf->global->DKMODUL_INEXCHANGE_ERP_ID)
    : '';

$message = '';
$messageType = 'ok';

if ($action === 'save') {
    $baseUrlInput = trim(GETPOST('base_url', 'alphanohtml'));
    $erpIdInput = trim(GETPOST('erp_id', 'alphanohtml'));

    if ($baseUrlInput === '' || !preg_match('/^https:\/\//i', $baseUrlInput)) {
        $message = 'Inexchange base URL skal være en HTTPS-URL.';
        $messageType = 'error';
    } elseif ($erpIdInput === '') {
        $message = 'ERP/company ID skal udfyldes.';
        $messageType = 'error';
    } else {
        dolibarr_set_const($db, 'DKMODUL_INEXCHANGE_BASE_URL', rtrim($baseUrlInput, '/'), 'chaine', 0, '', $conf->entity);
        dolibarr_set_const($db, 'DKMODUL_INEXCHANGE_ERP_ID', $erpIdInput, 'chaine', 0, '', $conf->entity);

        $baseUrl = rtrim($baseUrlInput, '/');
        $erpId = $erpIdInput;
        $message = 'Inexchange-indstillingerne er gemt.';
    }
}

$apiKeyConfigured = is_string(getenv('DKMODUL_INEXCHANGE_API_KEY')) && trim((string) getenv('DKMODUL_INEXCHANGE_API_KEY')) !== '';
$clientTokenConfigured = is_string(getenv('DKMODUL_INEXCHANGE_CLIENT_TOKEN')) && trim((string) getenv('DKMODUL_INEXCHANGE_CLIENT_TOKEN')) !== '';

if ($action === 'test' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $apiKey = getenv('DKMODUL_INEXCHANGE_API_KEY');
    $clientToken = getenv('DKMODUL_INEXCHANGE_CLIENT_TOKEN');

    if (!$apiKeyConfigured || !$clientTokenConfigured) {
        $message = 'Forbindelsestest kan ikke køres: DKMODUL_INEXCHANGE_API_KEY og DKMODUL_INEXCHANGE_CLIENT_TOKEN skal være konfigureret i runtime-miljøet.';
        $messageType = 'error';
    } else {
        try {
            $http = new DkAccessPointCurlHttpClient($baseUrl);
            $provider = new DkInexchangeAccessPointProvider($http);
            $provider->connect(new DkAccessPointConnection('inexchange', array(
                'api_key' => $apiKey,
                'client_token' => $clientToken,
            )));

            $health = $provider->health();
            if ($health->serviceAvailable()) {
                $message = 'Forbindelsen til Inexchange API er OK.';
            } else {
                $message = 'Inexchange kunne kontaktes, men API-servicen blev ikke godkendt: '.$health->message();
                $messageType = 'error';
            }
        } catch (Throwable $e) {
            $message = 'Forbindelsestest fejlede: '.$e->getMessage();
            $messageType = 'error';
        }
    }
}

$title = 'Inexchange – Access Point';
llxHeader('', $title);

print load_fiche_titre($title, '', 'generic');

if ($message !== '') {
    $class = $messageType === 'error' ? 'error' : 'ok';
    print '<div class="'.$class.'">'.dol_escape_htmltag($message).'</div>';
}

print '<form method="POST" action="'.dol_escape_htmltag($_SERVER['PHP_SELF']).'">';
print '<input type="hidden" name="token" value="'.newToken().'">';

print '<table class="noborder centpercent">';
print '<tr class="liste_titre"><th colspan="2">Inexchange P0-konfiguration</th></tr>';

print '<tr><td class="titlefield">Base URL</td><td><input class="minwidth400" type="url" name="base_url" value="'.dol_escape_htmltag($baseUrl).'"></td></tr>';
print '<tr><td>ERP/company ID</td><td><input class="minwidth300" type="text" name="erp_id" value="'.dol_escape_htmltag($erpId).'"></td></tr>';

print '<tr><td>API key</td><td>';
print $apiKeyConfigured ? '<span class="badge badge-status4">Konfigureret</span>' : '<span class="badge badge-status8">Mangler</span>';
print ' <span class="opacitymedium">DKMODUL_INEXCHANGE_API_KEY</span></td></tr>';

print '<tr><td>ClientToken</td><td>';
print $clientTokenConfigured ? '<span class="badge badge-status4">Konfigureret</span>' : '<span class="badge badge-status8">Mangler</span>';
print ' <span class="opacitymedium">DKMODUL_INEXCHANGE_CLIENT_TOKEN</span></td></tr>';

print '<tr><td>Provider</td><td><strong>Inexchange</strong> <span class="opacitymedium">P0</span></td></tr>';
print '</table>';

print '<div class="center" style="margin-top: 16px;">';
print '<input type="hidden" name="action" value="save">';
print '<input type="submit" class="button" value="Gem indstillinger">';
print '</div>';
print '</form>';

print '<div class="underbanner clearboth"></div>';

print '<form method="POST" action="'.dol_escape_htmltag($_SERVER['PHP_SELF']).'">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<div class="center"><input type="hidden" name="action" value="test">';
print '<input type="submit" class="button button-small" value="Test Inexchange-forbindelse"></div>';
print '</form>';

print '<div class="info" style="margin-top: 16px;">';
print '<strong>Sikkerhedsmodel:</strong> API key og ClientToken gemmes ikke i Dolibarr-databasen og vises aldrig i GUI’en. De skal leveres som runtime secrets.';
print '</div>';

llxFooter();
$db->close();
