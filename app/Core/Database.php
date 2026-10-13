<?php
declare(strict_types=1);

namespace App\Core;

use PDO;
use PDOException;

final class Database
{
    private static ?PDO $connection = null;

    public static function connection(): PDO
    {
        if (self::$connection) return self::$connection;
        $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', Env::get('DB_HOST', '127.0.0.1'), Env::get('DB_PORT', '3306'), Env::get('DB_DATABASE', 'traditional_news'));
        try {
            self::$connection = new PDO($dsn, Env::get('DB_USERNAME', 'root'), Env::get('DB_PASSWORD', ''), [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_STRINGIFY_FETCHES => false,
            ]);
            // Publication dates and campaign schedules use the configured app timezone.
            $offset=(new \DateTimeImmutable('now'))->format('P');
            self::$connection->exec('SET time_zone = '.self::$connection->quote($offset));
        } catch (PDOException $e) {
            error_log($e->getMessage());
            throw new \RuntimeException('The news service is temporarily unavailable.');
        }
        return self::$connection;
    }
}
