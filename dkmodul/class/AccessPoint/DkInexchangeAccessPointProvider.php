<?php

require_once __DIR__.'/DkAccessPointProvider.php';
require_once __DIR__.'/DkAccessPointConnection.php';
require_once __DIR__.'/DkAccessPointCompany.php';
require_once __DIR__.'/DkAccessPointOutboundDocument.php';
require_once __DIR__.'/DkAccessPointMessageReference.php';
require_once __DIR__.'/DkAccessPointMessageResult.php';
require_once __DIR__.'/DkAccessPointMessageStatus.php';
require_once __DIR__.'/DkAccessPointDocument.php';
require_once __DIR__.'/DkAccessPointHealth.php';
require_once __DIR__.'/DkAccessPointCapabilities.php';
require_once __DIR__.'/DkAccessPointPollCursor.php';
require_once __DIR__.'/DkAccessPointHttpClient.php';

final class DkInexchangeAccessPointProvider implements DkAccessPointProvider
{
    private $http;
    private $apiKey;
    private $clientToken;
    private $connected = false;
    private $configuration = array();

    public function __construct(DkAccessPointHttpClient $http, $apiKey = null, $clientToken = null)
    {
        $this->http = $http;
        $this->apiKey = $apiKey;
        $this->clientToken = $clientToken;
    }

    public function capabilities()
    {
        return new DkAccessPointCapabilities(
            array('invoice'),
            array('outbound', 'inbound'),
            array('Peppol', 'NemHandel'),
            false,
            true
        );
    }

    public function health()
    {
        if (!$this->connected) {
            return new DkAccessPointHealth(false, false, false, 'Not connected');
        }

        try {
            $this->request('GET', '/documents/incoming');
            return new DkAccessPointHealth(true, true, true, 'Inexchange API reachable');
        } catch (Throwable $e) {
            return new DkAccessPointHealth(true, $this->hasCredentials(), false, $e->getMessage());
        }
    }

    public function connect(DkAccessPointConnection $connection)
    {
        if ($connection->adapterId() !== 'inexchange') {
            throw new InvalidArgumentException('Inexchange provider requires adapter id inexchange');
        }

        $configuration = $connection->configuration();
        $apiKey = isset($configuration['api_key']) ? trim((string) $configuration['api_key']) : '';
        $clientToken = isset($configuration['client_token']) ? trim((string) $configuration['client_token']) : '';
        if ($apiKey === '' || $clientToken === '') {
            throw new InvalidArgumentException('Inexchange API key and ClientToken are required');
        }

        $this->apiKey = $apiKey;
        $this->clientToken = $clientToken;
        $this->configuration = $configuration;
        $this->connected = true;
        return $this->health();
    }

    public function disconnect()
    {
        $this->connected = false;
        $this->apiKey = null;
        $this->clientToken = null;
        $this->configuration = array();
    }

    public function registerCompany(DkAccessPointCompany $company)
    {
        $response = $this->requestJson('POST', '/companies/register', array(
            'ErpId' => $company->organizationNumber(),
            'Name' => $company->name(),
            'ParticipantId' => $company->participantId(),
        ), array(202));

        $data = $this->decodeJson($response['body']);
        $registrationId = $data['RegistrationId'] ?? $data['registrationId'] ?? null;
        if (!is_string($registrationId) || trim($registrationId) === '') {
            throw new RuntimeException('Inexchange company registration response did not contain RegistrationId');
        }

        return trim($registrationId);
    }

    public function registrationStatus($registrationId)
    {
        if (!is_string($registrationId) || trim($registrationId) === '') {
            throw new InvalidArgumentException('Inexchange registration id is required');
        }

        $response = $this->request('GET', '/companies/status', array(), null, array('RegistrationId' => trim($registrationId)));
        $data = $this->decodeJson($response['body']);
        $status = $data['Status'] ?? $data['status'] ?? null;
        if (!is_string($status) || trim($status) === '') {
            throw new RuntimeException('Inexchange company registration status response did not contain Status');
        }
        $data['_http_status'] = $response['status'];
        return $data;
    }

