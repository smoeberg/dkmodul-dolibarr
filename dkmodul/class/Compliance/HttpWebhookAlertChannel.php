<?php

require_once __DIR__.'/AlertDeliveryChannel.php';
require_once __DIR__.'/AlertEscalationChannel.php';

final class DkHttpWebhookAlertChannel implements DkAlertDeliveryChannel, DkAlertEscalationChannel
{
    private $url;
    private $token;
    private $timeoutSeconds;
    private $maxResponseBytes;

    public function __construct($url, $token, $timeoutSeconds = 10, $maxResponseBytes = 1048576)
    {
        if (!is_string($url) || trim($url) === '') {
            throw new RuntimeException('Alert webhook URL is required');
        }
        $parts = parse_url($url);
        if (!is_array($parts) || ($parts['scheme'] ?? '') !== 'https' || empty($parts['host']) || isset($parts['user']) || isset($parts['pass']) || isset($parts['query']) || isset($parts['fragment'])) {
            throw new RuntimeException('Alert webhook URL must be an approved HTTPS endpoint');
        }
        if (!is_string($token) || trim($token) === '') {
            throw new RuntimeException('Alert webhook authentication token is required');
        }
        if ((int) $timeoutSeconds < 1 || (int) $timeoutSeconds > 60) {
            throw new RuntimeException('Alert webhook timeout is invalid');
        }
        if ((int) $maxResponseBytes < 1024) {
            throw new RuntimeException('Alert webhook response limit is invalid');
        }
        $this->url = trim($url);
        $this->token = trim($token);
        $this->timeoutSeconds = (int) $timeoutSeconds;
        $this->maxResponseBytes = (int) $maxResponseBytes;
    }

    public function send(array $alert)
    {
        return $this->post($alert, 'primary');
    }

    public function escalate(array $alert, $attemptCount)
    {
        $alert['escalation_attempt'] = (int) $attemptCount;
        return $this->post($alert, 'escalation');
    }

    private function post(array $alert, $route)
    {
        if (!function_exists('curl_init')) {
            throw new RuntimeException('cURL is required for alert webhook delivery');
        }

        $payload = json_encode(array(
            'schema_version' => 1,
            'route' => $route,
            'alert' => $alert,
        ), JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $response = '';
        $handle = curl_init($this->url);
        curl_setopt_array($handle, array(
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_HTTPHEADER => array(
                'Content-Type: application/json',
                'Authorization: Bearer '.$this->token,
            ),
            CURLOPT_CONNECTTIMEOUT => min(10, $this->timeoutSeconds),
            CURLOPT_TIMEOUT => $this->timeoutSeconds,
            CURLOPT_RETURNTRANSFER => false,
            CURLOPT_WRITEFUNCTION => function ($handle, $chunk) use (&$response) {
                $nextLength = strlen($response) + strlen($chunk);
                if ($nextLength > $this->maxResponseBytes) {
                    return 0;
                }
                $response .= $chunk;
                return strlen($chunk);
            },
        ));
        $ok = curl_exec($handle);
        $errno = curl_errno($handle);
        $error = curl_error($handle);
        $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        curl_close($handle);

        if ($ok === false || $errno !== 0) {
            throw new RuntimeException('Alert webhook transport failed: '.$error);
        }
        if ($status < 200 || $status >= 300) {
            throw new RuntimeException('Alert webhook rejected delivery with HTTP '.$status);
        }

        return hash('sha256', $this->url.'|'.$route.'|'.$payload.'|'.$response);
    }
}
