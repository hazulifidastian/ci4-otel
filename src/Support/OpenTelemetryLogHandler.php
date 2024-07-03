<?php

namespace Hazuli\Ci4Otel\Support;

use CodeIgniter\Log\Handlers\BaseHandler;

class OpenTelemetryLogHandler extends BaseHandler
{
    public function handle($level, $message): bool
    {
        service('otelLogger')->log($level, $message);

        return true;
    }
}