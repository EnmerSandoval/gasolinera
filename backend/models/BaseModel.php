<?php

declare(strict_types=1);

namespace App\models;

use App\core\Database;
use PDO;

/**
 * Modelo base con operaciones CRUD genéricas.
 * Todas las queries usan prepared statements (PDO).
 */
abstract class BaseModel
{
    protected PDO $pdo;
    protected string $table;
    protected string $primaryKey = 'id';

    public function __construct()
    {
        $this->pdo = Database::getInstance()->getConnection();
    }

    /**
     * Busca un registro por su ID.
     */
    public function find(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT * FROM {$this->table} WHERE {$this->primaryKey} = :id LIMIT 1"
        );
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * Busca registros con condiciones WHERE.
     *
     * @param array<string, mixed> $conditions  ['campo' => valor]
     * @param string               $orderBy     Ej: 'created_at DESC'
     * @param int|null             $limit
     * @param int                  $offset
     */
    public function findWhere(
        array $conditions = [],
        string $orderBy = '',
        ?int $limit = null,
        int $offset = 0
    ): array {
        $sql = "SELECT * FROM {$this->table}";
        $params = [];

        if ($conditions) {
            $clauses = [];
            foreach ($conditions as $field => $value) {
                $placeholder = ':w_' . str_replace('.', '_', $field);
                $clauses[] = "{$field} = {$placeholder}";
                $params[$placeholder] = $value;
            }
            $sql .= ' WHERE ' . implode(' AND ', $clauses);
        }

        if ($orderBy) {
            $sql .= " ORDER BY {$orderBy}";
        }
        if ($limit !== null) {
            $sql .= " LIMIT {$limit} OFFSET {$offset}";
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Cuenta registros con condiciones.
     */
    public function count(array $conditions = []): int
    {
        $sql = "SELECT COUNT(*) FROM {$this->table}";
        $params = [];

        if ($conditions) {
            $clauses = [];
            foreach ($conditions as $field => $value) {
                $placeholder = ':c_' . str_replace('.', '_', $field);
                $clauses[] = "{$field} = {$placeholder}";
                $params[$placeholder] = $value;
            }
            $sql .= ' WHERE ' . implode(' AND ', $clauses);
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    /**
     * Inserta un registro y devuelve el ID.
     *
     * @param array<string, mixed> $data ['campo' => valor]
     */
    public function create(array $data): int
    {
        $fields = array_keys($data);
        $placeholders = array_map(fn ($f) => ":{$f}", $fields);

        $sql = sprintf(
            'INSERT INTO %s (%s) VALUES (%s)',
            $this->table,
            implode(', ', $fields),
            implode(', ', $placeholders)
        );

        $params = [];
        foreach ($data as $field => $value) {
            $params[":{$field}"] = $value;
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Actualiza un registro por ID.
     */
    public function update(int $id, array $data): bool
    {
        $setClauses = [];
        $params = [':id' => $id];

        foreach ($data as $field => $value) {
            $placeholder = ":u_{$field}";
            $setClauses[] = "{$field} = {$placeholder}";
            $params[$placeholder] = $value;
        }

        $sql = sprintf(
            'UPDATE %s SET %s WHERE %s = :id',
            $this->table,
            implode(', ', $setClauses),
            $this->primaryKey
        );

        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute($params);
    }

    /**
     * Elimina un registro por ID (hard delete).
     */
    public function delete(int $id): bool
    {
        $stmt = $this->pdo->prepare(
            "DELETE FROM {$this->table} WHERE {$this->primaryKey} = :id"
        );
        return $stmt->execute([':id' => $id]);
    }

    /**
     * Soft delete: marca como inactivo.
     */
    public function deactivate(int $id): bool
    {
        return $this->update($id, ['activo' => 0]);
    }

    /**
     * Ejecuta una query personalizada con prepared statements.
     */
    protected function query(string $sql, array $params = []): array
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Ejecuta una query que retorna un solo valor escalar.
     */
    protected function scalar(string $sql, array $params = []): mixed
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchColumn();
    }
}
