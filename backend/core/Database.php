<?php

declare(strict_types=1);

namespace App\core;

use PDO;
use PDOException;

/**
 * Conexión a BD via PDO - Patrón Singleton.
 *
 * Uso:
 *   $db = Database::getInstance();
 *   $pdo = $db->getConnection();
 */
final class Database
{
    private static ?self $instance = null;
    private PDO $pdo;

    private function __construct()
    {
        $config = require dirname(__DIR__) . '/config/app.php';
        $db = $config['db'];

        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            $db['host'],
            $db['port'],
            $db['database'],
            $db['charset']
        );

        try {
            $this->pdo = new PDO($dsn, $db['username'], $db['password'], [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::ATTR_STRINGIFY_FETCHES  => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES '{$db['charset']}' COLLATE '{$db['collation']}'",
            ]);
        } catch (PDOException $e) {
            // En producción no exponer detalles de conexión
            if (env('APP_DEBUG', false)) {
                throw $e;
            }
            throw new PDOException('Error de conexión a base de datos.');
        }
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function getConnection(): PDO
    {
        return $this->pdo;
    }

    /**
     * Ejecuta una transacción con rollback automático en caso de error.
     */
    public function transaction(callable $callback): mixed
    {
        $this->pdo->beginTransaction();
        try {
            $result = $callback($this->pdo);
            $this->pdo->commit();
            return $result;
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    // Prevenir clonar y deserializar
    private function __clone() {}
    public function __wakeup(): never
    {
        throw new \RuntimeException('No se puede deserializar un Singleton.');
    }
}
