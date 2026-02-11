<?php
class Router {
    private $routes = [];

    public function get($path, $handler) {
        $this->routes['GET'][$path] = $handler;
    }

    public function post($path, $handler) {
        $this->routes['POST'][$path] = $handler;
    }

    public function put($path, $handler) {
        $this->routes['PUT'][$path] = $handler;
    }

    public function delete($path, $handler) {
        $this->routes['DELETE'][$path] = $handler;
    }

    private function resolveMethod(): string {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

        // 일부 환경에서 PUT/DELETE가 막히거나 폼 전송에서 오버라이드 할 때 대비
        // 1) Header override
        $override = $_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE'] ?? '';
        if ($override) {
            $override = strtoupper(trim($override));
            if (in_array($override, ['PUT', 'DELETE'], true)) {
                return $override;
            }
        }

        // 2) Query override (?_method=PUT)
        if ($method === 'POST' && isset($_GET['_method'])) {
            $m = strtoupper(trim((string)$_GET['_method']));
            if (in_array($m, ['PUT', 'DELETE'], true)) {
                return $m;
            }
        }

        return strtoupper($method);
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
