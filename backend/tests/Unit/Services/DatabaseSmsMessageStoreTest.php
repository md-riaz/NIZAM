<?php

namespace Tests\Unit\Services;

use App\Services\Messaging\DatabaseSmsMessageStore;
use App\Services\Messaging\SmsMessageRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DatabaseSmsMessageStoreTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_persists_and_reads_back_sms_messages_for_a_tenant(): void
    {
        $store = new DatabaseSmsMessageStore;

        $message = new SmsMessageRecord(
            id: (string) fake()->uuid(),
            tenantDomain: 'tenant.example.com',
            direction: SmsMessageRecord::DIRECTION_OUTBOUND,
            from: '+15550000001',
            to: '+15550000002',
            body: 'Persist me',
            status: SmsMessageRecord::STATUS_SENT,
            provider: 'telnyx',
            providerMessageId: 'tx-db-1',
            failureReason: null,
            metadata: ['route' => ['strategy' => 'preferred_provider']],
            createdAt: now()->toDateTimeImmutable(),
        );

        $store->store($message);

        $history = $store->forTenant('tenant.example.com');

        $this->assertCount(1, $history);
        $this->assertSame('tenant.example.com', $history[0]->tenantDomain);
        $this->assertSame('telnyx', $history[0]->provider);
        $this->assertSame('tx-db-1', $history[0]->providerMessageId);
        $this->assertSame('preferred_provider', $history[0]->metadata['route']['strategy']);
    }

    public function test_it_filters_history_by_tenant_in_database_store(): void
    {
        $store = new DatabaseSmsMessageStore;

        $store->store(new SmsMessageRecord(
            id: (string) fake()->uuid(),
            tenantDomain: 'a.example.com',
            direction: SmsMessageRecord::DIRECTION_OUTBOUND,
            from: '+1',
            to: '+2',
            body: 'A',
            status: SmsMessageRecord::STATUS_SENT,
            createdAt: now()->subMinute()->toDateTimeImmutable(),
        ));

        $store->store(new SmsMessageRecord(
            id: (string) fake()->uuid(),
            tenantDomain: 'b.example.com',
            direction: SmsMessageRecord::DIRECTION_OUTBOUND,
            from: '+1',
            to: '+3',
            body: 'B',
            status: SmsMessageRecord::STATUS_FAILED,
            failureReason: 'failed',
            createdAt: now()->toDateTimeImmutable(),
        ));

        $this->assertCount(1, $store->forTenant('a.example.com'));
        $this->assertCount(1, $store->forTenant('b.example.com'));
    }
}
