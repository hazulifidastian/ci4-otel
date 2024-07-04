<?php

namespace Hazuli\Ci4Otel\Config;

use CodeIgniter\Config\BaseService;
use Hazuli\Ci4Otel\Config\Otel as OtelConfig;
use Hazuli\Ci4Otel\Otel;
use Hazuli\Ci4Otel\Support\Metric;
use Hazuli\Ci4Otel\Support\Trace;
use Hazuli\Ci4Otel\Support\Span;
use Hazuli\Ci4Otel\Support\OtelLogger;
use Hazuli\Ci4Otel\Support\Storage;

class Services extends BaseService
{
    public static function otel(?OtelConfig $config = null, bool $getShared = true): Otel
    {
        if ($getShared) {
            return static::getSharedInstance('otel', $config);
        }

        return new Otel($config ?? config('Otel'));
    }

    public static function Trace(?OtelConfig $config = null, bool $getShared = true): Trace
    {
        if ($getShared) {
            return static::getSharedInstance('trace', $config);
        }

        return new Trace($config ?? config('Otel'));
    }

    public static function Span(?OtelConfig $config = null, bool $getShared = true): Span
    {
        if ($getShared) {
            return static::getSharedInstance('span', $config);
        }

        return new Span($config ?? config('Otel'));
    }

    public static function Metric(?OtelConfig $config = null, bool $getShared = true): Metric
    {
        if ($getShared) {
            return static::getSharedInstance('metric', $config);
        }

        return new Metric($config ?? config('Otel'));
    }

    public static function OtelLogger(?OtelConfig $config = null, bool $getShared = true)
    {
        if ($getShared) {
            return static::getSharedInstance('otelLogger', $config);
        }

        return new OtelLogger($config ?? config('Otel'));
    }

    public static function Storage(?OtelConfig $config = null, bool $getShared = true): Storage
    {
        if ($getShared) {
            return static::getSharedInstance('storage', $config);
        }

        return new Storage($config ?? config('Otel'));
    }
}
