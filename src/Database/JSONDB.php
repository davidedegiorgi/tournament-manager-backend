<?php

namespace App\Database;

class JSONDB
{
    private string $filePath;
    private array $data;

    public function __construct(string $filePath)
    {
        $this->filePath = $filePath;
        $this->ensureFileExists();
        $this->load();
    }

    private function ensureFileExists(): void
    {
        $dir = dirname($this->filePath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        
        if (!file_exists($this->filePath)) {
            file_put_contents($this->filePath, json_encode([
                'teams' => [],
                'tournaments' => [],
                'matches' => [],
            ]));
        }
    }

    private function load(): void
    {
        $json = file_get_contents($this->filePath);
        $this->data = json_decode($json, true) ?? [];
    }

    private function save(): void
    {
        file_put_contents($this->filePath, json_encode($this->data, JSON_PRETTY_PRINT));
    }

    public function getTable(string $table): array
    {
        return $this->data[$table] ?? [];
    }

    public function find(string $table, int $id): ?array
    {
        $records = $this->getTable($table);
        foreach ($records as $record) {
            if ($record['id'] === $id) {
                return $record;
            }
        }
        return null;
    }

    public function findWhere(string $table, array $conditions): array
    {
        $records = $this->getTable($table);
        return array_values(array_filter($records, function ($record) use ($conditions) {
            foreach ($conditions as $key => $value) {
                if (!isset($record[$key]) || $record[$key] !== $value) {
                    return false;
                }
            }
            return true;
        }));
    }

    public function insert(string $table, array $data): array
    {
        if (!isset($this->data[$table])) {
            $this->data[$table] = [];
        }
        
        $data['id'] = $this->getNextId($table);
        $data['created_at'] = date('Y-m-d H:i:s');
        $data['updated_at'] = date('Y-m-d H:i:s');
        
        $this->data[$table][] = $data;
        $this->save();
        
        return $data;
    }

    public function update(string $table, int $id, array $data): ?array
    {
        if (!isset($this->data[$table])) {
            return null;
        }
        
        foreach ($this->data[$table] as &$record) {
            if ($record['id'] === $id) {
                $data['updated_at'] = date('Y-m-d H:i:s');
                $record = array_merge($record, $data);
                $this->save();
                return $record;
            }
        }
        
        return null;
    }

    public function delete(string $table, int $id): bool
    {
        if (!isset($this->data[$table])) {
            return false;
        }
        
        $initialCount = count($this->data[$table]);
        $this->data[$table] = array_values(array_filter($this->data[$table], function ($record) use ($id) {
            return $record['id'] !== $id;
        }));
        
        if (count($this->data[$table]) < $initialCount) {
            $this->save();
            return true;
        }
        
        return false;
    }

    private function getNextId(string $table): int
    {
        if (empty($this->data[$table])) {
            return 1;
        }
        
        $maxId = max(array_column($this->data[$table], 'id'));
        return $maxId + 1;
    }
}
