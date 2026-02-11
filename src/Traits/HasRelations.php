<?php

namespace App\Traits;

use App\Database\DB;

trait HasRelations
{
    public function hasMany(string $relatedTable, string $foreignKey): array
    {
        $db = DB::getInstance();
        return $db->findWhere($relatedTable, [$foreignKey => $this->attributes['id']]);
    }

    public function belongsTo(string $relatedTable, string $foreignKey): ?array
    {
        $db = DB::getInstance();
        $foreignId = $this->attributes[$foreignKey] ?? null;
        
        if (!$foreignId) {
            return null;
        }
        
        return $db->find($relatedTable, $foreignId);
    }

    public function belongsToMany(string $relatedTable, string $pivotTable, string $foreignKey, string $relatedKey): array
    {
        $db = DB::getInstance();
        $pivotRecords = $db->findWhere($pivotTable, [$foreignKey => $this->attributes['id']]);
        
        $relatedIds = array_column($pivotRecords, $relatedKey);
        $allRecords = $db->getTable($relatedTable);
        
        return array_values(array_filter($allRecords, function ($record) use ($relatedIds) {
            return in_array($record['id'], $relatedIds);
        }));
    }
}
