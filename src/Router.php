<?php
class Router {
    private $routes = [];

    public function get($path, $handler) {
        $this->routes['GET'][$path] = $handler;
    }

    public function post($path, $handler) {
        $this->routes['POST'][$path] = $handler;
    }

    public function run() {
        $method = $_SERVER['REQUEST_METHOD'];
        $uri = strtok($_SERVER['REQUEST_URI'], '?');

        if (!isset($this->routes[$method][$uri])) {
            Json::error(404, 'NOT_FOUND');
        }

        $handler = $this->routes[$method][$uri];

        if (is_callable($handler)) {
            $handler();
            return;
        }

        [$class, $methodName] = explode('@', $handler);
        require_once __DIR__ . "/{$class}.php";
        call_user_func([new $class, $methodName]);
    }

    public function dispatch(): void
    {
        $this->run();
    }
}
