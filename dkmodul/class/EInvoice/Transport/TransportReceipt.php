<?php

final readonly class DkTransportReceipt
{
    public string $status;
    public string $providerMessageId;
    public string $receiptCode;
    public string $receiptAt;
    public array $evidence;

    public function __construct(string $status, string $providerMessageId, string $receiptCode, string $receiptAt, array $evidence = array())
    {
        if (!in_array($status, array('accepted', 'rejected', 'failed'), true)) {
            throw new InvalidArgumentException('Transport receipt status is invalid');
        }
        $date = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $receiptAt, new DateTimeZone('UTC'));
        if (!$date || $date->format('Y-m-d H:i:s') !== $receiptAt) {
            throw new InvalidArgumentException('Transport receipt time must be UTC YYYY-MM-DD HH:MM:SS');
        }
        if ($status === 'accepted' && trim($providerMessageId) === '') {
            throw new InvalidArgumentException('Accepted transport receipt requires provider message id');
        }
        $this->status = $status;
        $this->providerMessageId = trim($providerMessageId);
        $this->receiptCode = trim($receiptCode);
        $this->receiptAt = $receiptAt;
        $this->evidence = $evidence;
    }
}
