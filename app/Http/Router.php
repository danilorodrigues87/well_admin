<?php

namespace App\Http;

use Closure;
use Exception;
use ReflectionFunction;
use App\Http\Middleware\Queue as MiddlewareQueue;
use App\Utils\View;

class Router
{
    private string $url;
    private string $prefix = '';
    private array $routes = [];
    private Request $request;
    private string $contentType = 'text/html';

    public function __construct(string $url)
    {
        $this->request = new Request($this);
        $this->url = rtrim($url, '/');
        $this->setPrefix();
    }

    public function setContentType(string $contentType): void
    {
        $this->contentType = $contentType;
    }

    private function setPrefix(): void
    {
        $scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
        if ($scriptDir === '/' || $scriptDir === '.') {
            $scriptDir = '';
        }
        $this->prefix = rtrim($scriptDir, '/');
    }

    private function addRoute(string $method, string $route, array $params = []): void
    {
        foreach ($params as $key => $value) {
            if ($value instanceof Closure) {
                $params['controller'] = $value;
                unset($params[$key]);
            }
        }

        $params['middlewares'] = $params['middlewares'] ?? [];
        $params['variables'] = [];

        $patternPlus = '/\{([a-zA-Z_][a-zA-Z0-9_]*)\+\}/';
        if (preg_match_all($patternPlus, $route, $matchesPlus)) {
            $route = preg_replace($patternPlus, '(.+)', $route);
            $params['variables'] = array_merge($params['variables'], $matchesPlus[1]);
        }

        $patternVariable = '/\{([a-zA-Z_][a-zA-Z0-9_]*)\}/';
        if (preg_match_all($patternVariable, $route, $matches)) {
            $route = preg_replace($patternVariable, '([^/]+)', $route);
            $params['variables'] = array_merge($params['variables'], $matches[1]);
        }

        $route = rtrim($route, '/');
        if ($route === '') {
            $route = '/';
        }

        $patternRoute = '/^'.str_replace('/', '\/', $route).'$/';
        $this->routes[$patternRoute][$method] = $params;
    }

    public function get(string $route, array $params = []): void
    {
        $this->addRoute('GET', $route, $params);
    }

    public function post(string $route, array $params = []): void
    {
        $this->addRoute('POST', $route, $params);
    }

    public function put(string $route, array $params = []): void
    {
        $this->addRoute('PUT', $route, $params);
    }

    public function patch(string $route, array $params = []): void
    {
        $this->addRoute('PATCH', $route, $params);
    }

    public function delete(string $route, array $params = []): void
    {
        $this->addRoute('DELETE', $route, $params);
    }

    public function options(string $route, array $params = []): void
    {
        $this->addRoute('OPTIONS', $route, $params);
    }

    public function getUri(): string
    {
        $uri = $this->request->getUri();
        $prefix = $this->prefix;
        if ($prefix !== '' && str_starts_with($uri, $prefix)) {
            $uri = substr($uri, strlen($prefix)) ?: '/';
        }
        $uri = '/'.ltrim($uri, '/');
        $uri = rtrim($uri, '/');
        return $uri === '' ? '/' : $uri;
    }

    private function getRoute(): array
    {
        $uri = $this->getUri();
        $httpMethod = $this->request->getHttpMethod();
        $uriMatched = false;

        foreach ($this->routes as $patternRoute => $methods) {
            if (preg_match($patternRoute, $uri, $matches)) {
                $uriMatched = true;
                if (!isset($methods[$httpMethod])) {
                    continue;
                }
                unset($matches[0]);
                $keys = $methods[$httpMethod]['variables'];
                $methods[$httpMethod]['variables'] = array_combine($keys, $matches);
                $methods[$httpMethod]['variables']['request'] = $this->request;
                return $methods[$httpMethod];
            }
        }

        $errVars = ['URL' => defined('URL') ? URL : ''];

        if ($uriMatched) {
            throw new Exception(View::render('erros/405', $errVars), 405);
        }

        throw new Exception(View::render('erros/404', $errVars), 404);
    }

    public function run(): Response
    {
        try {
            $route = $this->getRoute();

            if (!isset($route['controller'])) {
                throw new Exception(View::render('erros/405', ['URL' => defined('URL') ? URL : '']), 500);
            }

            $args = [];
            $reflection = new ReflectionFunction($route['controller']);
            foreach ($reflection->getParameters() as $parameter) {
                $name = $parameter->getName();
                $args[$name] = $route['variables'][$name] ?? '';
            }

            return (new MiddlewareQueue($route['middlewares'], $route['controller'], $args))->next($this->request);
        } catch (\Throwable $e) {
            $code = (int)$e->getCode();
            if ($code < 400 || $code > 599) {
                $code = 500;
            }
            return new Response($code, $this->getErrorMessage($e->getMessage(), $code), $this->contentType);
        }
    }

    private function getErrorMessage(string $message, int $httpCode = 404): string|array
    {
        if ($this->contentType === 'application/json') {
            if (str_contains($message, '<!doctype') || str_contains($message, '<html')) {
                $message = 'Recurso não encontrado.';
            }
            $code = match ($httpCode) {
                401 => 'unauthorized',
                403 => 'forbidden',
                404 => 'not_found',
                405 => 'method_not_allowed',
                default => 'error',
            };
            return [
                'success' => false,
                'error' => [
                    'code' => $code,
                    'message' => $message,
                ],
            ];
        }
        return $message;
    }

    public function redirect(string $route): never
    {
        $url = $this->url.'/'.ltrim($route, '/');
        header('Location: '.$url);
        exit;
    }
}
