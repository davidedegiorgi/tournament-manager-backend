<?php

use Pecee\SimpleRouter\SimpleRouter as Router;
use App\Models\Tournament;
use App\Models\Team;
use App\Models\TournamentMatch;
use App\Utils\Request;
use App\Utils\Response;

/**
 * Tournaments routes
 * Gestisce tutte le operazioni sui tornei
 */

/**
 * Tournaments routes
 * Gestisce tutte le operazioni sui tornei
 */

// GET /api/tournaments/history - Storico tornei conclusi (MUST BE BEFORE /tournaments/{id})
Router::get('/tournaments/history', function() {
    $tournaments = Tournament::where(['status' => 'completed']);
    $historyArray = array_map(function ($tournament) {
        $data = $tournament->toArray();
        
        // Aggiungi il team vincitore
        if ($tournament->winner_id) {
            $winner = Team::find($tournament->winner_id);
            $data['winner'] = $winner ? $winner->toArray() : null;
        }
        
        return $data;
    }, $tournaments);
    
    Response::success($historyArray)->send();
});

// GET /api/tournaments/hall-of-fame - Statistiche squadre (MUST BE BEFORE /tournaments/{id})
Router::get('/tournaments/hall-of-fame', function() {
    try {
        $db = \App\Database\DB::getInstance();
        
        // Prendi tutti i tornei completati
        $completedTournaments = Tournament::where(['status' => 'completed']);
        
        // Conta le vittorie per ogni squadra
        $teamWins = [];
        foreach ($completedTournaments as $tournament) {
            if ($tournament->winner_id) {
                if (!isset($teamWins[$tournament->winner_id])) {
                    $teamWins[$tournament->winner_id] = 0;
                }
                $teamWins[$tournament->winner_id]++;
            }
        }
        
        // Crea l'array delle squadre con le statistiche
        $hallOfFame = [];
        foreach ($teamWins as $teamId => $wins) {
            $team = Team::find($teamId);
            if ($team) {
                $hallOfFame[] = [
                    'team' => $team->toArray(),
                    'tournaments_won' => $wins,
                ];
            }
        }
        
        // Ordina per numero di vittorie (decrescente)
        usort($hallOfFame, function($a, $b) {
            return $b['tournaments_won'] - $a['tournaments_won'];
        });
        
        Response::success($hallOfFame)->send();
    } catch (\Exception $e) {
        Response::error('Error loading hall of fame: ' . $e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR)->send();
    }
});

// GET /api/tournaments - Lista tutti i tornei
Router::get('/tournaments', function() {
    try {
        $tournaments = Tournament::all();
        $tournamentsArray = array_map(function ($tournament) {
            $data = $tournament->toArray();
            $data['teams'] = $tournament->teams();
            
            // Aggiungi il vincitore se il torneo è completato
            if ($tournament->winner_id) {
                $winner = Team::find($tournament->winner_id);
                $data['winner'] = $winner ? $winner->toArray() : null;
            }
            return $data;
        }, $tournaments);
        
        Response::success($tournamentsArray)->send();
    } catch (\Exception $e) {
        Response::error('Error loading tournaments: ' . $e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR)->send();
    }
});

// GET /api/tournaments/{id}/matches - Ottieni solo le partite di un torneo
Router::get('/tournaments/{id}/matches', function($id) {
    $tournament = Tournament::find((int)$id);
    
    if (!$tournament) {
        Response::error('Tournament not found', Response::HTTP_NOT_FOUND)->send();
    }
    
    // Restituisci solo le partite con dettagli dei team
    $matches = $tournament->matches();
    $matchesWithTeams = array_map(function ($matchData) {
        $match = new TournamentMatch($matchData);
        return $match->toArrayWithTeams();
    }, $matches);
    
    Response::success($matchesWithTeams)->send();
});

// GET /api/tournaments/{id} - Dettagli torneo con partite
Router::get('/tournaments/{id}', function($id) {
    $tournament = Tournament::find((int)$id);
    
    if (!$tournament) {
        Response::error('Tournament not found', Response::HTTP_NOT_FOUND)->send();
    }
    
    $data = $tournament->toArray();
    $data['teams'] = $tournament->teams();
    
    // Aggiungi le partite con dettagli dei team
    $matches = $tournament->matches();
    $matchesWithTeams = array_map(function ($matchData) {
        $match = new App\Models\TournamentMatch($matchData);
        return $match->toArrayWithTeams();
    }, $matches);
    
    $data['matches'] = $matchesWithTeams;
    $data['total_rounds'] = $tournament->getTotalRounds();
    
    // Aggiungi il vincitore se il torneo è completato
    if ($tournament->winner_id) {
        $winner = Team::find($tournament->winner_id);
        $data['winner'] = $winner ? $winner->toArray() : null;
    }
    
    Response::success($data)->send();
});

