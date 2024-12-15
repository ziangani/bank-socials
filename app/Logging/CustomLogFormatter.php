<?php

namespace App\Logging;

use Monolog\Formatter\LineFormatter;

class CustomLogFormatter
{
    public function __invoke($logger)
    {
        foreach ($logger->getHandlers() as $handler) {
            $handler->setFormatter(new LineFormatter(null, null, true, true));
        }
    }
}
