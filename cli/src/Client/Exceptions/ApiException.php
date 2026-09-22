<?php

namespace FileServerCli\Client\Exceptions;

class ApiException extends \RuntimeException
{
    public function __construct(
        string $message,
        public readonly int $statusCode,
        public readonly ?string $errorCode = null,
        public readonly array $details = [],
    ) {
        parent::__construct($message);
    }

    public static function fromResponseBody(int $statusCode, array $body): self
    {
        $error = $body['error'] ?? [];

        return new self(
            $error['message'] ?? "API request failed with status {$statusCode}.",
            $statusCode,
            $error['code'] ?? null,
            $error['details'] ?? [],
        );
    }
}
