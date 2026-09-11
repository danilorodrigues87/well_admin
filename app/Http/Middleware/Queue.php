<?php

namespace App\Http\Middleware;

class Queue
{
    private static array $map = [];
    private static array $default = [];
    private array $middlewares;
    private \Closure $controller;
    private array $controllerArgs;

    public function __construct(array $middlewares, \Closure $controller, array $controllerArgs)
    {
        $this->middlewares = array_merge(self::$default, $middlewares);
        $this->controller = $controller;
        $this->controllerArgs = $controllerArgs;
    }

    public static function setMap(array $map): void
    {
        self::$map = $map;
    }

    public static function setDefault(array $default): void
    {
        self::$default = $default;
    }

    public function next($request)
    {
        if (empty($this->middlewares)) {
            return call_user_func_array($this->controller, $this->controllerArgs);
        }

        $middleware = array_shift($this->middlewares);

        if (str_starts_with($middleware, 'required-module:')) {
            $slug = substr($middleware, strlen('required-module:'));
            return (new RequireModule($slug))->handle($request, fn ($req) => $this->next($req));
        }

        if (!isset(self::$map[$middleware])) {
            throw new \RuntimeException('Middleware não mapeado: '.$middleware, 500);
        }

        $queue = $this;
        $next = fn ($req) => $queue->next($req);

        return (new self::$map[$middleware])->handle($request, $next);
    }
}
