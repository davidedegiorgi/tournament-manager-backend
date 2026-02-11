<?php

namespace App\Utils;

class Request
{
    private array $data;
    private string $method;
    private string $path;
    private array $params;

    public function __construct()
    {
        // Gestione X-HTTP-Method-Override header
        $this->method = $_SERVER['REQUEST_METHOD'];
        if (isset($_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE'])) {
            $this->method = strtoupper($_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE']);
        }
        
        $this->path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        $this->params = [];
        
        // Prima prova con $_POST (form-data)
        $this->data = $_POST;
        
        // Se $_POST è vuoto, prova a leggere php://input
        if (empty($this->data)) {
            $input = file_get_contents('php://input');
            
            // Se il content-type è application/x-www-form-urlencoded, parsa come query string
            $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
            if (strpos($contentType, 'application/x-www-form-urlencoded') !== false) {
                parse_str($input, $this->data);
            } else {
                // Altrimenti prova JSON
                $this->data = $input ? json_decode($input, true) ?? [] : [];
            }
        }
        
        // Parse query parameters
        parse_str($_SERVER['QUERY_STRING'] ?? '', $query);
        $this->data = array_merge($this->data, $query);
    }

    public function getMethod(): string
    {
        return $this->method;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function getData(): array
    {
        return $this->data;
    }

    public function get(string $key, $default = null)
    {
        return $this->data[$key] ?? $default;
    }

    public function all(): array
    {
        return $this->data;
    }

    public function setParams(array $params): void
    {
        $this->params = $params;
    }

    public function param(string $key, $default = null)
    {
        return $this->params[$key] ?? $default;
    }

    public function only(array $keys): array
    {
        return array_intersect_key($this->data, array_flip($keys));
    }
}