    public function send(DkAccessPointOutboundDocument $document)
    {
        $idempotencyKey = $document->idempotencyKey();
        if (!is_string($idempotencyKey) || trim($idempotencyKey) === '') {
            throw new InvalidArgumentException('Inexchange outbound send requires an idempotency key');
        }

        $upload = $this->request(
            'POST',
            '/documents',
            array('Content-Type' => 'application/octet-stream'),
            $document->payload()
        );
        $this->assertStatus($upload, array(200, 201, 202), 'Inexchange document upload');

        $uploadData = $this->decodeJson($upload['body']);
        $documentUri = $uploadData['DocumentUri'] ?? $uploadData['documentUri'] ?? null;
        $providerDocumentId = $uploadData['DocumentId'] ?? $uploadData['documentId'] ?? null;
        if (!is_string($documentUri) || trim($documentUri) === '') {
            throw new RuntimeException('Inexchange upload response did not contain DocumentUri');
        }

        $payload = array(
            'DocumentFormat' => $document->format(),
            'DocumentUri' => $documentUri,
            'RecipientInformation' => $document->recipient(),
        );
        $payload['ErpDocumentId'] = trim($idempotencyKey);

        try {
            $sent = $this->requestJson('POST', '/documents/outbound', $payload, array(200, 201, 202));
            $sentData = $this->decodeJson($sent['body']);
        } catch (Throwable $e) {
            return $this->reconcileAmbiguousOutbound($document, $idempotencyKey, $e);
        }
        $reference = $sentData['DocumentId'] ?? $sentData['documentId'] ?? $providerDocumentId;
        if (!is_string($reference) || trim($reference) === '') {
            throw new RuntimeException('Inexchange outbound response did not contain a provider document reference');
        }
        return new DkAccessPointMessageResult(
            true,
            $document->documentId(),
            trim($reference),
            array('provider' => 'inexchange', 'response' => $sentData)
        );
    }

    private function reconcileAmbiguousOutbound(DkAccessPointOutboundDocument $document, $idempotencyKey, Throwable $originalException)
    {
        try {
            $response = $this->request('GET', '/invoices/outbound/byerpid/'.rawurlencode($idempotencyKey));
            $data = $this->decodeJson($response['body']);
            $reference = $data['DocumentId'] ?? $data['documentId'] ?? null;
            if (!is_string($reference) || trim($reference) === '') {
                throw new RuntimeException('Inexchange reconciliation response did not contain a provider document reference');
            }

            return new DkAccessPointMessageResult(
                true,
                $document->documentId(),
                trim($reference),
                array(
                    'provider' => 'inexchange',
                    'reconciled_after_ambiguous_submission' => true,
                    'original_error' => $originalException->getMessage(),
                    'response' => $data,
                )
            );
        } catch (Throwable $reconciliationException) {
            throw new RuntimeException(
                'Inexchange outbound submission was ambiguous; reconciliation did not establish a provider reference: '
                .$reconciliationException->getMessage(),
                0,
                $originalException
            );
        }
    }

    public function outboundStatus(DkAccessPointMessageReference $reference)
    {
        $providerId = $reference->providerReference();
        $idempotencyKey = $reference->idempotencyKey();
        $path = $providerId !== null && $providerId !== ''
            ? '/invoices/outbound/'.rawurlencode($providerId)
            : ($idempotencyKey !== null && $idempotencyKey !== ''
                ? '/invoices/outbound/byerpid/'.rawurlencode($idempotencyKey)
                : '/invoices/outbound/byerpid/'.rawurlencode($reference->documentId()));

        $response = $this->request('GET', $path);
        $data = $this->decodeJson($response['body']);
        $providerStatus = $data['Status'] ?? $data['status'] ?? null;
        if (!is_string($providerStatus) || trim($providerStatus) === '') {
            throw new RuntimeException('Inexchange outbound status response did not contain Status');
        }

        return new DkAccessPointMessageStatus(
            $this->normalizeStatus($providerStatus),
            $reference->documentId(),
            trim($providerStatus),
            $data['UpdatedAt'] ?? $data['updatedAt'] ?? null
        );
    }

    public function listOutbound(DkAccessPointPollCursor $cursor)
    {
        $query = array();
        if ($cursor->value() !== null) $query['UpdatedAfter'] = $cursor->value();

        $response = $this->request('POST', '/documents/outbound/list', array('Content-Type' => 'application/json'), json_encode($query));
        $this->assertStatus($response, array(200), 'Inexchange outbound list');
        return $this->decodeJson($response['body']);
    }

    public function listInbound(DkAccessPointPollCursor $cursor)
    {
        $query = array();
        if ($cursor->value() !== null) $query['UpdatedAfter'] = $cursor->value();

        $response = $this->request('GET', '/documents/incoming', array(), null, $query);
        return $this->decodeJson($response['body']);
    }

    public function downloadInbound(DkAccessPointMessageReference $reference)
    {
        $providerReference = $this->requireInboundProviderReference($reference);
        $response = $this->request('GET', '/documents/'.rawurlencode($providerReference));
        $this->assertStatus($response, array(200), 'Inexchange inbound document download');

        $contentType = 'application/octet-stream';
        return new DkAccessPointDocument(
            $reference->documentId(),
            $response['body'],
            $contentType,
            array('provider' => 'inexchange', 'provider_reference' => $providerReference)
        );
    }

