<?php

namespace Hazuli\Ci4Otel\Support;

use Hazuli\Ci4Otel\Config\Otel as OtelConfig;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;

class OtelLogger
{
    private LoggerInterface $logger;

    public function __construct(OtelConfig $config)
    {
        $handler = new \OpenTelemetry\Contrib\Logs\Monolog\Handler(
            service('otel')->getLoggerProvider(),
            $config->logs['level'],
        );
        $this->logger = new \Monolog\Logger('ci4-otel', [$handler]);
    }

    public function emergency(string $message, array $context = []): void
    {
        $this->log(LogLevel::EMERGENCY, $message, $context);
    }

    public function alert(string $message, array $context = []): void
    {
        $this->log(LogLevel::ALERT, $message, $context);
    }

    public function critical(string $message, array $context = []): void
    {
        $this->log(LogLevel::CRITICAL, $message, $context);
    }

    public function error(string $message, array $context = []): void
    {
        $this->log(LogLevel::ERROR, $message, $context);
    }

    public function warning(string $message, array $context = []): void
    {
        $this->log(LogLevel::WARNING, $message, $context);
    }

    public function notice(string $message, array $context = []): void
    {
        $this->log(LogLevel::NOTICE, $message, $context);
    }

    public function info(string $message, array $context = []): void
    {
        $this->log(LogLevel::INFO, $message, $context);
    }

    public function debug(string $message, array $context = []): void
    {
        $this->log(LogLevel::DEBUG, $message, $context);
    }

    public function log(string $level, string $message, array $context = []): void
    {
        $this->logger->log($level, $message, $context);
    }
}
