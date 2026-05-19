<?php

namespace App\Exceptions;

use Exception;

class PaymentGatewayException extends Exception
{
    /**
     * @param  array<string, mixed>|null  $response
     */
    public function __construct(
        string $message,
        public readonly ?array $response = null,
    ) {
        parent::__construct($message);
    }
}
