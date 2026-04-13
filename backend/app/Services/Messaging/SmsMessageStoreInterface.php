<?php

namespace App\Services\Messaging;

interface SmsMessageStoreInterface
{
    public function store(SmsMessageRecord $message): SmsMessageRecord;

    /**
     * @return array<SmsMessageRecord>
     */
    public function forTenant(string $tenantDomain): array;
}
