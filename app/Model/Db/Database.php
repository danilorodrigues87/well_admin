<?php

namespace App\Model\Db;

use App\Common\Environment;
use PDO;
use PDOException;

Environment::load(dirname(__DIR__, 3));

if (!defined('DB_HOST')) {
    define('DB_HOST', (string)Environment::get('DB_HOST', 'localhost'));
    define('DB_NAME', (string)Environment::get('DB_NAME', ''));
    define('DB_USER', (string)Environment::get('DB_USER', ''));
    define('DB_PASS', (string)Environment::get('DB_PASS', ''));
}

class Database
{
    private ?string $table;
    private PDO $connection;

    public function __construct(?string $table = null)
    {
        $this->table = $table;
        $this->setConnection();
    }

    private function setConnection(): void
    {
        if (DB_USER === '' || DB_NAME === '') {
            throw new PDOException('Credenciais DB vazias. Verifique o arquivo .env');
        }
        try {
            $this->connection = new PDO(
                'mysql:host='.DB_HOST.';dbname='.DB_NAME.';charset=utf8mb4',
                DB_USER,
                DB_PASS,
                [PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci']
            );
            $this->connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->connection->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log('[Database] '.$e->getMessage());
            throw new \RuntimeException('Erro de conexão com o banco de dados.', 0, $e);
        }
    }

    public function execute(string $query, array $params = []): \PDOStatement
    {
        try {
            $statement = $this->connection->prepare($query);
            $statement->execute($params);
            return $statement;
        } catch (PDOException $e) {
            error_log('[Database] query: '.$e->getMessage());
            throw new \RuntimeException('Erro interno de banco de dados.', 0, $e);
        }
    }

    public function insert(array $values): string
    {
        $fields = array_keys($values);
        $binds = array_fill(0, count($fields), '?');
        $query = 'INSERT INTO '.$this->table.' ('.implode(',', $fields).') VALUES ('.implode(',', $binds).')';
        $this->execute($query, array_values($values));
        return (string)$this->connection->lastInsertId();
    }

    public function select(
        ?string $where = null,
        ?string $order = null,
        ?string $limit = null,
        string $fields = '*',
        ?string $innerJoin = null
    ): \PDOStatement {
        $whereSql = ($where !== null && $where !== '') ? 'WHERE '.$where : '';
        $orderSql = ($order !== null && $order !== '') ? 'ORDER BY '.$order : '';
        $limitSql = ($limit !== null && $limit !== '') ? 'LIMIT '.$limit : '';
        $joinSql = ($innerJoin !== null && $innerJoin !== '') ? $innerJoin : '';
        $query = 'SELECT '.$fields.' FROM '.$this->table.' '.$joinSql.' '.$whereSql.' '.$orderSql.' '.$limitSql;
        return $this->execute($query);
    }

    public function update(string $where, array $values): bool
    {
        $fields = array_keys($values);
        $query = 'UPDATE '.$this->table.' SET '.implode('=?,', $fields).'=? WHERE '.$where;
        $this->execute($query, array_values($values));
        return true;
    }

    public function delete(string $where): bool
    {
        $this->execute('DELETE FROM '.$this->table.' WHERE '.$where);
        return true;
    }

    public function beginTransaction(): void
    {
        $this->connection->beginTransaction();
    }

    public function commit(): void
    {
        $this->connection->commit();
    }

    public function rollBack(): void
    {
        if ($this->connection->inTransaction()) {
            $this->connection->rollBack();
        }
    }

    public function lastInsertId(): string
    {
        return (string)$this->connection->lastInsertId();
    }
}
