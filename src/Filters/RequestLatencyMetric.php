<?php

namespace Hazuli\Ci4Otel\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Hazuli\Ci4Otel\Support\FilterRoute;
use Throwable;

class RequestLatencyMetric implements FilterInterface
{
    public function __construct()
    {
    }

    public function before(RequestInterface $request, $arguments = null)
    {
        $now = \Carbon\Carbon::now();
        $config = new \Hazuli\Ci4Otel\Config\Otel();
        $routes = explode(',', $config->traces['middleware']['kindServerTraceRoutes']);

        $filterRoute = new FilterRoute($routes, $request->getPath());

        if ($filterRoute->isEmpty()) {
            return;
        }

        if (!$filterRoute->isAll() && !$filterRoute->isMatch()) {
            return;
        }

        $request->setHeader('requestLatencyStartedTimestamp', $now->getPreciseTimestamp(3));  // ms precision
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        $now = \Carbon\Carbon::now();

        try {
            $timestampBefore = (int)$request->getHeader('requestLatencyStartedTimestamp')->getValue();
            $before = \Carbon\Carbon::createFromTimestampMs($timestampBefore);
        } catch(Throwable $e) {
            return;
        }

        $this->collectMetrics($request, $response, $before, $now);
    }

    private function collectMetrics($request, $response, $before, $after)
    {
        $durationMs = $after->diffInMilliseconds($before);

        $meterName = 'http.server.request.latency.bucket';
        $serverRequestLatency = service('metric')->getDefaultMeter($meterName);
        $labels = [
            'url.path' => $request->getPath() === '' ? '/' : $request->getPath(),
            'http.method' => $request->getMethod(),
            'http.status_code' => $response->getStatusCode(),
        ];
        $lestEquals = [
            100,
            300,
            500,
            700,
            1000,  // 1 detik
            2000,
            3000,
            5000,
            7000,
            10000,
            30000,
            60000,  // 60 detik
            '+Inf',
        ];
        foreach ($lestEquals as $le) {
            if ($le === '+Inf') {
                $labels = array_merge($labels, ['le' => '+Inf']);
                break;
            }

            if ($durationMs <= $le) {
                $labels = array_merge($labels, ['le' => $le]);
                break;
            }
        }
        $serverRequestLatencyTotal = service('storage')->add($meterName . service('storage')->keyFromArray($labels), 1);
        $serverRequestLatency->add($serverRequestLatencyTotal, $labels);

        service('metric')->collect();
    }
}
