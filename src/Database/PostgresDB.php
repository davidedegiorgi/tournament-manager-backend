<?php

namespace App\Database;

use PDO;
use PDOException;

class PostgresDB
{
    private ?PDO $pdo = null;
    private array $config;

    public function __construct(array $config)
    {
        $this->config = $config;
        $this->connect();
    }

    private function connect(): void
    {
        try {
            $dsn = sprintf(
                'pgsql:host=%s;port=%s;dbname=%s',
                $this->config['host'],
                $this->config['port'],
                $this->config['database']
            );

            $options = array_merge([
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ], $this->config['options'] ?? []);

            $this->pdo = new PDO(
                $dsn,
                $this->config['username'],
                $this->config['password'],
                $options
            );
        } catch (PDOException $e) {
            throw new \RuntimeException('Errore di connessione al database: ' . $e->getMessage());
        }
    }

    public function getTable(string $table): array
    {
        try {
            $stmt = $this->pdo->prepare("SELECT * FROM {$table} ORDER BY id");
            $stmt->execute();
            $results = $stmt->fetchAll();
            
            // Converti array PostgreSQL in array PHP per tutti i risultati
            return array_map(function($row) {
                if (isset($row['team_ids']) && is_string($row['team_ids'])) {
                    $row['team_ids'] = $this->parsePostgresArray($row['team_ids']);
                }
                return $row;
            }, $results);
        } catch (PDOException $e) {
            throw new \RuntimeException("Errore nella lettura della tabella {$table}: " . $e->getMessage());
        }
    }

    public function find(string $table, int $id): ?array
    {
        try {
            $stmt = $this->pdo->prepare("SELECT * FROM {$table} WHERE id = :id LIMIT 1");
            $stmt->execute(['id' => $id]);
            $result = $stmt->fetch();
            
            if ($result && isset($result['team_ids']) && is_string($result['team_ids'])) {
                $result['team_ids'] = $this->parsePostgresArray($result['team_ids']);
            }
            
            return $result ?: null;
        } catch (PDOException $e) {
            throw new \RuntimeException("Errore nella ricerca in {$table}: " . $e->getMessage());
        }
    }

    public function findWhere(string $table, array $conditions): array
    {
        try {
            $where = [];
            $params = [];
            
            foreach ($conditions as $key => $value) {
                $where[] = "{$key} = :{$key}";
                $params[$key] = $value;
            }
            
            $whereClause = implode(' AND ', $where);
            $sql = "SELECT * FROM {$table} WHERE {$whereClause}";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            $results = $stmt->fetchAll();
            
            // Converti array PostgreSQL in array PHP per tutti i risultati
            return array_map(function($row) {
                if (isset($row['team_ids']) && is_string($row['team_ids'])) {
                    $row['team_ids'] = $this->parsePostgresArray($row['team_ids']);
                }
                return $row;
            }, $results);
        } catch (PDOException $e) {
            throw new \RuntimeException("Errore nella ricerca condizionale in {$table}: " . $e->getMessage());
        }
    }

    public function insert(string $table, array $data): array
    {
        try {
            // Non includiamo id, created_at, updated_at se gestiti dal DB
            $data['created_at'] = date('Y-m-d H:i:s');
            $data['updated_at'] = date('Y-m-d H:i:s');
            
            // Gestione speciale per array PostgreSQL (es. team_ids)
            foreach ($data as $key => $value) {
                if (is_array($value) && $key === 'team_ids') {
                    $data[$key] = '{' . implode(',', $value) . '}';
                }
            }
            
            $columns = array_keys($data);
            $placeholders = array_map(fn($col) => ":{$col}", $columns);
            
            $sql = sprintf(
                "INSERT INTO %s (%s) VALUES (%s) RETURNING *",
                $table,
                implode(', ', $columns),
                implode(', ', $placeholders)
            );
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($data);
            
            $result = $stmt->fetch();
            
            // Converti array PostgreSQL in array PHP
            if (isset($result['team_ids']) && is_string($result['team_ids'])) {
                $result['team_ids'] = $this->parsePostgresArray($result['team_ids']);
            }
            
            return $result;
        } catch (PDOException $e) {
            throw new \RuntimeException("Errore nell'inserimento in {$table}: " . $e->getMessage());
        }
    }

    public function update(string $table, int $id, array $data): ?array
    {
        try {
            $data['updated_at'] = date('Y-m-d H:i:s');
            
            // Gestione speciale per array PostgreSQL (es. team_ids)
            foreach ($data as $key => $value) {
                if (is_array($value) && $key === 'team_ids') {
                    $data[$key] = '{' . implode(',', $value) . '}';
                }
            }
            
            $set = [];
            foreach (array_keys($data) as $key) {
                $set[] = "{$key} = :{$key}";
            }
            
            $sql = sprintf(
                "UPDATE %s SET %s WHERE id = :id RETURNING *",
                $table,
                implode(', ', $set)
            );
            
            $data['id'] = $id;
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($data);
            
            $result = $stmt->fetch();
            
            // Converti array PostgreSQL in array PHP
            if ($result && isset($result['team_ids']) && is_string($result['team_ids'])) {
                $result['team_ids'] = $this->parsePostgresArray($result['team_ids']);
            }
            
            return $result ?: null;
        } catch (PDOException $e) {
            throw new \RuntimeException("Errore nell'aggiornamento in {$table}: " . $e->getMessage());
        }
    }

    public function delete(string $table, int $id): bool
    {
        try {
            $stmt = $this->pdo->prepare("DELETE FROM {$table} WHERE id = :id");
            $stmt->execute(['id' => $id]);
            return $stmt->rowCount() > 0;
        } catch (PDOException $e) {
            throw new \RuntimeException("Errore nell'eliminazione da {$table}: " . $e->getMessage());
        }
    }

    /**
     * Converte un array PostgreSQL (es. '{1,2,3}') in array PHP
     */
    private function parsePostgresArray(string $pgArray): array
    {
        // Rimuovi le parentesi graffe
        $pgArray = trim($pgArray, '{}');
        
        // Se vuoto, ritorna array vuoto
        if (empty($pgArray)) {
            return [];
        }
        
        // Split per virgola e converti in interi
        return array_map('intval', explode(',', $pgArray));
    }

    public function getPDO(): PDO
    {
        return $this->pdo;
    }
}
