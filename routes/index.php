<?php
use Pecee\SimpleRouter\SimpleRouter as Router;
use App\Utils\Response;

/**
 * File principale delle route
 * Carica tutte le route dai file separati per ogni risorsa
 */

// Route di benvenuto
Router::get('/', function() {
    Response::success([
        'name' => 'Tournament Manager API',
        'version' => '1.0.0',
        'endpoints' => [
            'GET /api/teams' => 'List all teams',
            'POST /api/teams' => 'Create team',
            'GET /api/teams/{id}' => 'Get team details',
            'PUT /api/teams/{id}' => 'Update team',
            'DELETE /api/teams/{id}' => 'Delete team',
            'GET /api/tournaments' => 'List all tournaments',
            'POST /api/tournaments' => 'Create tournament',
            'GET /api/tournaments/{id}' => 'Get tournament details',
            'PUT /api/tournaments/{id}' => 'Update tournament',
            'DELETE /api/tournaments/{id}' => 'Delete tournament',
            'POST /api/tournaments/{id}/generate-bracket' => 'Generate tournament bracket',
            'GET /api/tournaments/{id}/matches' => 'Get tournament matches',
            'GET /api/tournaments/history' => 'Get completed tournaments',
            'GET /api/tournaments/hall-of-fame' => 'Get teams statistics',
            'GET /api/matches/{id}' => 'Get match details',
            'PUT /api/matches/{id}' => 'Record match result',
            'POST /api/matches/{id}' => 'Record match result (alternative)',
        ],
    ])->send();
});

// Route group per API
Router::group(['prefix' => '/api'], function() {
    // Carica automaticamente tutte le route dalla directory routes/
    // Esclude index.php per evitare loop infiniti
    $routeFiles = glob(__DIR__ . '/*.php');
    foreach ($routeFiles as $file) {
        $filename = basename($file);
        // Carica tutti i file PHP tranne index.php
        if ($filename !== 'index.php') {
            require $file;
        }
    }
});

// Gestione errori 404
Router::error(function() {
    Response::error('Endpoint not found', Response::HTTP_NOT_FOUND)->send();
});
