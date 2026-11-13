<?php

namespace App\Logging;

use App\Helpers\Development;
use Monolog\Formatter\JsonFormatter;
use Monolog\LogRecord;

class JsonFormatterTap
{
    public function __invoke($logger): void
    {
        foreach ($logger->getHandlers() as $handler) {

            $handler->setFormatter(new JsonFormatter(
                JsonFormatter::BATCH_MODE_JSON,
                true
            ));

            $handler->pushProcessor(function (LogRecord $record): LogRecord {
                return $record->with(extra: array_merge($record->extra, [
                    'project' => config('app.name'),
                    'env'     => Development::getEnv(),
                    'host'    => gethostname(),
                ]));
            });
        }
    }
}
