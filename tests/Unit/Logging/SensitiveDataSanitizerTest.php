<?php

namespace Tests\Unit\Logging;

use App\Logging\SensitiveDataSanitizer;
use Monolog\Level;
use Monolog\LogRecord;
use PHPUnit\Framework\TestCase;

class SensitiveDataSanitizerTest extends TestCase
{
    private SensitiveDataSanitizer $sanitizer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sanitizer = new SensitiveDataSanitizer;
    }

    private function makeRecord(string $message, array $context = []): LogRecord
    {
        return new LogRecord(
            datetime: new \DateTimeImmutable,
            channel: 'test',
            level: Level::Info,
            message: $message,
            context: $context,
        );
    }

    public function test_masks_sip_password_in_message(): void
    {
        $record = ($this->sanitizer)($this->makeRecord('SIP password=mysecret123 in request'));

        $this->assertStringNotContainsString('mysecret123', $record->message);
        $this->assertStringContainsString('password=****', $record->message);
    }

    public function test_masks_bearer_token_in_message(): void
    {
        $record = ($this->sanitizer)($this->makeRecord('Auth: Bearer eyJhbGciOiJIUzI1NiJ9.test'));

        $this->assertStringNotContainsString('eyJhbGciOiJIUzI1NiJ9', $record->message);
        $this->assertStringContainsString('Bearer ****', $record->message);
    }

    public function test_masks_api_key_in_message(): void
    {
        $record = ($this->sanitizer)($this->makeRecord('Request with api_key=sk_live_abc123'));

        $this->assertStringNotContainsString('sk_live_abc123', $record->message);
        $this->assertStringContainsString('api_key=****', $record->message);
    }

    public function test_masks_basic_auth_in_message(): void
    {
        $record = ($this->sanitizer)($this->makeRecord('Authorization: Basic dXNlcjpwYXNz'));

        $this->assertStringNotContainsString('dXNlcjpwYXNz', $record->message);
        $this->assertStringContainsString('Basic ****', $record->message);
    }

    public function test_masks_secret_field_in_message(): void
    {
        $record = ($this->sanitizer)($this->makeRecord('"signing_secret": "whsec_abc123def"'));

        $this->assertStringNotContainsString('whsec_abc123def', $record->message);
        $this->assertStringContainsString('****', $record->message);
    }

    public function test_masks_credit_card_in_message(): void
    {
        $record = ($this->sanitizer)($this->makeRecord('Card: 4111111111111111'));

        $this->assertStringNotContainsString('4111111111111111', $record->message);
        $this->assertStringContainsString('4111********1111', $record->message);
    }

    public function test_masks_values_in_context_array(): void
    {
        $record = ($this->sanitizer)($this->makeRecord('Login', [
            'sip' => 'password=secret456',
            'token' => 'Bearer abc.def.ghi',
        ]));

        $this->assertStringNotContainsString('secret456', $record->context['sip']);
        $this->assertStringNotContainsString('abc.def.ghi', $record->context['token']);
    }

    public function test_masks_nested_context(): void
    {
        $record = ($this->sanitizer)($this->makeRecord('Event', [
            'payload' => [
                'auth' => 'password=nested_secret',
            ],
        ]));

        $this->assertStringNotContainsString('nested_secret', $record->context['payload']['auth']);
    }

    public function test_preserves_non_sensitive_data(): void
    {
        $record = ($this->sanitizer)($this->makeRecord('Normal log message', [
            'call_uuid' => 'abc-123',
            'tenant_id' => 'tenant-456',
        ]));

        $this->assertEquals('Normal log message', $record->message);
        $this->assertEquals('abc-123', $record->context['call_uuid']);
        $this->assertEquals('tenant-456', $record->context['tenant_id']);
    }
}