    public function markInboundHandled(DkAccessPointMessageReference $reference)
    {
        $providerReference = $this->requireInboundProviderReference($reference);
        $payload = array(
            'DocumentId' => $providerReference,
        );
        $response = $this->request('POST', '/documents/handled', array('Content-Type' => 'application/json'), json_encode($payload));
        $this->assertStatus($response, array(200, 201, 202, 204), 'Inexchange inbound handled acknowledgement');

        if ((int) $response['status'] === 204) {
            return true;
        }

        $data = $this->decodeJson($response['body']);
        $handled = $data['Handled'] ?? $data['handled'] ?? null;
        if ($handled !== true) {
            throw new RuntimeException('Inexchange inbound handled response did not confirm Handled=true');
        }

        return $data;
    }

    private function requireInboundProviderReference(DkAccessPointMessageReference $reference)
    {
        $providerReference = $reference->providerReference();
        if (!is_string($providerReference) || trim($providerReference) === '') {
            throw new InvalidArgumentException('Inexchange inbound operations require a provider document reference');
        }

        return trim($providerReference);
    }

    public function createClientToken($erpId, $validTo, array $roles)
    {
        if (!is_string($erpId) || trim($erpId) === '') throw new InvalidArgumentException('ErpId is required');
        if (!is_string($validTo) || trim($validTo) === '') throw new InvalidArgumentException('ValidTo is required');
        $response = $this->requestJson('POST', '/clienttokens/create', array(
            'ErpId' => trim($erpId),
            'ValidTo' => trim($validTo),
            'Roles' => array_values($roles),
        ), array(200, 201, 202));
        $data = $this->decodeJson($response['body']);
        return $data['ClientToken'] ?? $data['clientToken'] ?? $data;
    }

    public function revokeClientToken($clientToken)
    {
        if (!is_string($clientToken) || trim($clientToken) === '') throw new InvalidArgumentException('ClientToken is required');
        $response = $this->requestJson('POST', '/clienttokens/revoke', array('ClientToken' => trim($clientToken)), array(200, 202, 204));
        return $response['status'] === 204 ? true : $this->decodeJson($response['body']);
    }

    private function request($method, $path, array $headers = array(), $body = null, array $query = array())
    {
        if (!$this->connected && !$this->hasCredentials()) {
            throw new RuntimeException('Inexchange provider is not connected');
        }

        $headers['APIkey'] = (string) $this->apiKey;
        $headers['ClientToken'] = (string) $this->clientToken;
        $headers['Accept'] = 'application/json';

        $response = $this->http->request($method, $path, $headers, $body, $query);
        if (!is_array($response) || !isset($response['status']) || !array_key_exists('body', $response)) {
            throw new RuntimeException('Invalid Inexchange HTTP client response');
        }
        if (!is_int($response['status']) && !ctype_digit((string) $response['status'])) {
            throw new RuntimeException('Invalid Inexchange HTTP status');
        }
        $this->assertStatus($response, range(200, 299), 'Inexchange API request');
        return $response;
    }

    private function requestJson($method, $path, array $payload, array $allowedStatuses = array(200))
    {
        $response = $this->request($method, $path, array('Content-Type' => 'application/json'), json_encode($payload));
        $this->assertStatus($response, $allowedStatuses, 'Inexchange API request');
        return $response;
    }

    private function assertStatus(array $response, array $allowedStatuses, $operation)
    {
        if (!in_array((int) $response['status'], $allowedStatuses, true)) {
            throw new RuntimeException($operation.' returned HTTP '.(int) $response['status']);
        }
    }

    private function decodeJson($body)
    {
        $data = json_decode((string) $body, true);
        if (!is_array($data)) throw new RuntimeException('Invalid JSON response from Inexchange');
        return $data;
    }

    private function normalizeStatus($providerStatus)
    {
        $status = strtoupper(trim((string) $providerStatus));
        if (in_array($status, array('PENDING', 'SENT', 'DELIVERED', 'FAILED', 'STOPPED'), true)) return $status;
        if (in_array($status, array('ERROR', 'REJECTED', 'CANCELLED'), true)) return 'FAILED';
        throw new RuntimeException('Unsupported Inexchange outbound status: '.$providerStatus);
    }

    private function hasCredentials()
    {
        return is_string($this->apiKey) && trim($this->apiKey) !== ''
            && is_string($this->clientToken) && trim($this->clientToken) !== '';
    }
}
