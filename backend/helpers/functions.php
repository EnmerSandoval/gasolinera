<?php

declare(strict_types=1);

/**
 * Carga un archivo .env y define sus variables en $_ENV y putenv().
 */
function loadEnv(string $path): void
{
    if (!file_exists($path)) {
        return;
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }
        if (!str_contains($line, '=')) {
            continue;
        }

        [$name, $value] = explode('=', $line, 2);
        $name  = trim($name);
        $value = trim($value);

        // Quitar comillas
        if (preg_match('/^"(.*)"$/', $value, $m)) {
            $value = $m[1];
        } elseif (preg_match("/^'(.*)'$/", $value, $m)) {
            $value = $m[1];
        }

        // Convertir booleans/null
        $lower = strtolower($value);
        if ($lower === 'true') {
            $value = true;
        } elseif ($lower === 'false') {
            $value = false;
        } elseif ($lower === 'null') {
            $value = null;
        }

        $_ENV[$name] = $value;
        putenv("{$name}={$value}");
    }
}

/**
 * Obtiene una variable de entorno con valor por defecto.
 */
function env(string $key, mixed $default = null): mixed
{
    return $_ENV[$key] ?? $default;
}

/**
 * Respuesta JSON estandarizada.
 */
function jsonResponse(mixed $data, int $statusCode = 200, array $headers = []): never
{
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    foreach ($headers as $name => $value) {
        header("{$name}: {$value}");
    }
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/**
 * Respuesta de error JSON.
 */
function jsonError(string $message, int $statusCode = 400, array $errors = []): never
{
    $body = ['error' => true, 'message' => $message];
    if ($errors) {
        $body['errors'] = $errors;
    }
    jsonResponse($body, $statusCode);
}

/**
 * Obtiene el body JSON del request parseado.
 */
function getJsonInput(): array
{
    $raw = file_get_contents('php://input');
    if ($raw === '' || $raw === false) {
        return [];
    }
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

/**
 * Sanitiza y valida un valor de entrada.
 */
function sanitize(mixed $value, string $type = 'string'): mixed
{
    return match ($type) {
        'int'    => filter_var($value, FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE),
        'float'  => filter_var($value, FILTER_VALIDATE_FLOAT, FILTER_NULL_ON_FAILURE),
        'email'  => filter_var(trim((string) $value), FILTER_VALIDATE_EMAIL) ?: null,
        'bool'   => filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE),
        default  => htmlspecialchars(trim((string) $value), ENT_QUOTES | ENT_HTML5, 'UTF-8'),
    };
}

/**
 * Genera un UUID v4.
 */
function uuid4(): string
{
    $data = random_bytes(16);
    $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
    $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}
