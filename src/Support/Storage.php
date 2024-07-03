<?php

namespace Hazuli\Ci4Otel\Support;

use Hazuli\Ci4Otel\Config\Otel as OtelConfig;

class Storage
{
    private $cache;

    private $config;

    public function __construct(OtelConfig $config)
    {
        $this->config = $config;

        $this->cache = service('cache');
    }

    /**
     * Set storage
     *
     * @param string $key
     * @param $value
     * @param int $expire default 30 days
     * @return void
     */
    public function set(string $key, $value, int $expire = 60 * 60 * 24 * 30)
    {
        $this->cache->save($key, $value, $expire);

        return $value;
    }

    public function get(string $key)
    {
        return $this->cache->get($key);
    }

    public function add(string $key, $addition)
    {
        if ($this->cache->get($key)) {
            return $this->set($key, $this->get($key) + $addition);
        }

        return $this->set($key, $addition);
    }

    public function keyFromArray(array $array): string
    {
        $result = [];
        foreach ($array as $key => $value) {
            $result[] = "$key: $value";
        }
        $key = implode(', ', $result);
        $cleanKey = preg_replace("/[^A-Za-z0-9 ]/", ' ', $key);

        return $cleanKey;
    }
}
