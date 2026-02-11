<?php

namespace App\Database;

class DB
{
    private static $instance = null;

    public static function getInstance()
    {
        if (self::$instance === null) {
            $config = require __DIR__ . '/../../config/database.php';
            
            $driver = $config['driver'] ?? 'json';
            
            if ($driver === 'pgsql' || $driver === 'mysql') {
                self::$instance = new PostgresDB($config);
            } else {
                // Default a JSON database
                self::$instance = new JSONDB($config['json']['path']);
            }
        }
        
        return self::$instance;
    }

    public static function table(string $table)
    {
        return self::getInstance();
    }
}
