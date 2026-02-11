<?php

namespace App\Models;

use App\Database\DB;

class Tournament extends BaseModel
{
    protected string $table = 'tournaments';
    
    protected array $fillable = [
        'name',
        'date',
        'location',
        'team_ids',
        'status', // 'setup', 'in_progress', 'completed'
        'winner_id',
    ];
    
    protected array $rules = [
        'name' => 'required|min:3|max:100',
        'date' => 'required',
        'location' => 'required|min:2|max:200',
        'team_ids' => 'array',
    ];

    /**
     * Genera il bracket del torneo ad eliminazione diretta
     */
    public function generateBracket(): bool
    {
        $teamIds = $this->team_ids;
        
        if (empty($teamIds) || !is_array($teamIds)) {
            return false;
        }
        
        $numTeams = count($teamIds);
        
        // Verifica che sia una potenza di 2
        if (!$this->isPowerOfTwo($numTeams)) {
            return false;
        }
        
        // Mischia i team casualmente
        shuffle($teamIds);
        
        // Crea le partite del primo round
        $db = DB::getInstance();
        $round = 1;
        $totalRounds = log($numTeams, 2);
        
        for ($i = 0; $i < $numTeams; $i += 2) {
            $matchData = [
                'tournament_id' => $this->id,
                'round' => $round,
                'match_number' => ($i / 2) + 1,
                'team1_id' => $teamIds[$i],
                'team2_id' => $teamIds[$i + 1],
                'status' => 'pending', // 'pending', 'completed'
            ];
            
            $db->insert('matches', $matchData);
        }
        
        // Crea le partite vuote per i round successivi
        for ($r = 2; $r <= $totalRounds; $r++) {
            $matchesInRound = pow(2, $totalRounds - $r);
            for ($m = 1; $m <= $matchesInRound; $m++) {
                $matchData = [
                    'tournament_id' => $this->id,
                    'round' => $r,
                    'match_number' => $m,
                    'status' => 'pending',
                ];
                
                $db->insert('matches', $matchData);
            }
        }
        
        // Aggiorna lo stato del torneo
        $this->update(['status' => 'in_progress']);
        
        return true;
    }

    /**
     * Ottiene tutte le partite del torneo
     */
    public function matches(): array
    {
        $db = DB::getInstance();
        $matches = $db->findWhere('matches', ['tournament_id' => $this->id]);
        
        // Ordina per round e match_number
        usort($matches, function ($a, $b) {
            if ($a['round'] === $b['round']) {
                return $a['match_number'] - $b['match_number'];
            }
            return $a['round'] - $b['round'];
        });
        
        return $matches;
    }

    /**
     * Ottiene le partite di un round specifico
     */
    public function matchesByRound(int $round): array
    {
        $matches = $this->matches();
        return array_values(array_filter($matches, fn($m) => $m['round'] === $round));
    }

    /**
     * Ottiene i team partecipanti
     */
    public function teams(): array
    {
        // Assicurati che team_ids sia un array
        $teamIds = $this->team_ids;
        if (empty($teamIds) || !is_array($teamIds)) {
            return [];
        }
        
        $db = DB::getInstance();
        $allTeams = $db->getTable('teams');
        
        return array_values(array_filter($allTeams, function ($team) use ($teamIds) {
            return in_array($team['id'], $teamIds);
        }));
    }

    /**
     * Verifica se un numero è potenza di 2
     */
    private function isPowerOfTwo(int $n): bool
    {
        return $n > 0 && ($n & ($n - 1)) === 0;
    }

    /**
     * Ottiene il numero totale di round
     */
    public function getTotalRounds(): int
    {
        
        if (!is_array($this->team_ids) || count($this->team_ids) === 0) {
            return 0;
        }
        
        $count = count($this->team_ids);
        $rounds = (int) log($count, 2);
        
        
        return $rounds;
    }

    /**
     * Ottiene il nome del round
     */
    public static function getRoundName(int $round, int $totalRounds): string
    {
        $remaining = $totalRounds - $round + 1;
        
        return match ($remaining) {
            1 => 'Finale',
            2 => 'Semifinali',
            3 => 'Quarti di Finale',
            4 => 'Ottavi di Finale',
            default => "Round $round",
        };
    }
}
