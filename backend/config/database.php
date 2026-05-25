<?php
// backend/config/database.php

define('DB_HOST', '127.0.0.1');
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
            // Connect to MySQL server first (without database) to ensure database and table exist
            $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";charset=utf8mb4";
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ];
            
            $tempPdo = new PDO($dsn, DB_USER, DB_PASS, $options);
            
            // Create database if not exists
            $tempPdo->exec("CREATE DATABASE IF NOT EXISTS `" . DB_NAME . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci");
            $tempPdo->exec("USE `" . DB_NAME . "`");
            
            // Create user table if not exists
            $tableSql = "
                CREATE TABLE IF NOT EXISTS `user` (
                  `user_id` int unsigned NOT NULL AUTO_INCREMENT,
                  `username` varchar(255) NOT NULL DEFAULT '',
                  `email` varchar(255) NOT NULL DEFAULT '',
                  `member_valid` tinyint NOT NULL DEFAULT (0),
                  `date_joined` datetime NOT NULL DEFAULT (CURRENT_TIMESTAMP),
                  `ROLE` enum('member','admin','VIP','blacklisted') NOT NULL DEFAULT 'member',
                  `password_hash` varchar(255) NOT NULL,
                  PRIMARY KEY (`user_id`),
                  UNIQUE KEY `idx_email` (`email`),
                  UNIQUE KEY `idx_username` (`username`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
            ";
            $tempPdo->exec($tableSql);
            
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
