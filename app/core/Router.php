<?php

namespace App\Core;

/**
 * Routeur minimal à base d'action = "controller/methode".
 * Utilisé par public/index.php : index.php?action=stagiaires/liste
 */
class Router
{
    /** @var array<string,string> action => "ControllerClass@methode" */
    private array $routes = [];

    public function map(string $action, string $handler): void
    {
        $this->routes[$action] = $handler;
    }

    public function dispatch(string $action): void
    {
        if (!isset($this->routes[$action])) {
            http_response_code(404);
            require __DIR__ . '/../views/errors/404.php';
            return;
        }
        [$class, $method] = explode('@', $this->routes[$action]);
        $fqcn = "App\\Controllers\\{$class}";
        $controller = new $fqcn();
        $controller->$method();
    }
}
