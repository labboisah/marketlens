<?php

namespace App\Logging;

use Illuminate\Log\Logger as IlluminateLogger;
use Monolog\Formatter\JsonFormatter;
use Monolog\Handler\FormattableHandlerInterface;
use Monolog\Logger as MonologLogger;
use Monolog\LogRecord;

class ConfigureStructuredLogging
{
    public function __invoke(IlluminateLogger|MonologLogger $logger): void
    {
        $monolog = $logger instanceof IlluminateLogger ? $logger->getLogger() : $logger;

        if (! $monolog instanceof MonologLogger) {
            return;
        }

        foreach ($monolog->getHandlers() as $handler) {
            if (! $handler instanceof FormattableHandlerInterface) {
                continue;
            }

            $handler->setFormatter(new JsonFormatter);
        }

        $monolog->pushProcessor(function (LogRecord $record): LogRecord {
            $record->extra['application'] = config('app.name', 'MarketLens Platform');
            $record->extra['environment'] = app()->environment();
            $record->extra['trace_id'] = request()->attributes->get('trace_id');

            return $record;
        });
    }
}
