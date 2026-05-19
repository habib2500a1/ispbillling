<?php

namespace App\Services\Notifications\Channels;

interface NotificationChannelInterface
{
    public function channel(): string;

    public function isEnabled(): bool;

    /**
     * @param  array<string, mixed>  $context
     */
    public function send(string $recipient, string $message, array $context = []): void;
}
