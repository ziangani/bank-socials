<?php

namespace App\Logging;

use Monolog\Formatter\LineFormatter;
use Monolog\LogRecord;

class CustomLogFormatter
{
    public function __invoke($logger)
    {
        // Get the first handler (usually StreamHandler)
        $handler = $logger->getHandlers()[0];
        
        // Create a new formatter with null as the dateFormat to prevent timestamp modification
        $formatter = new LineFormatter(
            null, // Use default format
            null, // Use default date format
            true, // allowInlineLineBreaks
            true, // ignoreEmptyContextAndExtra
            true  // includeStacktraces
        );
        
        // Set maximum normalization depth
        $formatter->setMaxNormalizeDepth(5); // Reduce nesting depth
        
        // Set the formatter
        $handler->setFormatter($formatter);
    }
}
