<?php

final class Router
{
    private array $routes = [];

    public function get(string $path, array $handler): void
    {
        $this->add('GET', $path, $handler);
    }

    public function post(string $path, array $handler): void
    {
        $this->add('POST', $path, $handler);
    }

    public function match(array $methods, string $path, array $handler): void
    {
        foreach ($methods as $method) {
            $this->add($method, $path, $handler);
        }
    }

    public function dispatch(string $method, string $path): void
    {
        $path = trim($path, '/');
        $key = strtoupper($method) . ' ' . ($path === '' ? 'events' : $path);

        if (!isset($this->routes[$key])) {
            http_response_code(404);
            echo 'Route MVC introuvable.';
            return;
        }

        [$class, $action] = $this->routes[$key];
        $controller = new $class();
        $controller->$action();
    }

    private function add(string $method, string $path, array $handler): void
    {
        $path = trim($path, '/');
        $this->routes[strtoupper($method) . ' ' . ($path === '' ? 'events' : $path)] = $handler;
    }
}
