<?php

require_once dirname(__DIR__, 2).'/dkmodul/class/Compliance/HttpWebhookAlertChannel.php';

$channel = new DkHttpWebhookAlertChannel('https://alerts.example.invalid/webhook', 'test-token');
assert($channel instanceof DkAlertDeliveryChannel);
assert($channel instanceof DkAlertEscalationChannel);

$failed = false;
try {
    new DkHttpWebhookAlertChannel('http://alerts.example.invalid/webhook', 'test-token');
} catch (RuntimeException $e) {
    $failed = true;
}
assert($failed === true);

$failed = false;
try {
    new DkHttpWebhookAlertChannel('https://alerts.example.invalid/webhook?secret=bad', 'test-token');
} catch (RuntimeException $e) {
    $failed = true;
}
assert($failed === true);

$failed = false;
try {
    new DkHttpWebhookAlertChannel('https://alerts.example.invalid/webhook', '');
} catch (RuntimeException $e) {
    $failed = true;
}
assert($failed === true);

echo "HTTP webhook alert channel tests passed\n";
