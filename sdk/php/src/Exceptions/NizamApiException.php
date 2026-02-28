<?php

namespace Nizam\Sdk\Exceptions;

class NizamApiException extends \Exception
{
    protected int $statusCode;

    public function __construct(string $message, int $statusCode, ?\Throwable $previous = null)
    {
        $this->statusCode = $statusCode;
        parent::__construct($message, $statusCode, $previous);
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }
}
