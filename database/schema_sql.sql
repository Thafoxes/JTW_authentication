-- --------------------------------------------------------
-- Host:                         127.0.0.1
-- Server version:               8.4.3 - MySQL Community Server - GPL
-- Server OS:                    Win64
-- HeidiSQL Version:             12.8.0.6908
-- --------------------------------------------------------

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET NAMES utf8 */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;


-- Dumping database structure for jwt_dbms
CREATE DATABASE IF NOT EXISTS `jwt_dbms` /*!40100 DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci */ /*!80016 DEFAULT ENCRYPTION='N' */;
USE `jwt_dbms`;

-- Dumping structure for table jwt_dbms.check_ins
CREATE TABLE IF NOT EXISTS `check_ins` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int unsigned NOT NULL,
  `check_in_time` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `check_out_time` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `check_ins_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Dumping data for table jwt_dbms.check_ins: ~0 rows (approximately)

-- Dumping structure for procedure jwt_dbms.update_user
DELIMITER //
CREATE PROCEDURE `update_user`(
	IN `username` VARCHAR(255),
	IN `updated_email` VARCHAR(255),
	IN `member_valid_updated` TINYINT,
	IN `member_updated_role` ENUM('member','admin','VIP','blacklisted'),
	IN `user_id` INT
)
    DETERMINISTIC
BEGIN
UPDATE `user` SET `username` = username, email = updated_email, member_valid = member_valid_updated, `role` = member_updated_role WHERE `user_id` = user_id ;
END//
DELIMITER ;

-- Dumping structure for table jwt_dbms.users
CREATE TABLE IF NOT EXISTS `users` (
  `user_id` int unsigned NOT NULL AUTO_INCREMENT,
  `username` varchar(255) NOT NULL DEFAULT '',
  `email` varchar(255) NOT NULL DEFAULT '',
  `member_valid` tinyint NOT NULL DEFAULT (0),
  `date_joined` datetime NOT NULL DEFAULT (curdate()),
  `role` enum('member','admin','VIP','blacklisted') CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL DEFAULT 'member',
  `password_hash` varchar(255) NOT NULL,
  `gym_status` enum('OUTSIDE','INSIDE') DEFAULT 'OUTSIDE',
  `active_jti` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Dumping data for table jwt_dbms.users: ~2 rows (approximately)
REPLACE INTO `users` (`user_id`, `username`, `email`, `member_valid`, `date_joined`, `role`, `password_hash`, `gym_status`, `active_jti`) VALUES
	(1, 'developer', 'developer@example.com', 1, '2026-05-25 00:00:00', 'admin', '$2y$10$I6bS5SgcBWeLdMGc1HRBjeCyS7Y6EoJy7E3fpMnw7g6ye81DR0l6m', 'OUTSIDE', NULL),
	(2, 'user', 'user@example.com', 1, '2026-05-26 00:00:00', 'member', '$2y$10$52.OjL/Ewa1.Xac5zDP.4eaa0/WA6z0nBpppuh77IlIhHDw0BuyLO', 'OUTSIDE', NULL);

/*!40103 SET TIME_ZONE=IFNULL(@OLD_TIME_ZONE, 'system') */;
/*!40101 SET SQL_MODE=IFNULL(@OLD_SQL_MODE, '') */;
/*!40014 SET FOREIGN_KEY_CHECKS=IFNULL(@OLD_FOREIGN_KEY_CHECKS, 1) */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40111 SET SQL_NOTES=IFNULL(@OLD_SQL_NOTES, 1) */;
