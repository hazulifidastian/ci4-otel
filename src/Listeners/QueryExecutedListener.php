<?php

namespace Hazuli\Ci4Otel\Listeners;

use Hazuli\Ci4Otel\Support\SpanBuilder;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Str;
use OpenTelemetry\SemConv\TraceAttributes;
use OpenTelemetry\SemConv\TraceAttributeValues;
use Illuminate\Database\Capsule\Manager as DB;

class QueryExecutedListener
{
    public function register()
    {
        DB::listen(function (QueryExecuted $query) {
            $this->handle($query);
        });
    }

    public function handle(QueryExecuted $event)
    {
        $end = \Carbon\Carbon::now();
        $start = $end->copy()->sub((float) $event->time, 'milliseconds');

        // Trace only span that has parent
        if (service('span')->isRoot()) {
            return;
        }

        service('span')->start((new SpanBuilder())->database(Str::of($event->sql)->limit(50, ' (...)')), (int) $start->getPreciseTimestamp() * 1_000);
        service('span')->setAttributes([
            TraceAttributes::DB_SYSTEM => TraceAttributeValues::DB_SYSTEM_MYSQL,
            'db.query.text' => $event->sql,
            'db.query.bindings' => json_encode($event->bindings),
            'db.query.operation' => $this->extractDbOperation($event->sql),
            'db.query.operation.duration' => $event->time,
        ]);
        service('span')->stop((int) $end->getPreciseTimestamp() * 1_000)->detach();
    }

    private function extractDbOperation(string $sql): ?string
    {
        if (Str::startsWith(Str::upper($sql), 'SELECT')) {
            return 'SELECT';
        }

        if (Str::startsWith(Str::upper($sql), 'INSERT')) {
            return 'INSERT';
        }

        if (Str::startsWith(Str::upper($sql), 'UPDATE')) {
            return 'UPDATE';
        }

        if (Str::startsWith(Str::upper($sql), 'DELETE')) {
            return 'DELETE';
        }

        return null;
    }
}
