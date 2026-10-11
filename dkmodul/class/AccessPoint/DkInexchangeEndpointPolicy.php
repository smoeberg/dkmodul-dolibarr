<?php

final class DkInexchangeEndpointPolicy
{
    private const PRODUCTION_ORIGIN = 'https://api.inexchange.com/v1/api';
    private const TEST_ORIGIN = 'https://testapi.inexchange.se/v1/api';

    public static function defaultBaseUrl()
    {
        return self::PRODUCTION_ORIGIN;
    }

    public static function assertAllowed($baseUrl)
    {
        if (!is_string($baseUrl) || trim($baseUrl) === '') {
            throw new InvalidArgumentException('Inexchange base URL is required');
        }

        $url = rtrim(trim($baseUrl), '/');
        $parts = parse_url($url);

        if (!is_array($parts) || strtolower((string) ($parts['scheme'] ?? '')) !== 'https') {
            throw new InvalidArgumentException('Inexchange base URL must use HTTPS');
        }

        if (isset($parts['user']) || isset($parts['pass']) || isset($parts['query']) || isset($parts['fragment'])) {
            throw new InvalidArgumentException('Inexchange base URL must not contain credentials, query parameters, or fragments');
        }

        $normalized = 'https://'.strtolower((string) ($parts['host'] ?? ''));
        if (isset($parts['port'])) {
            $normalized .= ':'.(int) $parts['port'];
        }
        $normalized .= rtrim((string) ($parts['path'] ?? ''), '/');

        if (!in_array($normalized, array(self::PRODUCTION_ORIGIN, self::TEST_ORIGIN), true)) {
            throw new InvalidArgumentException(
                'Inexchange base URL is not an approved InExchange endpoint'
            );
        }

        return $normalized;
    }
}
