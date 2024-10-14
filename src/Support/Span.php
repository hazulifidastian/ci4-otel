<?php

namespace Hazuli\Ci4Otel\Support;

use Hazuli\Ci4Otel\Config\Otel as OtelConfig;
use OpenTelemetry\API\Trace\SpanInterface;
use OpenTelemetry\API\Trace\StatusCode;
use OpenTelemetry\API\Trace\TracerInterface;
use OpenTelemetry\Context\Context;
use OpenTelemetry\SemConv\TraceAttributes;
use ReflectionClass;
use Throwable;

class Span
{
    private TracerInterface $tracer;
    private array $scopes;

    public function __construct(OtelConfig $config)
    {
        $this->tracer = service('trace')->getTracer();
        $this->scopes = array();
    }

    public function start(SpanBuilder $spanBuilder, ?int $start=null): self
    {
        $builder = $this->tracer->spanBuilder($spanBuilder->name)
            ->setParent($spanBuilder->parentContext ?? null)
            ->setSpanKind($spanBuilder->spanKind);

        if ($start) {
            $builder->setStartTimestamp($start);
        }

        $span = $builder->startSpan();

        if (!empty($spanBuilder->attributes)) {
            $span->setAttributes($spanBuilder->attributes);
        }

        $this->scopes[] = $span->activate();

        return $this;
    }

    public function updateAttributes(array $attributes): self
    {
        if (!empty($attributes)) {
            $this->getCurrent()->setAttributes($attributes);
        }

        return $this;
    }

    public function getCurrent(): SpanInterface
    {
        return \OpenTelemetry\API\Trace\Span::getCurrent();
    }

    public function stop(?int $stop = null): self
    {
        if ($stop) {
            $this->getCurrent()->end($stop);
        } else {
            $this->getCurrent()->end();
        }

        return $this;
    }

    public function detach(): self
    {
        try {
            (array_pop($this->scopes))->detach();
        } catch (Throwable $e) {
            //
        }

        return $this;
    }

    public function addEvent(string $name, ?iterable $attributes=[]): self
    {
        $this->getCurrent()->addEvent($name, $attributes);

        return $this;
    }

    public function errorType($value): self
    {
        $this->setAttribute(TraceAttributes::ERROR_TYPE, $value);

        return $this;
    }

    /**
     * Record response
     *
     * @param \GuzzleHttp\Psr7\Response|CodeIgniter\HTTP\Response $response
     * @return self
     */
    public function response($response): self
    {
        $span = $this->getCurrent();

        $responseHeaderPrefix = 'http.response.header.';

        $span->setAttributes([
            TraceAttributes::HTTP_RESPONSE_STATUS_CODE => $response->getStatusCode(),
        ]);

        // TODO refactor this block
        if ($response instanceof \GuzzleHttp\Psr7\Response) {
            $headers = $response->getHeaders();
        } elseif ($response instanceof \CodeIgniter\HTTP\Response) {
            $spanBuilder = new SpanBuilder();
            $headers = $spanBuilder->headersToArray($response->getHeaders());
        }

        foreach ($headers as $key=>$value) {
            $span->setAttribute($responseHeaderPrefix. 'content-type', $value);
        }

        return $this;
    }

    public function setAttribute(string $key, $value): self
    {
        $this->getCurrent()->setAttribute($key, $value);

        return $this;
    }

    public function setAttributes(iterable $attributes): self
    {
        $this->getCurrent()->setAttributes($attributes);

        return $this;
    }

    public function exception(Throwable $e): self
    {
        $this->getCurrent()->recordException($e);

        return $this;
    }

    public function error(string $description=null): self
    {
        $this->getCurrent()->setStatus(StatusCode::STATUS_ERROR, $description);

        return $this;
    }

    public function ok(string $description=null): self
    {
        $this->getCurrent()->setStatus(StatusCode::STATUS_OK, $description);

        return $this;
    }

    public function isRoot(): bool
    {
        $context = clone Context::getCurrent();

        $reflectionClass = new ReflectionClass(Context::getCurrent());
        $spanProperty = $reflectionClass->getProperty('span');
        $spanProperty->setAccessible(true); // Make the private property accessible

        $spanValue = $spanProperty->getValue($context);

        if ($spanValue === null) {
            return true;
        }

        return false;
    }
}
