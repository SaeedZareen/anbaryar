<?php

namespace App\Http;

class Request
{
    /**
     * @var array<string, mixed>
     */
    private $query;

    /**
     * @var array<string, mixed>
     */
    private $post;

    /**
     * @var array<string, mixed>
     */
    private $server;

    /**
     * @param array<string, mixed> $query
     * @param array<string, mixed> $post
     * @param array<string, mixed> $server
     */
    public function __construct(array $query, array $post, array $server)
    {
        $this->query = $query;
        $this->post = $post;
        $this->server = $server;
    }

    public function method(): string
    {
        return strtoupper($this->server['REQUEST_METHOD'] ?? 'GET');
    }

    public function isPost(): bool
    {
        return $this->method() === 'POST';
    }

    /**
     * @param mixed $default
     * @return mixed
     */
    public function query(string $key, $default = null)
    {
        return $this->query[$key] ?? $default;
    }

    /**
     * @param mixed $default
     * @return mixed
     */
    public function post(string $key, $default = null)
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
