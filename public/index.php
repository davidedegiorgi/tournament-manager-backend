<?php
/**
 * Punto di ingresso dell'applicazione
 * Questo file carica il bootstrap che gestisce automaticamente il routing
 */
$path = __DIR__ . parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if (is_file($path)) {
    // Add CORS headers for static files
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, OPTIONS');
    header('Access-Control-Allow-Headers: *');
    
    // Determine MIME type
    $extension = pathinfo($path, PATHINFO_EXTENSION);
    $mimeTypes = [
        'png' => 'image/png',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'gif' => 'image/gif',
        'svg' => 'image/svg+xml',
        'css' => 'text/css',
        'js' => 'application/javascript',
        'json' => 'application/json',
    ];
    
    $mimeType = $mimeTypes[$extension] ?? 'application/octet-stream';
    header('Content-Type: ' . $mimeType);
    
    // Serve the file
    readfile($path);
    exit();
}

require __DIR__ . '/../src/bootstrap.php';
