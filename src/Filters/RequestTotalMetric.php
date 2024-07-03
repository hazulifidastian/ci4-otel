<?php

namespace Hazuli\Ci4Otel\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Hazuli\Ci4Otel\Support\FilterRoute;
use Hazuli\Ci4Otel\Support\SpanBuilder;
use Throwable;

class RequestTotalMetric implements FilterInterface
{
    public function __construct()
    {
    }

    public function before(RequestInterface $request, $arguments = null)
    {
        // Digunakan oleh Support\OpenTelemetryExceptionHandler
        $request->setHeader('requestTotalStartedTimestamp', \Carbon\Carbon::now()->getPreciseTimestamp(3));  // ms precision
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        try {
            $_timestampBefore = (int)$request->getHeader('requestTotalStartedTimestamp')->getValue();
        } catch(Throwable $e) {
            return;
        }

        $config = new \Hazuli\Ci4Otel\Config\Otel();
        $routes = explode(',', $config->traces['middleware']['kindServerTraceRoutes']);

        $filterRoute = new FilterRoute($routes, $request->getPath());

        if ($filterRoute->isEmpty()) {
            return;
        }

        if (!$filterRoute->isAll() && !$filterRoute->isMatch()) {
            return;
        }
        
        $this->collectMetrics($request, $response);
    }

    private function collectMetrics($request, $response)
    {
        $meterName = 'http.server.request.total';

        $serverRequestTotal = service('metric')->getDefaultMeter($meterName);
        $labels = [
            'url.path' => $request->getPath() === '' ? '/' : $request->getPath(),
            'http.method' => $request->getMethod(),
            'http.status_code' => $response->getStatusCode(),
        ];
        
        $serverRequestTotalValue = service('storage')->add($meterName . service('storage')->keyFromArray($labels), 1);
        $serverRequestTotal->add($serverRequestTotalValue, $labels);

        service('metric')->collect();
    }
}
