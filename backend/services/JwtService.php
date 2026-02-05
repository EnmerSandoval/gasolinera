<?php

declare(strict_types=1);

namespace App\services;

/**
 * Servicio JWT manual (sin dependencias externas).
 * Implementa HS256 (HMAC-SHA256).
 */
final class JwtService
{
    private string $secret;
    private string $algorithm;
    private int $accessTtl;
    private int $refreshTtl;
    private string $issuer;

    public function __construct()
    {
        $config = require dirname(__DIR__) . '/config/app.php';
        $jwt = $config['jwt'];

        $this->secret     = $jwt['secret'];
        $this->algorithm  = $jwt['algorithm'];
        $this->accessTtl  = $jwt['access_ttl'];
        $this->refreshTtl = $jwt['refresh_ttl'];
        $this->issuer     = $jwt['issuer'];

        if (empty($this->secret)) {
            throw new \RuntimeException('JWT_SECRET no está configurado.');
        }
    }

    /**
     * Genera un access token para el usuario dado.
     *
     * @param array $payload Datos del usuario (sub, empresa_id, sucursal_id, rol, nombre)
     */
    public function generateAccessToken(array $payload): string
    {
        $now = time();
        $claims = array_merge($payload, [
            'iss' => $this->issuer,
            'iat' => $now,
            'exp' => $now + $this->accessTtl,
            'type' => 'access',
        ]);
        return $this->encode($claims);
    }

    /**
     * Genera un refresh token.
     */
    public function generateRefreshToken(int $userId): string
    {
        $now = time();
        $claims = [
            'sub'  => $userId,
            'iss'  => $this->issuer,
            'iat'  => $now,
            'exp'  => $now + $this->refreshTtl,
            'type' => 'refresh',
            'jti'  => uuid4(),
        ];
        return $this->encode($claims);
    }

    /**
     * Decodifica y valida un token. Devuelve payload o null si inválido.
     */
    public function decode(string $token): ?array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return null;
        }

        [$headerB64, $payloadB64, $signatureB64] = $parts;

        // Verificar firma
        $signatureCheck = $this->sign("{$headerB64}.{$payloadB64}");
        if (!hash_equals($signatureCheck, $this->base64UrlDecode($signatureB64))) {
            return null;
        }

        $payload = json_decode($this->base64UrlDecode($payloadB64), true);
        if (!is_array($payload)) {
            return null;
        }

        // Verificar expiración
        if (isset($payload['exp']) && $payload['exp'] < time()) {
            return null;
        }

        return $payload;
    }

    /**
     * Verifica que sea un access token válido.
     */
    public function verifyAccessToken(string $token): ?array
    {
        $payload = $this->decode($token);
        if ($payload === null || ($payload['type'] ?? '') !== 'access') {
            return null;
        }
        return $payload;
    }

    // --- Internals ---

    private function encode(array $payload): string
    {
        $header = $this->base64UrlEncode(json_encode([
            'typ' => 'JWT',
            'alg' => $this->algorithm,
        ]));

        $body = $this->base64UrlEncode(json_encode($payload));

        $signature = $this->base64UrlEncode($this->sign("{$header}.{$body}"));

        return "{$header}.{$body}.{$signature}";
    }

    private function sign(string $data): string
    {
        return hash_hmac('sha256', $data, $this->secret, true);
    }

    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private function base64UrlDecode(string $data): string
    {
        $remainder = strlen($data) % 4;
        if ($remainder) {
            $data .= str_repeat('=', 4 - $remainder);
        }
        return base64_decode(strtr($data, '-_', '+/'));
    }
}
