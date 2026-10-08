<?php

require_once __DIR__.'/../../dkmodul/class/AccessPoint/DkAccessPointProvider.php';
require_once __DIR__.'/../../dkmodul/class/AccessPoint/DkAccessPointConnection.php';
require_once __DIR__.'/../../dkmodul/class/AccessPoint/DkAccessPointCompany.php';
require_once __DIR__.'/../../dkmodul/class/AccessPoint/DkAccessPointOutboundDocument.php';
require_once __DIR__.'/../../dkmodul/class/AccessPoint/DkAccessPointMessageReference.php';
require_once __DIR__.'/../../dkmodul/class/AccessPoint/DkAccessPointMessageResult.php';
require_once __DIR__.'/../../dkmodul/class/AccessPoint/DkAccessPointMessageStatus.php';
require_once __DIR__.'/../../dkmodul/class/AccessPoint/DkAccessPointDocument.php';
require_once __DIR__.'/../../dkmodul/class/AccessPoint/DkAccessPointHealth.php';
require_once __DIR__.'/../../dkmodul/class/AccessPoint/DkAccessPointCapabilities.php';
require_once __DIR__.'/../../dkmodul/class/AccessPoint/DkAccessPointPollCursor.php';
require_once __DIR__.'/../../dkmodul/class/AccessPoint/DkAccessPointHttpClient.php';
require_once __DIR__.'/../../dkmodul/class/AccessPoint/DkInexchangeAccessPointProvider.php';

final class InexchangeFakeHttpClient implements DkAccessPointHttpClient
{
    public $requests = array();
    public $responses = array();

    public function request($method, $path, array $headers = array(), $body = null, array $query = array())
    {
        $this->requests[] = array(
            'method' => $method,
            'path' => $path,
            'headers' => $headers,
            'body' => $body,
            'query' => $query,
        );

        $key = strtoupper($method).' '.$path;
        if (array_key_exists($key, $this->responses)) {
            $response = $this->responses[$key];
            unset($this->responses[$key]);
            return $response;
        }
        throw new RuntimeException('No fake response for '.strtoupper($method).' '.$path);
    }
}

$http = new InexchangeFakeHttpClient();
$http->responses = array(
    'GET /documents/incoming' => array('status' => 200, 'body' => json_encode(array('documents' => array()))),
);
$provider = new DkInexchangeAccessPointProvider($http);
$health = $provider->connect(new DkAccessPointConnection('inexchange', array(
    'api_key' => 'test-api-key',
    'client_token' => 'test-client-token',
)));
if (!$health instanceof DkAccessPointHealth || !$health->connected() || !$health->authenticated() || !$health->serviceAvailable()) {
    throw new RuntimeException('Inexchange connect/health contract failed');
}
if ($http->requests[0]['headers']['APIkey'] !== 'test-api-key' || $http->requests[0]['headers']['ClientToken'] !== 'test-client-token') {
    throw new RuntimeException('Inexchange authentication headers were not sent');
}

$http->responses['POST /companies/register'] = array('status' => 202, 'body' => json_encode(array('RegistrationId' => 'reg-1')));
$registrationId = $provider->registerCompany(new DkAccessPointCompany('12345678', 'Test ApS', '0088:5798000000000'));
if ($registrationId !== 'reg-1') throw new RuntimeException('Company registration id mismatch');

$http->responses['GET /companies/status'] = array('status' => 200, 'body' => json_encode(array('Status' => 'Completed')));
$status = $provider->registrationStatus('reg-1');
if ($status['Status'] !== 'Completed' || $status['_http_status'] !== 200) throw new RuntimeException('Registration status mismatch');

$http->responses['POST /documents'] = array('status' => 201, 'body' => json_encode(array('DocumentId' => 'doc-1', 'DocumentUri' => '/documents/doc-1')));
$http->responses['POST /documents/outbound'] = array('status' => 202, 'body' => json_encode(array('DocumentId' => 'doc-1')));
$result = $provider->send(new DkAccessPointOutboundDocument('inv-1', 'OIOUBL', '<Invoice/>', array('GLN' => '5798000000000'), 'erp-inv-1'));
if (!$result->accepted() || $result->providerReference() !== 'doc-1') throw new RuntimeException('Outbound send result mismatch');


// Provider must fail closed on HTTP errors instead of reporting a successful operation.
$http->responses['POST /documents'] = array('status' => 500, 'body' => json_encode(array('error' => 'server')));
$failed = false;
try {
    $provider->send(new DkAccessPointOutboundDocument('inv-http-error', 'OIOUBL', '<Invoice/>', array('GLN' => '5798000000000'), 'erp-http-error'));
} catch (RuntimeException $e) {
    $failed = strpos($e->getMessage(), 'HTTP 500') !== false;
}
if (!$failed) throw new RuntimeException('HTTP 500 was not fail-closed');

