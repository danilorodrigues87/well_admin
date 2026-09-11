<?php

namespace App\Http;

class Response
{
    private int $httpCode;
    private array $headers;
    private string $contentType;
    private mixed $content;

    public function __construct(int $httpCode, mixed $content, string $contentType = 'text/html')
    {
        $this->httpCode = $httpCode;
        $this->content = $content;
        $this->headers = [];
        $this->setContentType($contentType);
    }

    public function setContentType(string $contentType): void
    {
        $this->contentType = $contentType;
        if ($contentType === 'text/html') {
            $this->addHeader('Content-Type', 'text/html; charset=utf-8');
        } elseif ($contentType === 'application/json') {
            $this->addHeader('Content-Type', 'application/json; charset=utf-8');
        } else {
            $this->addHeader('Content-Type', $contentType);
        }
    }

    public function addHeader(string $key, string $value): void
    {
        $this->headers[$key] = $value;
    }

    private function sendHeaders(): void
    {
        http_response_code($this->httpCode);
        foreach ($this->headers as $key => $value) {
            header($key.': '.$value);
        }
    }

    public function sendResponse(): never
    {
        $this->sendHeaders();
        if ($this->contentType === 'application/json') {
            echo is_string($this->content)
                ? $this->content
                : json_encode($this->content, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit;
        }
        echo $this->content;
        exit;
    }
}
