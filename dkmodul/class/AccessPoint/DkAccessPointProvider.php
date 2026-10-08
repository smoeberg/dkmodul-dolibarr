<?php

interface DkAccessPointProvider
{
    public function capabilities();
    public function health();
    public function connect(DkAccessPointConnection $connection);
    public function disconnect();
    public function registerCompany(DkAccessPointCompany $company);
    public function registrationStatus($registrationId);
    public function send(DkAccessPointOutboundDocument $document);
    public function outboundStatus(DkAccessPointMessageReference $reference);
    public function listOutbound(DkAccessPointPollCursor $cursor);
    public function listInbound(DkAccessPointPollCursor $cursor);
    public function downloadInbound(DkAccessPointMessageReference $reference);
    public function markInboundHandled(DkAccessPointMessageReference $reference);
}
