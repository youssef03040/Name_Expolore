<?php
class Database {
    private static $instance = null;
    private $connection;

    private function __construct() {
        $dsn = 'mysql:host=localhost;dbname=name_explore;charset=utf8';
        $username = 'root';
        $password = '';

        try {
            $this->connection = new PDO($dsn, $username, $password);
        } catch (PDOException $exception) {
            if ($exception->getCode() !== 1049) {
                throw $exception;
            }

            $this->createDatabase($username, $password);
            $this->connection = new PDO($dsn, $username, $password);
        }

        $this->connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }

    private function createDatabase(string $username, string $password): void {
        $serverDsn = 'mysql:host=localhost;charset=utf8';
        $serverConnection = new PDO($serverDsn, $username, $password);
        $serverConnection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        // Ensure the target schema exists before reconnecting with the db-specific DSN.
        $serverConnection->exec("CREATE DATABASE IF NOT EXISTS name_explore CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new Database();
        }
        return self::$instance;
    }

    public function getConnection() {
        return $this->connection;
    }
}
?>