<?php

declare(strict_types=1);

namespace App\core;

/**
 * Encapsula el request HTTP entrante.
 */
final class Request
{
    private string $method;
    private string $uri;
    private string $path;
    private array $query;
    private array $body;
    private array $headers;
    private array $params = []; // Parámetros de ruta dinámica

    /** @var array Datos del usuario autenticado (poblado por AuthMiddleware) */
    private array $user = [];

    /** @var int|null Sucursal activa del request */
    private ?int $sucursalId = null;

    public function __construct()
    {
        $this->method  = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        $this->uri     = $_SERVER['REQUEST_URI'] ?? '/';
        $this->query   = $_GET;
        $this->body    = getJsonInput();
        $this->headers = $this->parseHeaders();

        // Extraer path sin query string
        $parsed = parse_url($this->uri);
        $this->path = $parsed['path'] ?? '/';

        // Remover prefijo /api si existe
        if (str_starts_with($this->path, '/api')) {
            $this->path = substr($this->path, 4) ?: '/';
        }
    }

    public function method(): string
    {
        return $this->method;
    }

    public function path(): string
    {
        return $this->path;
    }

    public function query(string $key, mixed $default = null): mixed
    {
        return $this->query[$key] ?? $default;
    }

    public function allQuery(): array
    {
        return $this->query;
    }

    public function input(string $key, mixed $default = null): mixed
    {
        return $this->body[$key] ?? $default;
    }

    public function allInput(): array
    {
        return $this->body;
    }

    /**
     * Valida campos requeridos en el body y devuelve errores.
     *
     * @param array<string, string> $rules  ['campo' => 'tipo'] donde tipo = string|int|float|email
     * @return array Lista de errores (vacío si todo OK)
     */
    public function validate(array $rules): array
    {
        $errors = [];
        foreach ($rules as $field => $type) {
            $value = $this->body[$field] ?? null;

            if ($value === null || $value === '') {
                $errors[] = "El campo '{$field}' es requerido.";
                continue;
            }

            $sanitized = sanitize($value, $type);
            if ($sanitized === null && $type !== 'string') {
                $errors[] = "El campo '{$field}' debe ser de tipo {$type}.";
            }
        }
        return $errors;
    }

    public function header(string $name): ?string
    {
        $normalized = strtolower($name);
        return $this->headers[$normalized] ?? null;
    }

    public function bearerToken(): ?string
    {
        $auth = $this->header('authorization');
        if ($auth && str_starts_with($auth, 'Bearer ')) {
            return substr($auth, 7);
        }
        return null;
    }

    // --- Route params ---

    public function setParams(array $params): void
    {
        $this->params = $params;
    }

    public function param(string $key, mixed $default = null): mixed
    {
        return $this->params[$key] ?? $default;
    }

    // --- Auth context ---

    public function setUser(array $user): void
    {
        $this->user = $user;
    }

    public function user(): array
    {
        return $this->user;
    }

    public function userId(): int
    {
        return (int) ($this->user['sub'] ?? 0);
    }

    public function empresaId(): int
    {
        return (int) ($this->user['empresa_id'] ?? 0);
    }

    public function userSucursalId(): ?int
    {
        $id = $this->user['sucursal_id'] ?? null;
        return $id !== null ? (int) $id : null;
    }

    public function rolNombre(): string
    {
        return (string) ($this->user['rol'] ?? '');
    }

    // --- Sucursal activa ---

    public function setSucursalId(int $id): void
    {
        $this->sucursalId = $id;
    }

    public function sucursalId(): ?int
    {
        if ($this->sucursalId !== null) {
            return $this->sucursalId;
        }
        // Intentar desde header
        $headerVal = $this->header('x-sucursal-id');
        if ($headerVal !== null) {
            return (int) $headerVal;
        }
        // Intentar desde query
        $queryVal = $this->query('sucursal_id');
        if ($queryVal !== null) {
            return (int) $queryVal;
        }
        return $this->userSucursalId();
    }

    private function parseHeaders(): array
    {
        $headers = [];
        foreach ($_SERVER as $key => $value) {
            if (str_starts_with($key, 'HTTP_')) {
                $name = strtolower(str_replace('_', '-', substr($key, 5)));
                $headers[$name] = $value;
            }
        }
        // Content-Type y Content-Length no llevan prefijo HTTP_
        if (isset($_SERVER['CONTENT_TYPE'])) {
            $headers['content-type'] = $_SERVER['CONTENT_TYPE'];
        }
        if (isset($_SERVER['CONTENT_LENGTH'])) {
            $headers['content-length'] = $_SERVER['CONTENT_LENGTH'];
        }
        return $headers;
    }
}
