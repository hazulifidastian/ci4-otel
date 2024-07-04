<?php

namespace Hazuli\Ci4Otel\Config;

use CodeIgniter\Config\BaseConfig;

class Otel extends BaseConfig
{
    public $serviceName;
    public $deploymentEnvironment;
    public $traces = [
        'exporter' => '',
        'endpoint' => '',
        'forceFlush' => false,
        'sampler' => [
            'parent' => true,
            'type' => '',
            'args' => [
                'ratio' => 0.05,
            ],
        ],
        'middleware' => [
            'kindServerTraceRoutes' => '',
        ],
        'recordDbQuery' => false,
        'recordRedisCache' => false,
    ];
    public $metrics = [
        'provider' => '',
        'endpoint' => '',
        'forceFlush' => true,
        'middleware' => [
            'requestTotalMetricRoutes' => '',
            'requestLatencyMetricRoutes' => '',
        ]
    ];
    public $logs = [
        /**
         * emergency
         * alert
         * critical
         * error
         * warning
         * notice
         * info
         * debug
         *
         * Konfigurasi ini tergantung dengan nilai
         * logger.threshold = 7 di file ini
         * jika otel.log.level diset menjadi debug, maka
         * logger.threshold harus diset dengan nilai 9 (paling rendah)
         *
        */
        'level' => 'error',

        'exporter' => '',
        'endpoint' => '',
        'forceFlush' => true,
    ];
}
