<?php

use Pecee\SimpleRouter\SimpleRouter as Router;
use App\Models\TournamentMatch;
use App\Models\Tournament;
use App\Utils\Request;
use App\Utils\Response;

/**
 * Matches routes
 * Gestisce le operazioni sulle partite dei tornei
 */

// GET /api/matches/{id} - Dettagli partita
Router::get('/matches/{id}', function($id) {
    $match = TournamentMatch::find((int)$id);
    
    if (!$match) {
        Response::error('Match not found', Response::HTTP_NOT_FOUND)->send();
    }
    
    Response::success($match->toArrayWithTeams())->send();
});

// PUT /api/matches/{id} - Inserisci risultato
Router::put('/matches/{id}', function($id) {
    $request = new Request();
    $match = TournamentMatch::find((int)$id);
    
    if (!$match) {
        Response::error('Match not found', Response::HTTP_NOT_FOUND)->send();
    }
    
    if ($match->status === 'completed') {
        Response::error('Match is already completed', Response::HTTP_BAD_REQUEST)->send();
    }
    
    $team1Id = $match->team1_id;
    $team2Id = $match->team2_id;
    
    if (!$team1Id || !$team2Id) {
        Response::error('Both teams must be set before recording a result', Response::HTTP_BAD_REQUEST)->send();
    }
    
    $team1Score = $request->get('team1_score');
    $team2Score = $request->get('team2_score');
    
    // Debug per vedere cosa arriva dal frontend
    error_log("DEBUG PUT - team1_score ricevuto: " . var_export($team1Score, true));
    error_log("DEBUG PUT - team2_score ricevuto: " . var_export($team2Score, true));
    
    // Se arriva stringa vuota, convertila a "0"
    if ($team1Score === '') $team1Score = '0';
    if ($team2Score === '') $team2Score = '0';
    
    // Verifica che i valori siano stati forniti
    if ($team1Score === null || $team2Score === null) {
        error_log("DEBUG PUT - Uno o entrambi i valori sono null");
        Response::error('Both team scores are required', Response::HTTP_BAD_REQUEST)->send();
    }
    
    // Controlla che siano numerici
    if (!is_numeric($team1Score) || !is_numeric($team2Score)) {
        error_log("DEBUG PUT - Uno o entrambi i valori non sono numerici");
        Response::error('Scores must be numeric', Response::HTTP_BAD_REQUEST)->send();
    }
    
    // Converti a intero
    $team1Score = (int)$team1Score;
    $team2Score = (int)$team2Score;
    
    if ($team1Score < 0 || $team2Score < 0) {
        Response::error('Scores cannot be negative', Response::HTTP_BAD_REQUEST)->send();
    }
    
    try {
        $match->setResult($team1Score, $team2Score);
        
        // Ricarica il match con i nuovi dati
        $match = TournamentMatch::find($match->id);
        $data = $match->toArrayWithTeams();
        
        // Verifica se il torneo è completato
        $tournament = Tournament::find($match->tournament_id);
        if ($tournament && $tournament->status === 'completed') {
            $data['tournament_completed'] = true;
            $data['tournament_winner_id'] = $tournament->winner_id;
        }
        
        Response::success($data, Response::HTTP_OK, 'Match result recorded successfully')->send();
    } catch (\Exception $e) {
        Response::error($e->getMessage(), Response::HTTP_BAD_REQUEST)->send();
    }
});

// POST /api/matches/{id} - Inserisci risultato (metodo alternativo)
Router::post('/matches/{id}', function($id) {
    $request = new Request();
    $match = TournamentMatch::find((int)$id);
    
    if (!$match) {
        Response::error('Match not found', Response::HTTP_NOT_FOUND)->send();
    }
    
    if ($match->status === 'completed') {
        Response::error('Match is already completed', Response::HTTP_BAD_REQUEST)->send();
    }
    
    $team1Id = $match->team1_id;
    $team2Id = $match->team2_id;
    
    if (!$team1Id || !$team2Id) {
        Response::error('Both teams must be set before recording a result', Response::HTTP_BAD_REQUEST)->send();
    }
    
    $team1Score = $request->get('team1_score');
    $team2Score = $request->get('team2_score');
    
    // Debug per vedere cosa arriva dal frontend
    error_log("DEBUG POST - team1_score ricevuto: " . var_export($team1Score, true));
    error_log("DEBUG POST - team2_score ricevuto: " . var_export($team2Score, true));
    
    // Se arriva stringa vuota, convertila a "0"
    if ($team1Score === '') $team1Score = '0';
    if ($team2Score === '') $team2Score = '0';
    
    // Verifica che i valori siano stati forniti
    if ($team1Score === null || $team2Score === null) {
        error_log("DEBUG POST - Uno o entrambi i valori sono null");
        Response::error('Both team scores are required', Response::HTTP_BAD_REQUEST)->send();
    }
    
    // Controlla che siano numerici
    if (!is_numeric($team1Score) || !is_numeric($team2Score)) {
        error_log("DEBUG POST - Uno o entrambi i valori non sono numerici");
        Response::error('Scores must be numeric', Response::HTTP_BAD_REQUEST)->send();
    }
    
    // Converti a intero
    $team1Score = (int)$team1Score;
    $team2Score = (int)$team2Score;
    
    if ($team1Score < 0 || $team2Score < 0) {
        Response::error('Scores cannot be negative', Response::HTTP_BAD_REQUEST)->send();
    }
    
    try {
        $match->setResult($team1Score, $team2Score);
        
        // Ricarica il match con i nuovi dati
        $match = TournamentMatch::find($match->id);
        $data = $match->toArrayWithTeams();
        
        // Verifica se il torneo è completato
        $tournament = Tournament::find($match->tournament_id);
        if ($tournament && $tournament->status === 'completed') {
            $data['tournament_completed'] = true;
            $data['tournament_winner_id'] = $tournament->winner_id;
        }
        
        Response::success($data, Response::HTTP_OK, 'Match result recorded successfully')->send();
    } catch (\Exception $e) {
        Response::error($e->getMessage(), Response::HTTP_BAD_REQUEST)->send();
    }
});
