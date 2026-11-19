<?php

namespace App\Core;

use PDO;
use PDOException;

/**
 * Connexion PDO unique (singleton) à MySQL.
 */
class Database
{
    private static ?PDO $instance = null;

    public static function getInstance(): PDO
    {
        if (self::$instance === null) {
            $config = require __DIR__ . '/../../config/config.php';
            $db = $config['db'];
            $dsn = "mysql:host={$db['host']};port={$db['port']};dbname={$db['name']};charset={$db['charset']}";
            try {
                self::$instance = new PDO($dsn, $db['user'], $db['pass'], [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE  => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES    => false,
                ]);
            } catch (PDOException $e) {
                http_response_code(500);
                die('Erreur de connexion à la base de données. Vérifiez config/config.php. (' . $e->getMessage() . ')');
            }
        }
        return self::$instance;
    }
}
