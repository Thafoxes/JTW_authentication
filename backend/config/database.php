<?php
// backend/config/database.php
// Configure the database information here 
define('DB_HOST', 'localhost');
define('DB_PORT', '3306');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'jwt_dbms');
define('JWT_SECRET', 'JTW_test');

class Database {
    private static $pdo = null;

    public static function getConnection() {
        if (self::$pdo !== null) {
            return self::$pdo;
        }

        try {
            // Connect directly to the database
            $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=utf8mb4";
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ];
            
            $tempPdo = new PDO($dsn, DB_USER, DB_PASS, $options);
            
            self::$pdo = $tempPdo;
            return self::$pdo;
            
        } catch (PDOException $e) {
            // Return JSON response if database connection fails
            header('Content-Type: application/json; charset=utf-8');
            http_response_code(500);
            echo json_encode([
                "success" => false,
                "message" => "Database connection failed: " . $e->getMessage()
            ]);
            exit();
        }
    }
}
