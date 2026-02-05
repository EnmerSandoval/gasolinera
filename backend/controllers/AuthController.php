<?php

declare(strict_types=1);

namespace App\controllers;

use App\core\Database;
use App\core\Request;
use App\models\Usuario;
use App\services\JwtService;

/**
 * Controlador de Autenticación.
 * Maneja login, refresh token y perfil del usuario.
 */
final class AuthController
{
    /**
     * POST /auth/login
     * Body: { email, password }
     */
    public function login(Request $request): void
    {
        $errors = $request->validate([
            'email'    => 'email',
            'password' => 'string',
        ]);

        if ($errors) {
            jsonError('Datos de login inválidos.', 422, $errors);
        }

        $email    = sanitize($request->input('email'), 'email');
        $password = $request->input('password');

        $userModel = new Usuario();
        $user = $userModel->findByEmail($email);

        if (!$user || !$userModel->verifyPassword($password, $user['password_hash'])) {
            jsonError('Credenciales incorrectas.', 401);
        }

        // Generar tokens
        $jwt = new JwtService();

        $accessPayload = [
            'sub'         => (int) $user['id'],
            'empresa_id'  => (int) $user['empresa_id'],
            'sucursal_id' => $user['sucursal_id'] !== null ? (int) $user['sucursal_id'] : null,
            'rol'         => $user['rol_nombre'],
            'nombre'      => $user['nombre_completo'],
        ];

        $accessToken  = $jwt->generateAccessToken($accessPayload);
        $refreshToken = $jwt->generateRefreshToken((int) $user['id']);

        // Guardar refresh token hasheado en BD
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare(
            'INSERT INTO refresh_tokens (usuario_id, token_hash, expires_at)
             VALUES (:uid, :hash, :exp)'
        );
        $stmt->execute([
            ':uid'  => $user['id'],
            ':hash' => hash('sha256', $refreshToken),
            ':exp'  => date('Y-m-d H:i:s', time() + 604800),
        ]);

        // Actualizar último login
        $userModel->updateLastLogin((int) $user['id']);

        jsonResponse([
            'access_token'  => $accessToken,
            'refresh_token' => $refreshToken,
            'token_type'    => 'Bearer',
            'expires_in'    => 3600,
            'user' => [
                'id'              => (int) $user['id'],
                'nombre'          => $user['nombre_completo'],
                'email'           => $user['email'],
                'rol'             => $user['rol_nombre'],
                'empresa_id'      => (int) $user['empresa_id'],
                'empresa_nombre'  => $user['empresa_nombre'],
                'sucursal_id'     => $user['sucursal_id'] !== null ? (int) $user['sucursal_id'] : null,
            ],
        ]);
    }

    /**
     * POST /auth/refresh
     * Body: { refresh_token }
     */
    public function refresh(Request $request): void
    {
        $refreshToken = $request->input('refresh_token');
        if (!$refreshToken) {
            jsonError('Refresh token requerido.', 400);
        }

        $jwt = new JwtService();
        $payload = $jwt->decode($refreshToken);

        if (!$payload || ($payload['type'] ?? '') !== 'refresh') {
            jsonError('Refresh token inválido.', 401);
        }

        // Verificar en BD
        $db = Database::getInstance()->getConnection();
        $tokenHash = hash('sha256', $refreshToken);
        $stmt = $db->prepare(
            'SELECT id FROM refresh_tokens
             WHERE usuario_id = :uid AND token_hash = :hash AND revoked = 0 AND expires_at > NOW()
             LIMIT 1'
        );
        $stmt->execute([':uid' => $payload['sub'], ':hash' => $tokenHash]);

        if (!$stmt->fetch()) {
            jsonError('Refresh token revocado o expirado.', 401);
        }

        // Revocar token anterior
        $stmt2 = $db->prepare('UPDATE refresh_tokens SET revoked = 1 WHERE token_hash = :hash');
        $stmt2->execute([':hash' => $tokenHash]);

        // Obtener datos actualizados del usuario
        $userModel = new Usuario();
        $user = $userModel->find((int) $payload['sub']);
        if (!$user) {
            jsonError('Usuario no encontrado.', 404);
        }

        // Obtener rol
        $rolStmt = $db->prepare('SELECT nombre FROM roles WHERE id = :rid');
        $rolStmt->execute([':rid' => $user['rol_id']]);
        $rolNombre = $rolStmt->fetchColumn();

        // Nuevos tokens
        $newAccess = $jwt->generateAccessToken([
            'sub'         => (int) $user['id'],
            'empresa_id'  => (int) $user['empresa_id'],
            'sucursal_id' => $user['sucursal_id'] !== null ? (int) $user['sucursal_id'] : null,
            'rol'         => $rolNombre,
            'nombre'      => $user['nombre_completo'],
        ]);
        $newRefresh = $jwt->generateRefreshToken((int) $user['id']);

        // Guardar nuevo refresh
        $stmt3 = $db->prepare(
            'INSERT INTO refresh_tokens (usuario_id, token_hash, expires_at)
             VALUES (:uid, :hash, :exp)'
        );
        $stmt3->execute([
            ':uid'  => $user['id'],
            ':hash' => hash('sha256', $newRefresh),
            ':exp'  => date('Y-m-d H:i:s', time() + 604800),
        ]);

        jsonResponse([
            'access_token'  => $newAccess,
            'refresh_token' => $newRefresh,
            'token_type'    => 'Bearer',
            'expires_in'    => 3600,
        ]);
    }

    /**
     * POST /auth/logout (requiere auth)
     */
    public function logout(Request $request): void
    {
        $userId = $request->userId();

        // Revocar todos los refresh tokens del usuario
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare('UPDATE refresh_tokens SET revoked = 1 WHERE usuario_id = :uid');
        $stmt->execute([':uid' => $userId]);

        jsonResponse(['message' => 'Sesión cerrada correctamente.']);
    }

    /**
     * GET /auth/me (requiere auth)
     */
    public function me(Request $request): void
    {
        $userModel = new Usuario();
        $user = $userModel->find($request->userId());

        if (!$user) {
            jsonError('Usuario no encontrado.', 404);
        }

        unset($user['password_hash']);
        jsonResponse(['user' => $user]);
    }
}
