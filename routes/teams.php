<?php

use Pecee\SimpleRouter\SimpleRouter as Router;
use App\Models\Team;
use App\Utils\Request;
use App\Utils\Response;

/**
 * Teams routes
 * Gestisce tutte le operazioni CRUD per le squadre
 */

// GET /api/teams - Lista tutte le squadre attive
Router::get('/teams', function() {
    $teams = Team::active();
    $teamsArray = array_map(fn($team) => $team->toArray(), $teams);
    Response::success($teamsArray)->send();
});

// GET /api/teams/{id} - Dettagli squadra
Router::get('/teams/{id}', function($id) {
    $team = Team::find((int)$id);
    
    if (!$team) {
        Response::error('Squadra non trovata', Response::HTTP_NOT_FOUND)->send();
    }
    
    if ($team->deleted_at) {
        Response::error('Squadra eliminata', Response::HTTP_NOT_FOUND)->send();
    }
    
    Response::success($team->toArray())->send();
});

// POST /api/teams - Crea nuova squadra
Router::post('/teams', function() {
    try {
        $request = new Request();
        $data = $request->only(['name', 'logo']);
        $team = Team::create($data);
    Response::success($team->toArray(), Response::HTTP_CREATED, 'La squadra è stata creata con successo')->send();
    } catch (\Exception $e) {
        Response::error($e->getMessage(), Response::HTTP_BAD_REQUEST)->send();
    }
});

// PUT /api/teams/{id} - Modifica squadra
Router::put('/teams/{id}', function($id) {
    $team = Team::find((int)$id);
    
    if (!$team) {
        Response::error('Squadra non trovata', Response::HTTP_NOT_FOUND)->send();
    }
    
    if ($team->deleted_at) {
        Response::error('Impossibile modificare una squadra eliminata', Response::HTTP_BAD_REQUEST)->send();
    }
    
    $request = new Request();
    $data = $request->only(['name', 'logo']);
    
    if ($team->update($data)) {
        Response::success($team->toArray(), Response::HTTP_OK, 'Squadra aggiornata con successo')->send();
    } else {
        Response::error('Errore durante l\'aggiornamento della squadra', Response::HTTP_BAD_REQUEST, $team->getErrors())->send();
    }
});

// DELETE /api/teams/{id} - Elimina squadra
Router::delete('/teams/{id}', function($id) {
    $team = Team::find((int)$id);
    
    if (!$team) {
        Response::error('Squadra non trovata', Response::HTTP_NOT_FOUND)->send();
    }
    
    if ($team->deleted_at) {
        Response::error('Squadra già eliminata', Response::HTTP_BAD_REQUEST)->send();
    }
    
    // Verifica se può essere eliminato
    if (!$team->canBeDeleted()) {
        Response::error(
            'Impossibile eliminare una squadra che ha partecipato a tornei completati. La squadra è stata eliminata in modalità soft.',
            Response::HTTP_BAD_REQUEST
        )->send();
    }
    
    // Soft delete
    if ($team->softDelete()) {
        Response::success(null, Response::HTTP_OK, 'Squadra eliminata con successo')->send();
    } else {
        Response::error('Errore durante l\'eliminazione della squadra', Response::HTTP_INTERNAL_SERVER_ERROR)->send();
    }
});
