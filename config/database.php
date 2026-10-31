<?php
declare(strict_types=1);

/**
 * BengalSeva - Hardened PDO Database Singleton
 * PHP 8.3 / MariaDB Compatibility Layer
 */
final class Database
{
    private static ?PDO $instance = null;

    private const DB_HOST = 'localhost';
    private const DB_NAME = 'u163000859_Searchkaro';
    private const DB_USER = 'u163000859_search';
    private const DB_PASS = 'Sadek@124';
    private const DB_PORT = 3306;

    private function __construct() {}
    private function __clone() {}

    public static function getConnection(): PDO
    {
        if (self::$instance === null) {
            $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', self::DB_HOST, self::DB_PORT, self::DB_NAME);
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci",
            ];
            try {
                self::$instance = new PDO($dsn, self::DB_USER, self::DB_PASS, $options);
            } catch (PDOException $e) {
                error_log('[BengalSeva DB Error] ' . $e->getMessage());
                die('Database connection error. Please contact BengalSeva system administration.');
            }
        }
        return self::$instance;
    }
}
