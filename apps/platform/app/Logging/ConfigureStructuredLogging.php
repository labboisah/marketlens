<?php

namespace App\Logging;

use Monolog\Formatter\JsonFormatter;
use Monolog\Logger;
use Monolog\LogRecord;

class ConfigureStructuredLogging
{
    public function __invoke(Logger $logger): void
    {
        foreach ($logger->getHandlers() as $handler) {
            $handler->setFormatter(new JsonFormatter());
        }

        $logger->pushProcessor(function (LogRecord $record): LogRecord {
            $record->extra['application'] = config('app.name', 'MarketLens Platform');
            $record->extra['environment'] = app()->environment();
            $record->extra['trace_id'] = request()?->attributes->get('trace_id');

            return $record;
        });
    }
}
