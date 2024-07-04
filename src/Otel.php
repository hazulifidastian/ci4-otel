<?php

namespace Hazuli\Ci4Otel;

use CodeIgniter\Events\Events;
use Hazuli\Ci4Otel\Config\Otel as OtelConfig;
use Hazuli\Ci4Otel\Listeners\QueryExecutedListener;
use Hazuli\Ci4Otel\Listeners\RedisCacheListener;
use Hazuli\Ci4Otel\Support\CarbonClock;
use OpenTelemetry\API\Logs\LoggerProviderInterface;
use OpenTelemetry\API\Trace\Propagation\TraceContextPropagator;
use OpenTelemetry\API\Trace\TracerProviderInterface;
use OpenTelemetry\Contrib\Otlp\LogsExporter;
use OpenTelemetry\Contrib\Otlp\MetricExporter;
use OpenTelemetry\Contrib\Otlp\OtlpHttpTransportFactory;
use OpenTelemetry\Contrib\Otlp\SpanExporter;
use OpenTelemetry\SDK\Common\Attribute\Attributes;
use OpenTelemetry\SDK\Common\Instrumentation\InstrumentationScopeFactory;
use OpenTelemetry\SDK\Common\Time\ClockFactory;
use OpenTelemetry\SDK\Logs\LoggerProvider;
use OpenTelemetry\SDK\Logs\LogRecordExporterInterface;
use OpenTelemetry\SDK\Logs\Processor\BatchLogRecordProcessor;
use OpenTelemetry\SDK\Metrics\Exemplar\ExemplarFilter\WithSampledTraceExemplarFilter;
use OpenTelemetry\SDK\Metrics\MeterProvider;
use OpenTelemetry\SDK\Metrics\MeterProviderInterface;
use OpenTelemetry\SDK\Metrics\MetricReader\ExportingReader;
use OpenTelemetry\SDK\Metrics\StalenessHandler\ImmediateStalenessHandlerFactory;
use OpenTelemetry\SDK\Metrics\View\CriteriaViewRegistry;
use OpenTelemetry\SDK\Resource\ResourceInfo;
use OpenTelemetry\SDK\Resource\ResourceInfoFactory;
use OpenTelemetry\SDK\Sdk;
use OpenTelemetry\SDK\Trace\Sampler\AlwaysOffSampler;
use OpenTelemetry\SDK\Trace\Sampler\AlwaysOnSampler;
use OpenTelemetry\SDK\Trace\Sampler\ParentBased;
use OpenTelemetry\SDK\Trace\Sampler\TraceIdRatioBasedSampler;
use OpenTelemetry\SDK\Trace\SpanProcessor\BatchSpanProcessorBuilder;
use OpenTelemetry\SDK\Trace\TracerProvider;
use OpenTelemetry\SemConv\ResourceAttributes;

class Otel
{
    private $config;

    private TracerProviderInterface $tracerProvider;

    private LogRecordExporterInterface $logExporter;

    private MeterProviderInterface $meterProvider;

    private LoggerProviderInterface $loggerProvider;

    private ExportingReader $metricsReader;

    public function __construct(OtelConfig $config)
    {
        $this->config = $config;

        $this->buildSDK();

        $this->registerListeners();

        $this->registerPostSystem();
    }

    public function buildSDK()
    {
        ClockFactory::setDefault(new CarbonClock());

        $resource = $this->buildResource();
        $this->tracerProvider = $this->buildTracerProvider($resource);
        $propagator = TraceContextPropagator::getInstance();
        $this->meterProvider = $this->buildMeterProvider();
        $this->loggerProvider = $this->buildLoggerProvider($resource);

        Sdk::builder()
            ->setPropagator($propagator)
            ->setTracerProvider($this->tracerProvider)
            ->setMeterProvider($this->meterProvider)
            ->setLoggerProvider($this->loggerProvider)
            ->setAutoShutdown(true)
            ->buildAndRegisterGlobal();
    }

