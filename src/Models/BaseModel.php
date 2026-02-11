<?php

namespace App\Models;

use App\Database\DB;
use App\Traits\HasRelations;
use App\Traits\WithValidate;

abstract class BaseModel
{
    use WithValidate, HasRelations;

    protected string $table;
    protected array $attributes = [];
    protected array $fillable = [];

    public function __construct(array $attributes = [])
    {
        $this->attributes = $attributes;
    }

    public static function all(): array
    {
        $instance = new static();
        $db = DB::getInstance();
        $records = $db->getTable($instance->table);
        
        return array_map(fn($record) => new static($record), $records);
    }

    public static function find(int $id): ?static
    {
        $instance = new static();
        $db = DB::getInstance();
        $record = $db->find($instance->table, $id);
        
        return $record ? new static($record) : null;
    }

    public static function where(array $conditions): array
    {
        $instance = new static();
        $db = DB::getInstance();
        $records = $db->findWhere($instance->table, $conditions);
        
        return array_map(fn($record) => new static($record), $records);
    }

    public static function create(array $data): static
    {
        $instance = new static();
        
        if (!$instance->validate($data)) {
            throw new \Exception('Validation failed: ' . json_encode($instance->getErrors()));
        }
        
        $fillableData = array_intersect_key($data, array_flip($instance->fillable));
        $db = DB::getInstance();
        $record = $db->insert($instance->table, $fillableData);
        
        return new static($record);
    }

    public function update(array $data): bool
    {
        // Valida solo se ci sono regole E se i dati contengono almeno un campo obbligatorio
        // Per update parziali (come aggiornare solo lo score) non validare
        $hasRequiredFields = false;
        if (!empty($this->rules)) {
            foreach ($this->rules as $field => $rule) {
                if (isset($data[$field]) && str_contains($rule, 'required')) {
                    $hasRequiredFields = true;
                    break;
                }
            }
            
            if ($hasRequiredFields && !$this->validate($data)) {
                return false;
            }
        }
        
        $fillableData = array_intersect_key($data, array_flip($this->fillable));
        $db = DB::getInstance();
        $updated = $db->update($this->table, $this->attributes['id'], $fillableData);
        
        if ($updated) {
            $this->attributes = $updated;
            return true;
        }
        
        return false;
    return false;
    }

    public function delete(): bool
    {
        $db = DB::getInstance();
        return $db->delete($this->table, $this->attributes['id']);
    }

    public function toArray(): array
    {
        return $this->attributes;
    }

    public function __get(string $name)
    {
        return $this->attributes[$name] ?? null;
    }

    public function __set(string $name, $value): void
    {
        $this->attributes[$name] = $value;
    }
}
