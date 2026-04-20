<?php

namespace App\Services\Messaging;

use App\Models\SmsMessage;

class DatabaseSmsMessageStore implements SmsMessageStoreInterface
{
    public function store(SmsMessageRecord $message): SmsMessageRecord
    {
        SmsMessage::query()->updateOrCreate(
            ['id' => $message->id],
            [
                'organization_domain' => $message->organizationDomain,
                'direction' => $message->direction,
                'from_number' => $message->from,
                'to_number' => $message->to,
                'body' => $message->body,
                'status' => $message->status,
                'provider' => $message->provider,
                'provider_message_id' => $message->providerMessageId,
                'failure_reason' => $message->failureReason,
                'metadata' => $message->metadata,
                'created_at' => $message->createdAt,
                'updated_at' => $message->createdAt,
            ],
        );

        return $message;
    }

    public function forOrganization(string $organizationDomain): array
    {
        return SmsMessage::query()
            ->where('organization_domain', $organizationDomain)
            ->orderBy('created_at')
            ->get()
            ->map(fn (SmsMessage $message): SmsMessageRecord => new SmsMessageRecord(
                id: $message->id,
                organizationDomain: $message->organization_domain,
                direction: $message->direction,
                from: $message->from_number,
                to: $message->to_number,
                body: $message->body,
                status: $message->status,
                provider: $message->provider,
                providerMessageId: $message->provider_message_id,
                failureReason: $message->failure_reason,
                metadata: $message->metadata ?? [],
                createdAt: $message->created_at?->toDateTimeImmutable(),
            ))
            ->all();
    }
}
