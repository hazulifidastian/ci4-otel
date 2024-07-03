<?php

namespace Hazuli\Ci4Otel\Listeners;

use Illuminate\Database\Events\QueryExecuted;
use CodeIgniter\Events\Events;

class RedisCacheListener
{
    public function register()
    {
        Events::on('CACHE_HIT', function(array $event) {
            service('span')->addEvent('cache hit', [
                'key' => $event['key'],
            ]);
        });

        Events::on('KEY_WRITTEN', function(array $event) {
            $ttl = $event['expired'] ?? 0;

            service('span')->addEvent('cache set', [
                'key' => $event['key'],
                'expires_at' => $ttl > 0 ? \Carbon\Carbon::now()->addSeconds($ttl)->getTimestamp() : 'never',
                'expires_in_seconds' => $ttl > 0 ? $ttl : 'never',
                'expires_in_human' => $ttl > 0 ? \Carbon\Carbon::now()->addSeconds($ttl)->diffForHumans() : 'never',
            ]);
        });
    }
}