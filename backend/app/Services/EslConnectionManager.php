<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class EslConnectionManager
{
    protected $socket = null;

    protected bool $authenticated = false;

    protected int $maxRetries = 3;

    protected int $retryDelayMs = 500;

    /**
     * Set once reconnect() has exhausted its retries.
     *
     * Without it, every command that calls ensureConnected() pays the full
     * retry budget again. A single request touching several settings could
     * spend minutes waiting on a switch that is plainly not answering.
     */
    protected bool $unreachable = false;

    public function __construct(
        protected string $host,
        protected int $port,
        protected string $password,
        protected int $timeoutSeconds = 10
    ) {}

    /**
     * Create an instance from config.
     */
    public static function fromConfig(): static
    {
        return new static(
            config('telephony.freeswitch.host'),
            config('telephony.freeswitch.esl_port'),
            config('telephony.freeswitch.esl_password'),
            (int) config('telephony.freeswitch.esl_timeout', 10)
        );
    }

    /**
     * Connect to FreeSWITCH ESL.
     */
    public function connect(): bool
    {
        $socket = @stream_socket_client(
            "tcp://{$this->host}:{$this->port}",
            $errno,
            $errstr,
            timeout: $this->timeoutSeconds
        );

        if (! $socket) {
            Log::error("ESL connection failed: [{$errno}] {$errstr}");

            return false;
        }

        $this->socket = $socket;

        // Bound reads as well as the handshake. A socket that accepts the
        // connection and then says nothing would otherwise block forever.
        stream_set_timeout($this->socket, $this->timeoutSeconds);

        // Read the initial Content-Type header
        $response = $this->readResponse();

        if (str_contains($response, 'auth/request') && $this->authenticate()) {
            $this->unreachable = false;

            return true;
        }

        // The socket is open but unusable; leaving it dangling leaks a file
        // descriptor for the life of the process.
        $this->disconnect();

        return false;
    }

    /**
     * Reconnect to FreeSWITCH ESL with retry logic.
     *
     * Attempts up to maxRetries with exponential back-off.
     */
    public function reconnect(): bool
    {
        $this->disconnect();

        for ($attempt = 1; $attempt <= $this->maxRetries; $attempt++) {
            Log::info("ESL reconnect attempt {$attempt}/{$this->maxRetries}");

            if ($this->connect()) {
                Log::info('ESL reconnected successfully');

                return true;
            }

            if ($attempt < $this->maxRetries) {
                usleep($this->retryDelayMs * 1000 * $attempt);
            }
        }

        Log::error('ESL reconnect failed after '.$this->maxRetries.' attempts');
        $this->unreachable = true;

        return false;
    }

    /**
     * Ensure the ESL connection is active, reconnecting if necessary.
     */
    public function ensureConnected(): bool
    {
        if ($this->isConnected()) {
            return true;
        }

        if ($this->unreachable) {
            return false;
        }

        return $this->reconnect();
    }

    /**
     * Authenticate with FreeSWITCH.
     */
    protected function authenticate(): bool
    {
        $this->sendCommand("auth {$this->password}");
        $response = $this->readResponse();

        if (str_contains($response, 'Reply-Text: +OK accepted')) {
            $this->authenticated = true;
            Log::info('ESL authenticated successfully');

            return true;
        }

        Log::error('ESL authentication failed');

        return false;
    }

    /**
     * Subscribe to events.
     */
    public function subscribeEvents(array $events): bool
    {
        if (! $this->authenticated) {
            return false;
        }

        $eventList = implode(' ', $events);
        $this->sendCommand("event plain {$eventList}");
        $response = $this->readResponse();

        return str_contains($response, '+OK');
    }

    /**
     * Send an API command to FreeSWITCH.
     */
    public function api(string $command): ?string
    {
        if (! $this->ensureConnected()) {
            return null;
        }

        $this->sendCommand("api {$command}");

        return $this->readResponse();
    }

    /**
     * Send a bgapi command (async).
     */
    public function bgapi(string $command): ?string
    {
        if (! $this->ensureConnected()) {
            return null;
        }

        $this->sendCommand("bgapi {$command}");

        return $this->readResponse();
    }

    /**
     * Read a single event from the socket.
     * Returns parsed event as associative array, or null if no event available.
     */
    public function readEvent(int $timeoutSeconds = 1): ?array
    {
        if (! $this->socket) {
            return null;
        }

        stream_set_timeout($this->socket, $timeoutSeconds);

        $headers = $this->readHeaders();
        if (empty($headers)) {
            return null;
        }

        $contentLength = (int) ($headers['Content-Length'] ?? 0);
        $body = '';

        if ($contentLength > 0) {
            $body = $this->readBytes($contentLength);
        }

        return array_merge($headers, $this->parseEventBody($body));
    }

    /**
     * Send a raw command to ESL.
     */
    protected function sendCommand(string $command): void
    {
        if ($this->socket) {
            fwrite($this->socket, $command."\n\n");
        }
    }

    /**
     * Read a response from the socket.
     */
    protected function readResponse(): string
    {
        if (! $this->socket) {
            return '';
        }

        $response = '';
        while (($line = fgets($this->socket)) !== false) {
            $response .= $line;
            if (trim($line) === '') {
                break;
            }
        }

        // Check for Content-Length and read body
        if (preg_match('/Content-Length:\s*(\d+)/i', $response, $matches)) {
            $response .= $this->readBytes((int) $matches[1]);
        }

        return $response;
    }

    /**
     * Read headers until empty line.
     */
    protected function readHeaders(): array
    {
        $headers = [];
        while (($line = @fgets($this->socket)) !== false) {
            $line = trim($line);
            if ($line === '') {
                break;
            }
            if (str_contains($line, ':')) {
                [$key, $value] = explode(':', $line, 2);
                $headers[trim($key)] = urldecode(trim($value));
            }
        }

        return $headers;
    }

    /**
     * Read exact number of bytes from socket.
     */
    protected function readBytes(int $length): string
    {
        $data = '';
        $remaining = $length;
        while ($remaining > 0 && ! feof($this->socket)) {
            $chunk = fread($this->socket, min($remaining, 8192));

            // A read timeout returns '' with EOF still false, so an empty chunk
            // must end the loop — otherwise this spins forever on a switch that
            // announced a Content-Length and then stopped sending. The body is
            // truncated either way, so the connection is dropped and the next
            // command reconnects rather than resuming mid-message.
            if ($chunk === false || $chunk === '') {
                Log::warning(sprintf(
                    'ESL body read incomplete: %d of %d bytes; dropping the connection.',
                    $length - $remaining,
                    $length
                ));

                $this->disconnect();

                return $data;
            }

            $data .= $chunk;
            $remaining -= strlen($chunk);
        }

        return $data;
    }

    /**
     * Parse an event body into key-value pairs.
     */
    protected function parseEventBody(string $body): array
    {
        $data = [];
        foreach (explode("\n", $body) as $line) {
            $line = trim($line);
            if ($line !== '' && str_contains($line, ':')) {
                [$key, $value] = explode(':', $line, 2);
                $data[trim($key)] = urldecode(trim($value));
            }
        }

        return $data;
    }

    /**
     * Check if connected and authenticated.
     */
    public function isConnected(): bool
    {
        return $this->socket !== null && $this->authenticated;
    }

    /**
     * Disconnect from ESL.
     */
    public function disconnect(): void
    {
        if ($this->socket) {
            fclose($this->socket);
            $this->socket = null;
            $this->authenticated = false;
        }
    }

    public function __destruct()
    {
        $this->disconnect();
    }
}
