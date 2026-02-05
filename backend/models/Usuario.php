<?php

declare(strict_types=1);

namespace App\models;

/**
 * Modelo de Usuarios con autenticación.
 */
final class Usuario extends BaseModel
{
    protected string $table = 'usuarios';

    /**
     * Busca usuario por username dentro de una empresa.
     */
    public function findByUsername(string $username, int $empresaId): ?array
    {
        $rows = $this->query(
            'SELECT u.*, r.nombre AS rol_nombre, r.permisos AS rol_permisos
             FROM usuarios u
             JOIN roles r ON r.id = u.rol_id
             WHERE u.username = :username
               AND u.empresa_id = :empresa_id
               AND u.activo = 1
             LIMIT 1',
            [':username' => $username, ':empresa_id' => $empresaId]
        );
        return $rows[0] ?? null;
    }

    /**
     * Busca usuario por email (global).
     */
    public function findByEmail(string $email): ?array
    {
        $rows = $this->query(
            'SELECT u.*, r.nombre AS rol_nombre, r.permisos AS rol_permisos,
                    e.nombre AS empresa_nombre
             FROM usuarios u
             JOIN roles r ON r.id = u.rol_id
             JOIN empresas e ON e.id = u.empresa_id
             WHERE u.email = :email AND u.activo = 1
             LIMIT 1',
            [':email' => $email]
        );
        return $rows[0] ?? null;
    }

    /**
     * Verifica contraseña.
     */
    public function verifyPassword(string $password, string $hash): bool
    {
        return password_verify($password, $hash);
    }

    /**
     * Hash de contraseña con bcrypt.
     */
    public function hashPassword(string $password): string
    {
        return password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
    }

    /**
     * Actualiza timestamp de último login.
     */
    public function updateLastLogin(int $userId): void
    {
        $this->update($userId, ['ultimo_login' => date('Y-m-d H:i:s')]);
    }

    /**
     * Lista usuarios de una empresa con filtro opcional por sucursal.
     */
    public function listByEmpresa(int $empresaId, ?int $sucursalId = null): array
    {
        $sql = 'SELECT u.id, u.username, u.email, u.nombre_completo, u.telefono,
                       u.sucursal_id, u.activo, u.ultimo_login,
                       r.nombre AS rol_nombre,
                       s.nombre AS sucursal_nombre
                FROM usuarios u
                JOIN roles r ON r.id = u.rol_id
                LEFT JOIN sucursales s ON s.id = u.sucursal_id
                WHERE u.empresa_id = :empresa_id';
        $params = [':empresa_id' => $empresaId];

        if ($sucursalId !== null) {
            $sql .= ' AND (u.sucursal_id = :sucursal_id OR u.sucursal_id IS NULL)';
            $params[':sucursal_id'] = $sucursalId;
        }

        $sql .= ' ORDER BY u.nombre_completo';

        return $this->query($sql, $params);
    }
}
