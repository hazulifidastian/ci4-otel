<?php

namespace Hazuli\Ci4Otel\Support;

use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\Context\ContextInterface;
use OpenTelemetry\SemConv\TraceAttributes;
use CodeIgniter\HTTP\IncomingRequest;

class SpanBuilder
{
    public string $name;
    public ContextInterface $parentContext;
    public int $spanKind;
    public array $attributes;

    public function __construct()
    {
        $this->attributes = [];
    }

    public function headersToArray(array $headers)
    {
        $array = [];
        foreach ($headers as $header) {
            $array[$header->getName()] = $header->getValue();
        }

        return $array;
    }

    public function server(IncomingRequest $request): self
    {
        $headers = $this->headersToArray($request->headers());

        $this->name = strtoupper($request->getMethod()) . ' ' . ($request->getPath() === '' ? '/' : $request->getPath());
        $this->spanKind = SpanKind::KIND_SERVER;
        $this->parentContext = service('trace')->getParentContext($headers);
        $this->attributes = [
            TraceAttributes::HTTP_REQUEST_METHOD => $request->getMethod(),
            TraceAttributes::URL_PATH => $request->getPath(),
            TraceAttributes::URL_SCHEME => preg_match('/^https:\/\//', $request->getServer()['app.baseURL']) ? 'https' : 'http',
            TraceAttributes::HTTP_ROUTE => $request->getUri()->getRoutePath(),
            TraceAttributes::URL_FULL => $request->getServer()['app.baseURL'] . $request->getServer()['REQUEST_URI'],
            TraceAttributes::URL_QUERY => $request->getServer()['QUERY_STRING'],
            TraceAttributes::CLIENT_ADDRESS => $request->getIPAddress(),
            TraceAttributes::SERVER_ADDRESS => $request->getServer()['SERVER_ADDR'],

            TraceAttributes::NETWORK_PROTOCOL_VERSION => $request->getProtocolVersion(),
            TraceAttributes::ENDUSER_ID => auth()->user()->email ?? null,
        ];

        $this->headers($headers);

        return $this;
    }

    public function client($request): self
    {
        $this->name = $request->getMethod() . ' ' . $request->getUri()->getHost() . $request->getUri()->getPath();
        $this->spanKind = SpanKind::KIND_CLIENT;
        $this->parentContext = service('trace')->getParentContext($request->getHeaders());
        $this->attributes = [
            TraceAttributes::HTTP_REQUEST_METHOD => $request->getMethod(),
            TraceAttributes::SERVER_ADDRESS => $request->getUri()->getHost(),
            TraceAttributes::SERVER_PORT => $request->getUri()->getPort(),
            TraceAttributes::URL_PATH => $request->getUri()->getPath(),
            TraceAttributes::URL_QUERY => $request->getUri()->getQuery(),
            TraceAttributes::NETWORK_PROTOCOL_VERSION => $request->getProtocolVersion(),
            TraceAttributes::URL_SCHEME => $request->getUri()->getScheme(),
        ];

        $this->headers($request->getHeaders());

        return $this;
    }

    public function internal(string $name): self
    {
        $this->name = $name;
        $this->spanKind = SpanKind::KIND_INTERNAL;
        $this->parentContext = service('trace')->getCurrentContext();

        return $this;
    }

    public function command(string $name, ?array $options = null): self
    {
        $this->name = 'COMMAND ' . $name;
        $this->spanKind = SpanKind::KIND_INTERNAL;
        $this->parentContext = service('trace')->getCurrentContext();

        if ($options) {
            $this->attributes = [
                'command.options' => json_encode($options),
            ];
        }

        return $this;
    }

    public function database(string $name=''): self
    {
        $prefix = 'DB';
        $this->name = $prefix . ' ' . $name;
        $this->spanKind = SpanKind::KIND_INTERNAL;
        $this->parentContext = service('trace')->getCurrentContext();

        return $this;
    }

    public function headers(array $headers)
    {
        $requestHeaderPrefix = 'http.request.header.';

        foreach ($headers as $key=>$value) {
            $this->attributes[$requestHeaderPrefix . $key] = $value;
        }
    }

    public function codeinfo(string $file, string $lineNo, string $function): self
    {
        $this->attributes = array_merge($this->attributes, [
            TraceAttributes::CODE_FILEPATH => $file,
            TraceAttributes::CODE_LINENO => $lineNo,
            TraceAttributes::CODE_FUNCTION => $function,
        ]);


        return $this;
    }
}
