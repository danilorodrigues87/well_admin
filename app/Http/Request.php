<?php

namespace App\Http;

class Request
{
    private Router $router;
    private string $httpMethod;
    private string $uri;
    private array $queryParams;
    private array $postVars;
    private array $headers;
    private array $fileVars;

    public function __construct(Router $router)
    {
        $this->router = $router;
        $this->queryParams = $_GET ?? [];
        $this->headers = function_exists('getallheaders') ? (getallheaders() ?: []) : [];
        $this->httpMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $this->setUri();
        $this->setPostVars();
        $this->fileVars = $_FILES ?? [];
    }

    private function setPostVars(): void
    {
        if ($this->httpMethod === 'GET') {
            $this->postVars = [];
            return;
        }
        $this->postVars = $_POST ?? [];
        $inputRow = file_get_contents('php://input');
        if (strlen($inputRow) && empty($_POST)) {
            $decoded = json_decode($inputRow, true);
            if (is_array($decoded)) {
                $this->postVars = $decoded;
            }
        }
    }

    private function setUri(): void
    {
        $this->uri = $_SERVER['REQUEST_URI'] ?? '';
        $parts = explode('?', $this->uri);
        $this->uri = $parts[0];
    }

    public function getHttpMethod(): string
    {
        return $this->httpMethod;
    }

    public function getUri(): string
    {
        return $this->uri;
    }

    public function getHeaders(): array
    {
        return $this->headers;
    }

    public function getQueryParams(): array
    {
        return $this->queryParams;
    }

    public function getPostVars(): array
    {
        return $this->postVars;
    }

    public function getFileVars(): array
    {
        return $this->fileVars;
    }

    public function getRouter(): Router
    {
        return $this->router;
    }

    public function getCsrfToken(): string
    {
        return $_SESSION['well_eco_csrf'] ?? '';
    }
}
