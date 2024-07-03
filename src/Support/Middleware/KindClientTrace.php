<?php

namespace Hazuli\Ci4Otel\Support\Middleware;

use Closure;
use GuzzleHttp\Promise\PromiseInterface;
use Hazuli\Ci4Otel\Support\SpanBuilder;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

class KindClientTrace
{
    public function __construct()
    {
    }

    public static function make(): Closure
    {
        return static function (callable $handler): callable {
            return static function (RequestInterface $request, array $options) use ($handler) {
                service('span')->start((new SpanBuilder())->client($request));

                $context = service('trace')->getCurrentContext();
                foreach (service('trace')->propagationHeaders($context) as $key => $value) {
                    $request = $request->withHeader($key, $value);
                }

                service('span')->updateAttributes(['http.request.header.traceparent' => $request->getHeader('traceparent')]);

                $promise = $handler($request, $options);
                assert($promise instanceof PromiseInterface);

                return $promise->then(function (ResponseInterface $response) {
                    service('span')->response($response);

                    if ($response->getStatusCode() === 200) {
                        service('span')->ok();
                    } else {
                        service('span')->error($response->getReasonPhrase());
                    }

                    // TODO record exception

                    service('span')->stop()->detach();

                    return $response;
                });
            };
        };
    }
}