// Successful upload without a provider URI must not continue to outbound submission.
$http->responses['POST /documents'] = array('status' => 201, 'body' => json_encode(array('DocumentId' => 'doc-missing-uri')));
$failed = false;
try {
    $provider->send(new DkAccessPointOutboundDocument('inv-missing-uri', 'OIOUBL', '<Invoice/>', array('GLN' => '5798000000000'), 'erp-missing-uri'));
} catch (RuntimeException $e) {
    $failed = strpos($e->getMessage(), 'DocumentUri') !== false;
}
if (!$failed) throw new RuntimeException('Missing DocumentUri was not rejected');

// Successful outbound submission without a provider reference must not be reported as accepted.
$http->responses['POST /documents'] = array('status' => 201, 'body' => json_encode(array('DocumentId' => 'doc-no-reference', 'DocumentUri' => '/documents/doc-no-reference')));
$http->responses['POST /documents/outbound'] = array('status' => 202, 'body' => json_encode(array('Accepted' => true)));
$failed = false;
try {
    $provider->send(new DkAccessPointOutboundDocument('inv-no-reference', 'OIOUBL', '<Invoice/>', array('GLN' => '5798000000000'), 'erp-no-reference'));
} catch (RuntimeException $e) {
    $failed = strpos($e->getMessage(), 'provider document reference') !== false;
}
if (!$failed) throw new RuntimeException('Missing provider reference was not rejected');

// Invalid JSON from the provider must be rejected rather than treated as an empty result.
$http->responses['GET /invoices/outbound/doc-invalid-json'] = array('status' => 200, 'body' => '{invalid');
$failed = false;
try {
    $provider->outboundStatus(new DkAccessPointMessageReference('inv-invalid-json', 'doc-invalid-json'));
} catch (RuntimeException $e) {
    $failed = strpos($e->getMessage(), 'Invalid JSON') !== false;
}
if (!$failed) throw new RuntimeException('Invalid JSON was not rejected');

// Unknown provider statuses must fail closed instead of being guessed.
$http->responses['GET /invoices/outbound/doc-unknown-status'] = array('status' => 200, 'body' => json_encode(array('Status' => 'Mystery')));
$failed = false;
try {
    $provider->outboundStatus(new DkAccessPointMessageReference('inv-unknown-status', 'doc-unknown-status'));
} catch (RuntimeException $e) {
    $failed = strpos($e->getMessage(), 'Unsupported Inexchange outbound status') !== false;
}
if (!$failed) throw new RuntimeException('Unknown provider status was not rejected');

$http->responses['GET /invoices/outbound/doc-1'] = array('status' => 200, 'body' => json_encode(array('Status' => 'Delivered', 'UpdatedAt' => '2026-10-08T10:00:00Z')));
$status = $provider->outboundStatus(new DkAccessPointMessageReference('inv-1', 'doc-1'));
if ($status->status() !== 'DELIVERED') throw new RuntimeException('Outbound status normalization failed');

$http->responses['POST /documents/outbound/list'] = array('status' => 200, 'body' => json_encode(array('documents' => array())));
$list = $provider->listOutbound(new DkAccessPointPollCursor('2026-10-08T09:00:00Z'));
if (!isset($list['documents'])) throw new RuntimeException('Outbound polling failed');

$http->responses['GET /documents/incoming'] = array('status' => 200, 'body' => json_encode(array('documents' => array(array('DocumentId' => 'in-1')))));
$inbound = $provider->listInbound(new DkAccessPointPollCursor(null));
if (!isset($inbound['documents'])) throw new RuntimeException('Inbound polling failed');

$http->responses['GET /documents/in-1'] = array('status' => 200, 'body' => '<Invoice/>');
$downloaded = $provider->downloadInbound(new DkAccessPointMessageReference('in-1', 'in-1'));
if ($downloaded->payload() !== '<Invoice/>') throw new RuntimeException('Inbound download failed');

$http->responses['POST /documents/handled'] = array('status' => 202, 'body' => json_encode(array('Handled' => true)));
$handled = $provider->markInboundHandled(new DkAccessPointMessageReference('in-1', 'in-1'));
if (empty($handled['Handled'])) throw new RuntimeException('Inbound handled acknowledgement failed');

echo "Inexchange access point adapter contract: PASS\n";
