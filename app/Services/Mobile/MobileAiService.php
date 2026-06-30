<?php

namespace App\Services\Mobile;

use App\Models\Customer;
use App\Services\Ai\AiCustomerAssistantService;

final class MobileAiService
{
    public function __construct(
        private readonly AiCustomerAssistantService $assistant,
    ) {}

    /**
     * @return array{reply: string, hints: list<string>, locale?: string, advisory?: bool}
     */
    public function reply(Customer $customer, string $question): array
    {
        return $this->assistant->reply($customer, $question);
    }
}
