/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET NAMES utf8 */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

CREATE TABLE IF NOT EXISTS `challenges` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `token` binary(16) NOT NULL,
  `address` varbinary(16) NOT NULL,
  `created_at` int(10) unsigned NOT NULL DEFAULT unix_timestamp(),
  `used_by` varchar(255) DEFAULT NULL,
  `used_at` int(10) unsigned DEFAULT NULL,
  `reject_reason` varchar(255) DEFAULT NULL,
  `expire_at` int(10) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `token` (`token`),
  FULLTEXT KEY `solved_by` (`used_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `challenges_hr` (
	`id` BIGINT(20) UNSIGNED NOT NULL,
	`address` VARCHAR(39) NULL COLLATE 'utf8mb4_general_ci',
	`token` VARCHAR(32) NOT NULL COLLATE 'utf8mb4_general_ci',
	`created_at` DATETIME NULL,
	`used_by` VARCHAR(255) NULL COLLATE 'utf8mb4_general_ci',
	`used_at` DATETIME NULL,
	`reject_reason` VARCHAR(255) NULL COLLATE 'utf8mb4_general_ci',
	`expire_at` DATETIME NULL
) ENGINE=MyISAM;

DROP TABLE IF EXISTS `challenges_hr`;
CREATE ALGORITHM=UNDEFINED SQL SECURITY DEFINER VIEW `challenges_hr` AS select `challenges`.`id` AS `id`,inet6_ntoa(`challenges`.`address`) AS `address`,hex(`challenges`.`token`) AS `token`,from_unixtime(`challenges`.`created_at`) AS `created_at`,`challenges`.`used_by` AS `used_by`,from_unixtime(`challenges`.`used_at`) AS `used_at`,`challenges`.`reject_reason` AS `reject_reason`,from_unixtime(`challenges`.`expire_at`) AS `expire_at` from `challenges` order by `challenges`.`id` desc limit 500;

/*!40103 SET TIME_ZONE=IFNULL(@OLD_TIME_ZONE, 'system') */;
/*!40101 SET SQL_MODE=IFNULL(@OLD_SQL_MODE, '') */;
/*!40014 SET FOREIGN_KEY_CHECKS=IFNULL(@OLD_FOREIGN_KEY_CHECKS, 1) */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40111 SET SQL_NOTES=IFNULL(@OLD_SQL_NOTES, 1) */;
