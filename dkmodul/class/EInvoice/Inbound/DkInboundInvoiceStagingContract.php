<?php

interface DkInboundInvoiceStagingContract
{
    public function stage(int $entity, string $channel, string $providerMessageId, string $senderScheme, string $senderId, string $xml, int $actorId): array;
}
