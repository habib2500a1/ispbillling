<?php

namespace App\Logging\Tap;

use App\Logging\StructuredLogContext;
use Illuminate\Log\Logger as IlluminateLogger;
use Monolog\Logger;
use Monolog\Processor\ProcessorInterface;

final class AddStructuredContext
{
    public function __invoke(IlluminateLogger|Logger $logger): void
    {
        if ($logger instanceof IlluminateLogger) {
            $logger = $logger->getLogger();
        }

        $processor = new class implements ProcessorInterface
        {
            public function __invoke(\Monolog\LogRecord $record): \Monolog\LogRecord
            {
                $record->extra = array_merge(StructuredLogContext::defaults(), $record->extra);

                return $record;
            }
        };

        foreach ($logger->getHandlers() as $handler) {
            $handler->pushProcessor($processor);
        }
    }
}
