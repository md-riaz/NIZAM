<?php

namespace Nizam\Sdk\Exceptions;

class ValidationException extends NizamApiException
{
    protected array $errors;

    public function __construct(string $message, array $errors, int $statusCode = 422, ?\Throwable $previous = null)
    {
        $this->errors = $errors;
        parent::__construct($message, $statusCode, $previous);
    }

    public function getErrors(): array
    {
        return $this->errors;
    }
}
