<?php

namespace App\Console\Commands;

use App\Models\Extension;
use App\Models\Organization;
use App\Services\WebhookDispatcher;
use Illuminate\Console\Command;

class ListenFreeswitchEvents extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'nizam:webhook-esl-listener';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Listen to FreeSWITCH events via ESL and dispatch webhooks';

    /**
     * The events we subscribe to and their webhook event type mappings.
     */
    protected const EVENT_MAP = [
        'CHANNEL_CREATE'          => 'call.created',
        'CHANNEL_ANSWER'          => 'call.answered',
        'CHANNEL_HANGUP'          => 'call.ended',
        'CHANNEL_HANGUP_COMPLETE' => 'call.completed',
    ];

    public function __construct(
        protected WebhookDispatcher $webhookDispatcher,
    ) {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $host     = config('telephony.freeswitch.host', 'freeswitch');
        $port     = (int) config('telephony.freeswitch.esl_port', 8021);
        $password = config('telephony.freeswitch.esl_password', 'ClueCon');

        $this->info("Connecting to FreeSWITCH ESL at {$host}:{$port}...");

        $socket = @stream_socket_client("tcp://{$host}:{$port}", $errno, $errstr, 10);

        if (! $socket) {
            $this->error("Failed to connect to FreeSWITCH ESL: {$errstr} ({$errno})");
            return 1;
        }

        $this->info('Connected to FreeSWITCH ESL.');

        // Read the auth/request header
        $this->readResponse($socket);

        // Authenticate
        fwrite($socket, "auth {$password}\n\n");
        $authResponse = $this->readResponse($socket);

        if (str_contains($authResponse, '-ERR')) {
            $this->error('ESL authentication failed.');
            fclose($socket);
            return 1;
        }

        $this->info('Authenticated successfully.');

        // Subscribe to events
        $eventNames = implode(' ', array_keys(self::EVENT_MAP));
        fwrite($socket, "event plain {$eventNames}\n\n");
        $this->readResponse($socket);
        $this->info("Subscribed to events: {$eventNames}");

        // Main event loop
        while (! feof($socket)) {
            $headers = $this->readEventHeaders($socket);

            if (empty($headers)) {
                usleep(10000); // 10ms
                continue;
            }

            $eventName = $headers['Event-Name'] ?? null;

            if (! $eventName || ! isset(self::EVENT_MAP[$eventName])) {
                continue;
            }

            // Read the event body if Content-Length is present
            $body = '';
            if (isset($headers['Content-Length'])) {
                $body = $this->readBody($socket, (int) $headers['Content-Length']);
                $bodyHeaders = $this->parseHeaders($body);
                $headers = array_merge($headers, $bodyHeaders);
            }

            $this->processEvent($eventName, $headers);
        }

        fclose($socket);
        $this->warn('ESL connection closed.');

        return 0;
    }

    /**
     * Process a FreeSWITCH event by resolving the organization and dispatching webhooks.
     */
    protected function processEvent(string $eventName, array $headers): void
    {
        $uniqueId = $headers['Unique-ID'] ?? 'unknown';
        $webhookEvent = self::EVENT_MAP[$eventName];

        $this->info("Event: {$eventName} → {$webhookEvent} (Call: {$uniqueId})");

        // Resolve organization from the FreeSWITCH domain/context
        $organization = $this->resolveOrganization($headers);

        if (! $organization) {
            $this->warn("  → Could not resolve organization for call {$uniqueId}, skipping webhook.");
            return;
        }

        // Build the webhook payload
        $payload = [
            'call_uuid'          => $uniqueId,
            'caller_id_number'   => urldecode($headers['Caller-Caller-ID-Number'] ?? ''),
            'caller_id_name'     => urldecode($headers['Caller-Caller-ID-Name'] ?? ''),
            'destination_number' => urldecode($headers['Caller-Destination-Number'] ?? ''),
            'direction'          => $headers['Call-Direction'] ?? '',
            'channel_state'      => $headers['Channel-State'] ?? '',
            'hangup_cause'       => $headers['Hangup-Cause'] ?? null,
            'duration'           => $headers['variable_billsec'] ?? null,
            'organization_domain'      => $organization->domain,
        ];

        // Dispatch via the existing WebhookDispatcher service
        $this->webhookDispatcher->dispatch($organization->id, $webhookEvent, $payload);

        $this->info("  → Dispatched '{$webhookEvent}' webhook for organization '{$organization->name}'");
    }

    /**
     * Resolve the organization from FreeSWITCH event headers.
     *
     * Strategy:
     * 1. Try the variable_domain_name (set by FreeSWITCH context)
     * 2. Try the Caller-Context (the FreeSWITCH context name, which matches organization domain)
     * 3. Try to find the extension in the database and get its organization
     */
    protected function resolveOrganization(array $headers): ?Organization
    {
        // Strategy 1: Domain name variable
        $domain = $headers['variable_domain_name'] ?? null;
        if ($domain) {
            $organization = Organization::where('domain', $domain)->where('is_active', true)->first();
            if ($organization) {
                return $organization;
            }
        }

        // Strategy 2: Caller context (FreeSWITCH context = organization domain)
        $context = $headers['Caller-Context'] ?? null;
        if ($context && $context !== 'default' && $context !== 'public') {
            $organization = Organization::where('domain', $context)->where('is_active', true)->first();
            if ($organization) {
                return $organization;
            }
        }

        // Strategy 3: Look up the extension
        $extensionNumber = urldecode($headers['Caller-Caller-ID-Number'] ?? '');
        if ($extensionNumber) {
            $extension = Extension::where('extension', $extensionNumber)
                ->where('is_active', true)
                ->first();

            if ($extension) {
                return $extension->organization;
            }
        }

        return null;
    }

    /**
     * Read a full response from the ESL socket (headers + optional body).
     */
    protected function readResponse(mixed $socket): string
    {
        $response = '';
        while (($line = fgets($socket)) !== false) {
            $response .= $line;
            if (trim($line) === '') {
                break;
            }
        }

        return $response;
    }

    /**
     * Read event headers from the ESL socket until a blank line.
     *
     * @return array<string, string>
     */
    protected function readEventHeaders(mixed $socket): array
    {
        $headerBlock = '';

        while (($line = fgets($socket, 4096)) !== false) {
            if (trim($line) === '') {
                break;
            }
            $headerBlock .= $line;
        }

        return $this->parseHeaders($headerBlock);
    }

    /**
     * Parse a block of "Key: Value" text into an associative array.
     *
     * @return array<string, string>
     */
    protected function parseHeaders(string $block): array
    {
        $headers = [];
        foreach (explode("\n", $block) as $line) {
            $line = trim($line);
            if ($line === '' || ! str_contains($line, ': ')) {
                continue;
            }
            [$key, $value] = explode(': ', $line, 2);
            $headers[$key] = $value;
        }

        return $headers;
    }

    /**
     * Read a specific number of bytes from the socket.
     */
    protected function readBody(mixed $socket, int $length): string
    {
        $body = '';
        $remaining = $length;

        while ($remaining > 0) {
            $chunk = fread($socket, $remaining);
            if ($chunk === false) {
                break;
            }
            $body .= $chunk;
            $remaining -= strlen($chunk);
        }

        return $body;
    }
}

