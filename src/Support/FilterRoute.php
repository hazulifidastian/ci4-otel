<?php

namespace Hazuli\Ci4Otel\Support;

class FilterRoute
{
    private array $routes;
    private string $route;

    public function __construct(array $routes, string $route)
    {
        $this->routes = $routes;
        $this->route = $route;
    }

    public function isEmpty(): bool
    {
        return empty($this->routes);
    }

    public function isAll(): bool
    {
        return in_array('*', $this->routes);
    }

    public function isMatch()
    {
        $match = false;
        $currentRouteUri = $this->route;
        foreach ($this->routes as $route) {
            $pattern = '/' . $route . '/';
            if (preg_match($pattern, $currentRouteUri)) {
                $match = true;
                break;
            }
        }

        return $match;
    }
}