    private function buildResource()
    {
        return ResourceInfoFactory::defaultResource()->merge(ResourceInfo::create(Attributes::create([
            ResourceAttributes::SERVICE_NAME => $this->config->serviceName,
            ResourceAttributes::DEPLOYMENT_ENVIRONMENT => $this->config->deploymentEnvironment,
        ])));
    }

    private function buildTracerProvider(ResourceInfo $resource): TracerProviderInterface
    {
        if ($this->config->traces['exporter'] === 'otlp') {
            $transport = (new OtlpHttpTransportFactory())->create($this->config->traces['endpoint'], 'application/json');
        }

        $spanExporter = new SpanExporter($transport);
        $spanProcessor = (new BatchSpanProcessorBuilder($spanExporter))->build();

        $tracerProvider = TracerProvider::builder()
            ->setResource($resource)
            ->addSpanProcessor($spanProcessor)
            ->setSampler($this->buildSampler())
            ->build();

        return $tracerProvider;
    }

        private function buildMeterProvider(): MeterProviderInterface
        {
            $resource = ResourceInfoFactory::emptyResource()->merge(ResourceInfo::create(Attributes::create([
                ResourceAttributes::SERVICE_NAME => $this->config->serviceName,
            ])));

            $this->metricsReader = new ExportingReader(
                new MetricExporter(
                    (new OtlpHttpTransportFactory())->create($this->config->metrics['endpoint'], 'application/json')
                )
            );

            $meterProvider = new MeterProvider(
                null,
                $resource,
                ClockFactory::getDefault(),
                Attributes::factory(),
                new InstrumentationScopeFactory(Attributes::factory()),
                [$this->metricsReader],
                new CriteriaViewRegistry(),
                new WithSampledTraceExemplarFilter(),
                new ImmediateStalenessHandlerFactory(),
            );

            return $meterProvider;
        }

    private function buildLoggerProvider(ResourceInfo $resource): LoggerProviderInterface
    {
        $this->logExporter = new LogsExporter(
            (new OtlpHttpTransportFactory())->create(
                $this->config->logs['endpoint'],
                'application/json',
                [],
                null,
                0.3,
                5,
                1
            )
        );

        $logProcessor = new BatchLogRecordProcessor($this->logExporter, ClockFactory::getDefault());

        $loggerProvider = LoggerProvider::builder()
            ->setResource($resource)
            ->addLogRecordProcessor($logProcessor)
            ->build();

        return $loggerProvider;
    }

    private function buildSampler()
    {
        switch ($this->config->traces['sampler']['type']) {
            case 'always_off':
                $sampler = new AlwaysOffSampler();
                break;
            case 'always_on':
                $sampler = new AlwaysOnSampler();
                break;
            case 'traceidratio':
                $sampler = new TraceIdRatioBasedSampler($this->config->traces['sampler']['args']['ratio'] ?? 0.05);
                break;
        }

        if ($this->config->traces['sampler']['parent']) {
            $sampler = new ParentBased($sampler);
        }

        return $sampler;
    }

    public function getLogExporter(): LogRecordExporterInterface
    {
        return $this->logExporter;
    }

    public function getLoggerProvider(): LoggerProviderInterface
    {
        return $this->loggerProvider;
    }

    public function getMetricsReader(): ExportingReader
    {
        return $this->metricsReader;
    }

    private function registerListeners()
    {
        if ($this->config->traces['recordDbQuery']) {
            $queryExecutedListener = new QueryExecutedListener();
            $queryExecutedListener->register();
        }
        if ($this->config->traces['recordRedisCache']) {
            $redisCacheListener = new RedisCacheListener();
            $redisCacheListener->register();
        }
    }

    private function registerPostSystem()
    {
        $self = $this;
        Events::on('post_system', static function () use ($self) {
            if ($self->config->traces['forceFlush']) {
                $self->tracerProvider->forceFlush();
            }
            if ($self->config->metrics['forceFlush']) {
                $self->meterProvider->forceFlush();
            }
            if ($self->config->logs['forceFlush']) {
                $self->loggerProvider->forceFlush();
            }
        });
    }
}
