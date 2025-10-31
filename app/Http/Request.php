<?php

namespace App\Http;

class Request
{
    public function __construct(
        private array $query,
        private array $post,
        private array $server
    ) {
    }

    public function method(): string
    {
        return strtoupper($this->server['REQUEST_METHOD'] ?? 'GET');
    }

    public function isPost(): bool
    {
        return $this->method() === 'POST';
    }

    public function query(string $key, mixed $default = null): mixed
    {
        return $this->query[$key] ?? $default;
    }

    public function post(string $key, mixed $default = null): mixed
    {
        return $this->post[$key] ?? $default;
    }

    public function hasPost(string $key): bool
    {
        return array_key_exists($key, $this->post);
    }

    public function allQuery(): array
    {
        return $this->query;
    }

    public function allPost(): array
    {
        return $this->post;
    }
}
