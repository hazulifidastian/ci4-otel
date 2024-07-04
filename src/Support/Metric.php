<?php

namespace Hazuli\Ci4Otel\Support;

use Exception;
use Hazuli\Ci4Otel\Config\Otel as OtelConfig;
use OpenTelemetry\API\Globals;
use OpenTelemetry\API\Metrics\MeterInterface;

class Metric
{
    public function __construct(OtelConfig $config)
    {
        $this->config = $config;
    }

    public function getMeter(string $name = 'io.opentelemetry.contrib.php'): MeterInterface
    {
        return Globals::meterProvider()->getMeter($name);
    }

    public function collect(): bool
    {
        return service('otel')->getMetricsReader()->collect();
    }

    public function getDefaultMeter(string $name)
    {
        switch($name) {
            case 'http.server.request.total':
                return $this->getMeter()
                    ->createCounter('http.server.request.total', 'request', 'jumlah request');
            case 'http.server.request.latency.bucket':
                return $this->getMeter()
                    ->createCounter('http.server.request.latency.bucket', 'ms', 'latency request bucket');
            default:
                throw new Exception("Meter {$name} tidak tersedia.");
        }
    }
}
