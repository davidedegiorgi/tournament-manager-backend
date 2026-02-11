<?php

namespace App\Models;

use App\Database\DB;

class Team extends BaseModel
{
    protected string $table = 'teams';
    
    protected array $fillable = [
        'name',
        'logo',
        'deleted_at',
    ];
    
    protected array $rules = [
        'name' => 'required|min:2|max:100',
    ];

    /**
     * Verifica se il team può essere eliminato (non ha partecipato a tornei passati)
     */
    public function canBeDeleted(): bool
    {
        $db = DB::getInstance();
        $tournaments = $db->getTable('tournaments');
        
        foreach ($tournaments as $tournament) {
            if (isset($tournament['status']) && $tournament['status'] === 'completed') {
                // Verifica se il team ha partecipato a questo torneo
                if (isset($tournament['team_ids']) && in_array($this->id, $tournament['team_ids'])) {
                    return false;
                }
            }
        }
        
        return true;
    }

    /**
     * Soft delete - marca il team come eliminato
     */
    public function softDelete(): bool
    {
        $db = DB::getInstance();
        $updated = $db->update($this->table, $this->attributes['id'], [
            'deleted_at' => date('Y-m-d H:i:s')
        ]);
        
        if ($updated) {
            $this->attributes = $updated;
            return true;
        }
        
        return false;
    }

    /**
     * Recupera solo i team attivi (non eliminati)
     */
    public static function active(): array
    {
        $all = self::all();
        return array_values(array_filter($all, fn($team) => $team->deleted_at === null || $team->deleted_at === ''));
    }

    /**
     * Ottiene i tornei a cui ha partecipato il team
     */
    public function tournaments(): array
    {
        $db = DB::getInstance();
        $tournaments = $db->getTable('tournaments');
        
        return array_values(array_filter($tournaments, function ($tournament) {
            return isset($tournament['team_ids']) && in_array($this->id, $tournament['team_ids']);
        }));
    }
}
