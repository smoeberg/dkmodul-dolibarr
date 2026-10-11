<?php

require_once __DIR__.'/DkInexchangeEndpointPolicy.php';

final class DkAccessPointCurlHttpClient implements DkAccessPointHttpClient
{
    private $baseUrl;
    private $timeout;

    public function __construct($baseUrl, $timeout = 30)
    {
        $this->baseUrl = DkInexchangeEndpointPolicy::assertAllowed($baseUrl);
        if (!is_int($timeout) || $timeout < 1) {
            throw new InvalidArgumentException('Access Point HTTP timeout must be a positive integer');
        }

        $this->timeout = $timeout;
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

        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, strtoupper($method));
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headerLines);
        curl_setopt($curl, CURLOPT_TIMEOUT, $this->timeout);
        curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, min(10, $this->timeout));

        if ($body !== null) curl_setopt($curl, CURLOPT_POSTFIELDS, $body);

        $responseBody = curl_exec($curl);
        $error = curl_error($curl);
        $status = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);

        if ($responseBody === false) {
            throw new RuntimeException('Access Point HTTP request failed: '.$error);
        }

        return array('status' => $status, 'body' => (string) $responseBody);
    }
}
