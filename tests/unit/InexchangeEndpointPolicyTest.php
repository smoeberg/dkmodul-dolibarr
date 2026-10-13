<?php

require_once dirname(__DIR__, 2).'/dkmodul/class/AccessPoint/DkInexchangeEndpointPolicy.php';
require_once dirname(__DIR__, 2).'/dkmodul/class/AccessPoint/DkAccessPointCurlHttpClient.php';

function assertSameValue($expected, $actual, $message)
{
    if ($expected !== $actual) {
        throw new RuntimeException($message.' expected='.var_export($expected, true).' actual='.var_export($actual, true));
    }
}

function assertThrows(callable $callback, $message)
{
    try {
        $callback();
    } catch (InvalidArgumentException $e) {
        return;
    }

    throw new RuntimeException($message);
}

assertSameValue(
    10 * 1024 * 1024,
    DkAccessPointCurlHttpClient::DEFAULT_MAX_RESPONSE_BYTES,
    'Access Point HTTP client must enforce a bounded response size by default'
);

assertSameValue(
    'https://api.inexchange.com/v1/api',
    DkInexchangeEndpointPolicy::defaultBaseUrl(),
    'production default must be the official InExchange API origin'
);

assertSameValue(
    'https://api.inexchange.com/v1/api',
    DkInexchangeEndpointPolicy::assertAllowed('https://api.inexchange.com/v1/api/'),
    'production endpoint should normalize a trailing slash'
);

assertSameValue(
    'https://testapi.inexchange.se/v1/api',
    DkInexchangeEndpointPolicy::assertAllowed('https://testapi.inexchange.se/v1/api'),
    'test endpoint should be explicitly supported'
);

assertThrows(
    function () { DkInexchangeEndpointPolicy::assertAllowed('http://api.inexchange.com/v1/api'); },
    'HTTP must be rejected'
);

assertThrows(
    function () { DkInexchangeEndpointPolicy::assertAllowed('https://evil.example/v1/api'); },
    'unapproved host must be rejected'
);

assertThrows(
    function () { DkInexchangeEndpointPolicy::assertAllowed('https://api.inexchange.com.attacker.example/v1/api'); },
    'lookalike host must be rejected'
);

assertThrows(
    function () { DkInexchangeEndpointPolicy::assertAllowed('https://api.inexchange.com/v1/api?proxy=https://evil.example'); },
    'query parameters must be rejected'
);

assertThrows(
    function () { DkInexchangeEndpointPolicy::assertAllowed('https://user:pass@api.inexchange.com/v1/api'); },
    'embedded credentials must be rejected'
);

assertThrows(
    function () { new DkAccessPointCurlHttpClient('https://api.inexchange.com/v1/api', 30, 1023); },
    'HTTP response limit below the minimum must be rejected'
);

assertThrows(
    function () { DkInexchangeEndpointPolicy::assertAllowed('https://api.inexchange.com/v1/api/../internal'); },
    'path traversal must not expand the approved endpoint'
);

echo "Inexchange endpoint policy evidence passed\n";