// POST /api/tournaments - Crea nuovo torneo
Router::post('/tournaments', function() {
    try {
        $request = new Request();
        $data = $request->all();
        
        // Validazione
        if (empty($data['name']) || empty($data['team_ids'])) {
            Response::error('Name and team_ids are required', Response::HTTP_BAD_REQUEST)->send();
        }
        
        // Converti team_ids se è una stringa
        $teamIds = $data['team_ids'];
        if (is_string($teamIds)) {
            // Se è una stringa separata da virgole, convertila in array
            $teamIds = array_map('intval', explode(',', $teamIds));
        }
        
        if (!is_array($teamIds)) {
            Response::error('team_ids must be an array or comma-separated string', Response::HTTP_BAD_REQUEST)->send();
        }
        
        $numTeams = count($teamIds);
        
        // Verifica che il numero di squadre non superi 16
        if ($numTeams > 16) {
            Response::error('Maximum 16 teams allowed per tournament', Response::HTTP_BAD_REQUEST)->send();
        }
        
        // Verifica che sia potenza di 2
        if (($numTeams & ($numTeams - 1)) !== 0 || $numTeams < 2) {
            Response::error('Number of teams must be a power of 2 (2, 4, 8, 16)', Response::HTTP_BAD_REQUEST)->send();
        }
        
        // Verifica che tutti i team esistano e non siano eliminati
        foreach ($teamIds as $teamId) {
            $team = Team::find((int)$teamId);
            if (!$team || $team->deleted_at) {
                Response::error("Team with ID {$teamId} not found or deleted", Response::HTTP_BAD_REQUEST)->send();
            }
        }
        
        // Crea torneo
        $tournament = Tournament::create([
            'name' => $data['name'],
            'date' => $data['date'] ?? date('Y-m-d'),
            'location' => $data['location'] ?? '',
            'team_ids' => $teamIds,
            'status' => 'setup'
        ]);
        
        // Genera automaticamente il bracket
        $tournament->generateBracket();
        
        // Ricarica il torneo per avere i dati aggiornati
        $tournament = Tournament::find($tournament->id);
        
        Response::success($tournament->toArray(), Response::HTTP_CREATED, 'Tournament created successfully')->send();
    } catch (\Exception $e) {
        Response::error('Error creating tournament: ' . $e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR)->send();
    }
});

// POST /api/tournaments/{id}/generate-bracket - Genera bracket
Router::post('/tournaments/{id}/generate-bracket', function($id) {
    $tournament = Tournament::find((int)$id);
    
    if (!$tournament) {
        Response::error('Tournament not found', Response::HTTP_NOT_FOUND)->send();
    }
    
    if ($tournament->status !== 'setup') {
        Response::error('Bracket can only be generated for tournaments in setup status', Response::HTTP_BAD_REQUEST)->send();
    }
    
    if (empty($tournament->team_ids)) {
        Response::error('No teams selected for this tournament', Response::HTTP_BAD_REQUEST)->send();
    }
    
    if ($tournament->generateBracket()) {
        // Ricarica il torneo con le partite
        $tournament = Tournament::find($tournament->id);
        $data = $tournament->toArray();
        $data['matches'] = $tournament->matches();
        
        Response::success($data, Response::HTTP_OK, 'Bracket generated successfully')->send();
    } else {
        Response::error('Failed to generate bracket', Response::HTTP_INTERNAL_SERVER_ERROR)->send();
    }
});

// PUT /api/tournaments/{id} - Modifica torneo
Router::put('/tournaments/{id}', function($id) {
    $tournament = Tournament::find((int)$id);
    
    if (!$tournament) {
        Response::error('Tournament not found', Response::HTTP_NOT_FOUND)->send();
    }
    
    if ($tournament->status !== 'setup') {
        Response::error('Can only modify tournaments in setup status', Response::HTTP_BAD_REQUEST)->send();
    }
    
    $request = new Request();
    $data = $request->only(['name', 'date', 'location', 'team_ids']);
    
    // Valida team_ids se presente
    if (isset($data['team_ids'])) {
        if (is_string($data['team_ids'])) {
            $data['team_ids'] = json_decode($data['team_ids'], true);
        }
        
        $numTeams = count($data['team_ids']);
        if ($numTeams < 2 || ($numTeams & ($numTeams - 1)) !== 0) {
            Response::error('Number of teams must be a power of 2 (2, 4, 8, 16, etc.)', Response::HTTP_BAD_REQUEST)->send();
        }
    }
    
    if ($tournament->update($data)) {
        Response::success($tournament->toArray(), Response::HTTP_OK, 'Tournament updated successfully')->send();
    } else {
        Response::error('Failed to update tournament', Response::HTTP_BAD_REQUEST, $tournament->getErrors())->send();
    }
});

// DELETE /api/tournaments/{id} - Elimina torneo
Router::delete('/tournaments/{id}', function($id) {
    $tournament = Tournament::find((int)$id);
    
    if (!$tournament) {
        Response::error('Tournament not found', Response::HTTP_NOT_FOUND)->send();
    }
    
    if ($tournament->status === 'completed') {
        Response::error('Cannot delete completed tournaments', Response::HTTP_BAD_REQUEST)->send();
    }
    
    if ($tournament->delete()) {
        Response::success(null, Response::HTTP_OK, 'Tournament deleted successfully')->send();
    } else {
        Response::error('Failed to delete tournament', Response::HTTP_INTERNAL_SERVER_ERROR)->send();
    }
});
