<?php

namespace App\Models;

use App\Database\DB;

class TournamentMatch extends BaseModel
{
    protected string $table = 'matches';
    
    protected array $fillable = [
        'tournament_id',
        'round',
        'match_number',
        'team1_id',
        'team2_id',
        'team1_score',
        'team2_score',
        'winner_id',
        'status', // 'pending', 'completed'
    ];
    
    protected array $rules = [
        'tournament_id' => 'required|integer',
        'round' => 'required|integer',
        'match_number' => 'required|integer',
    ];

    /**
     * Inserisce il risultato e avanza il vincitore
     */
    public function setResult(int $team1Score, int $team2Score): bool
    {
        if (!isset($this->team1_id) || !isset($this->team2_id)) {
            throw new \Exception('Both teams must be set before recording a result');
        }
        
        if ($team1Score === $team2Score) {
            throw new \Exception('Ties are not allowed in elimination tournaments');
        }
        
        // Determina il vincitore
        $winnerId = $team1Score > $team2Score ? $this->team1_id : $this->team2_id;
        
        // Aggiorna questa partita
        $this->update([
            'team1_score' => $team1Score,
            'team2_score' => $team2Score,
            'winner_id' => $winnerId,
            'status' => 'completed',
        ]);
        
        // Avanza il vincitore al round successivo
        $this->advanceWinner($winnerId);
        
        // Verifica se il torneo è completato
        $this->checkTournamentCompletion();
        
        return true;
    }

    /**
     * Avanza il vincitore al round successivo
     */
    private function advanceWinner(int $winnerId): void
    {
        $db = DB::getInstance();
        $tournament = Tournament::find($this->tournament_id);
        
        if (!$tournament) {
            return;
        }
        
        $nextRound = $this->round + 1;
        $totalRounds = $tournament->getTotalRounds();
        
        // Se questa è la finale, non c'è un round successivo
        if ($this->round >= $totalRounds) {
            // Aggiorna il torneo con il vincitore
            $tournament->update([
                'winner_id' => $winnerId,
                'status' => 'completed',
            ]);
            return;
        }
        
        // Trova la partita del round successivo dove deve andare il vincitore
        $nextMatchNumber = (int) ceil($this->match_number / 2);
        $nextMatches = $db->findWhere('matches', [
            'tournament_id' => $this->tournament_id,
            'round' => $nextRound,
            'match_number' => $nextMatchNumber,
        ]);
        
        if (empty($nextMatches)) {
            return;
        }
        
        $nextMatch = $nextMatches[0];
        
        // Determina se il vincitore va in team1 o team2
        // Se match_number è dispari, va in team1, altrimenti in team2
        $isOddMatch = $this->match_number % 2 === 1;
        $teamField = $isOddMatch ? 'team1_id' : 'team2_id';
        
        // Aggiorna la partita successiva
        $db->update('matches', $nextMatch['id'], [
            $teamField => $winnerId,
        ]);
    }

    /**
     * Verifica se il torneo è completato
     */
    private function checkTournamentCompletion(): void
    {
        $tournament = Tournament::find($this->tournament_id);
        
        if (!$tournament) {
            return;
        }
        
        $totalRounds = $tournament->getTotalRounds();
        
        // Verifica se questa era la finale
        if ($this->round === $totalRounds && $this->status === 'completed') {
            $tournament->update([
                'status' => 'completed',
                'winner_id' => $this->winner_id,
            ]);
        }
    }

    /**
     * Ottiene i dettagli del team 1
     */
    public function team1(): ?array
    {
        if (!$this->team1_id) {
            return null;
        }
        
        $team = Team::find($this->team1_id);
        return $team ? $team->toArray() : null;
    }

    /**
     * Ottiene i dettagli del team 2
     */
    public function team2(): ?array
    {
        if (!$this->team2_id) {
            return null;
        }
        
        $team = Team::find($this->team2_id);
        return $team ? $team->toArray() : null;
    }

    /**
     * Ottiene i dettagli del vincitore
     */
    public function winner(): ?array
    {
        if (!$this->winner_id) {
            return null;
        }
        
        $team = Team::find($this->winner_id);
        return $team ? $team->toArray() : null;
    }

    /**
     * Converte la partita in array con i dettagli dei team
     */
    public function toArrayWithTeams(): array
    {
        $data = $this->toArray();
        $data['team1'] = $this->team1();
        $data['team2'] = $this->team2();
        $data['winner'] = $this->winner();
        return $data;
    }
}
