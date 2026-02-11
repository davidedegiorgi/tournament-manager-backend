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
        Response::error('Team not found', Response::HTTP_NOT_FOUND)->send();
    }
    
    if ($team->deleted_at) {
        Response::error('Team has been deleted', Response::HTTP_NOT_FOUND)->send();
    }
    
    Response::success($team->toArray())->send();
});

// POST /api/teams - Crea nuova squadra
Router::post('/teams', function() {
    try {
        $request = new Request();
        $data = $request->only(['name', 'logo']);
        $team = Team::create($data);
        Response::success($team->toArray(), Response::HTTP_CREATED, 'Team created successfully')->send();
    } catch (\Exception $e) {
        Response::error($e->getMessage(), Response::HTTP_BAD_REQUEST)->send();
    }
});

// PUT /api/teams/{id} - Modifica squadra
Router::put('/teams/{id}', function($id) {
    $team = Team::find((int)$id);
    
    if (!$team) {
        Response::error('Team not found', Response::HTTP_NOT_FOUND)->send();
    }
    
    if ($team->deleted_at) {
        Response::error('Cannot update deleted team', Response::HTTP_BAD_REQUEST)->send();
    }
    
    $request = new Request();
    $data = $request->only(['name', 'logo']);
    
    if ($team->update($data)) {
        Response::success($team->toArray(), Response::HTTP_OK, 'Team updated successfully')->send();
    } else {
        Response::error('Failed to update team', Response::HTTP_BAD_REQUEST, $team->getErrors())->send();
    }
});

// DELETE /api/teams/{id} - Elimina squadra
Router::delete('/teams/{id}', function($id) {
    $team = Team::find((int)$id);
    
    if (!$team) {
        Response::error('Team not found', Response::HTTP_NOT_FOUND)->send();
    }
    
    if ($team->deleted_at) {
        Response::error('Team already deleted', Response::HTTP_BAD_REQUEST)->send();
    }
    
    // Verifica se può essere eliminato
    if (!$team->canBeDeleted()) {
        Response::error(
            'Cannot delete team that participated in completed tournaments. The team has been soft deleted instead.',
            Response::HTTP_BAD_REQUEST
        )->send();
    }
    
    // Soft delete
    if ($team->softDelete()) {
        Response::success(null, Response::HTTP_OK, 'Team deleted successfully')->send();
    } else {
        Response::error('Failed to delete team', Response::HTTP_INTERNAL_SERVER_ERROR)->send();
    }
});
