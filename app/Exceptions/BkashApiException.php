<?php

namespace App\Exceptions;

use RuntimeException;

class BkashApiException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly ?string $errorCode = null,
        public readonly ?array $response = null,
    ) {
        parent::__construct($message);
    }
}
