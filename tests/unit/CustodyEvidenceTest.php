<?php

require_once __DIR__.'/../../dkmodul/class/Operations/DkEvidenceSigner.php';
require_once __DIR__.'/../../dkmodul/class/Operations/DkCustodyEvidence.php';

final class TestEvidenceSigner implements DkEvidenceSigner
{
    public function sign(array $evidence): array
    {
        return array(
            'algorithm' => 'ed25519',
            'keyId' => 'production-key-01',
            'signature' => hash('sha256', json_encode($evidence)),
        );
    }
}

$signer = new TestEvidenceSigner();
$evidence = array(
    'schemaVersion' => '1',
    'evidenceSha256' => hash('sha256', 'evidence'),
);

$signature = $signer->sign($evidence);
$attached = DkCustodyEvidence::attach($evidence, $signature);

assert($attached['custody']['algorithm'] === 'ED25519');
assert($attached['custody']['keyId'] === 'production-key-01');
assert($attached['custody']['signature'] !== '');

$throws = false;
try {
    DkCustodyEvidence::attach($evidence, array('algorithm' => 'ed25519'));
} catch (InvalidArgumentException $e) {
    $throws = true;
}
assert($throws === true);

$throws = false;
try {
    DkCustodyEvidence::attach($evidence, array(
        'algorithm' => 'ed25519',
        'keyId' => str_repeat('k', 256),
        'signature' => 'x',
    ));
} catch (InvalidArgumentException $e) {
    $throws = true;
}
assert($throws === true);

echo "Signing and custody evidence contract passed\n";
