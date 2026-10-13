<?php

require_once __DIR__.'/DkInexchangeEndpointPolicy.php';

final class DkAccessPointCurlHttpClient implements DkAccessPointHttpClient
{
    public const DEFAULT_MAX_RESPONSE_BYTES = 10 * 1024 * 1024;

    private $baseUrl;
    private $timeout;
    private $maxResponseBytes;

    public function __construct($baseUrl, $timeout = 30, $maxResponseBytes = self::DEFAULT_MAX_RESPONSE_BYTES)
    {
        $this->baseUrl = DkInexchangeEndpointPolicy::assertAllowed($baseUrl);
        if (!is_int($timeout) || $timeout < 1) {
            throw new InvalidArgumentException('Access Point HTTP timeout must be a positive integer');
        }
        if (!is_int($maxResponseBytes) || $maxResponseBytes < 1024) {
            throw new InvalidArgumentException('Access Point HTTP response limit must be at least 1024 bytes');
        }

        $this->timeout = $timeout;
        $this->maxResponseBytes = $maxResponseBytes;
    }

    public function request($method, $path, array $headers = array(), $body = null, array $query = array())
    {
        if (!function_exists('curl_init')) {
            throw new RuntimeException('cURL is required for Access Point HTTP calls');
        }
        if (!is_string($method) || trim($method) === '' || !is_string($path) || $path === '') {
            throw new InvalidArgumentException('HTTP method and path are required');
        }

        $path = '/'.ltrim($path, '/');
        if (strpos($path, '/../') !== false || substr($path, -3) === '/..' || strpos($path, '/./') !== false) {
            throw new InvalidArgumentException('Access Point request path contains traversal segments');
        }

        $url = $this->baseUrl.$path;
        if (count($query)) {
            $encoded = http_build_query($query, '', '&', PHP_QUERY_RFC3986);
            if ($encoded !== '') $url .= '?'.$encoded;
        }

        $curl = curl_init($url);
        if ($curl === false) throw new RuntimeException('Unable to initialize cURL');

        $headerLines = array();
        foreach ($headers as $name => $value) {
            $headerLines[] = $name.': '.$value;
        }

        $responseBody = '';
        $responseTooLarge = false;
        $maxResponseBytes = $this->maxResponseBytes;
        $write = static function ($curlHandle, $chunk) use (&$responseBody, &$responseTooLarge, $maxResponseBytes) {
            $chunkLength = strlen($chunk);
            if (strlen($responseBody) + $chunkLength > $maxResponseBytes) {
                $responseTooLarge = true;
                return 0;
            }

            $responseBody .= $chunk;
            return $chunkLength;
        };

        curl_setopt($curl, CURLOPT_RETURNTRANSFER, false);
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, strtoupper($method));
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headerLines);
        curl_setopt($curl, CURLOPT_TIMEOUT, $this->timeout);
        curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, min(10, $this->timeout));
        curl_setopt($curl, CURLOPT_WRITEFUNCTION, $write);

        if ($body !== null) curl_setopt($curl, CURLOPT_POSTFIELDS, $body);

        $ok = curl_exec($curl);
        $error = curl_error($curl);
        $status = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);

        if ($responseTooLarge) {
            throw new RuntimeException(
                'Access Point HTTP response exceeded the configured limit of '.$this->maxResponseBytes.' bytes'
            );
        }

        if ($ok === false) {
            throw new RuntimeException('Access Point HTTP request failed: '.$error);
        }

        return array('status' => $status, 'body' => $responseBody);
    }
}
