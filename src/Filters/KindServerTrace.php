<?php

namespace Hazuli\Ci4Otel\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Hazuli\Ci4Otel\Support\FilterRoute;
use Hazuli\Ci4Otel\Support\SpanBuilder;
use Throwable;

class KindServerTrace implements FilterInterface
{
    public function __construct()
    {
    }

    public function before(RequestInterface $request, $arguments = null)
    {
        $config = new \Hazuli\Ci4Otel\Config\Otel();
        $routes = explode(',', $config->traces['middleware']['kindServerTraceRoutes']);

        $filterRoute = new FilterRoute($routes, $request->getPath());

        if ($filterRoute->isEmpty()) {
            return;
        }

        if (!$filterRoute->isAll() && !$filterRoute->isMatch()) {
            return;
        }

        service('span')->start((new SpanBuilder())->server($request));

        $request->setHeader('spanStarted', 1);
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        try {
            if ((int)$request->getHeader('spanStarted')->getValue() !== 1) {
                return;
            }
        } catch(Throwable $e) {
            return;
        }

        service('span')->response($response);

        if ($response->getStatusCode() === 200) {
            service('span')->ok();
        } else {
            service('span')->error($response->getReason());
        }

        service('span')->stop()->detach();
    }
}
