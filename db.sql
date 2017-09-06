CREATE DATABASE  IF NOT EXISTS `mail_tracker` /*!40100 DEFAULT CHARACTER SET utf8 */;
USE `mail_tracker`;
-- MySQL dump 10.13  Distrib 5.7.17, for macos10.12 (x86_64)
--
-- Host: 127.0.0.1    Database: mail_tracker
-- ------------------------------------------------------
-- Server version	5.7.19

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `tokens`
--

DROP TABLE IF EXISTS `tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tokens` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `created` timestamp NULL DEFAULT NULL,
  `group_id` int(11) DEFAULT NULL,
  `meta` json,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tokens`
--

LOCK TABLES `tokens` WRITE;
/*!40000 ALTER TABLE `tokens` DISABLE KEYS */;
/*!40000 ALTER TABLE `tokens` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `token_summaries`
--

DROP TABLE IF EXISTS `token_summaries`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `token_summaries` (
  `token_id` int(11) DEFAULT NULL,
  `first_open` int(11) DEFAULT NULL,
  `last_open` int(11) DEFAULT NULL,
  `total_opens` int(11) DEFAULT NULL,
  `unique_opens` int(11) DEFAULT NULL,
  UNIQUE KEY `token_id_UNIQUE` (`token_id`),
  KEY `token_summary_first_open_idx` (`first_open`),
  KEY `token_summary_last_open_idx` (`last_open`),
  CONSTRAINT `token_summary_first_open` FOREIGN KEY (`first_open`) REFERENCES `tracking_events` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  CONSTRAINT `token_summary_last_open` FOREIGN KEY (`last_open`) REFERENCES `tracking_events` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  CONSTRAINT `token_summary_token_id` FOREIGN KEY (`token_id`) REFERENCES `tokens` (`id`) ON DELETE CASCADE ON UPDATE NO ACTION
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `token_summaries`
--

LOCK TABLES `token_summaries` WRITE;
/*!40000 ALTER TABLE `token_summaries` DISABLE KEYS */;
/*!40000 ALTER TABLE `token_summaries` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tracking_event_summaries`
--

DROP TABLE IF EXISTS `tracking_event_summaries`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tracking_event_summaries` (
  `token_id` int(11) NOT NULL,
  `unique_user_string` varchar(32) NOT NULL,
  `bucket_start_time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `count` int(11) DEFAULT NULL,
  UNIQUE KEY `summary_unique_idx` (`bucket_start_time`,`token_id`,`unique_user_string`),
  KEY `summary_token_id_idx` (`token_id`),
  CONSTRAINT `summary_token_id` FOREIGN KEY (`token_id`) REFERENCES `tokens` (`id`) ON DELETE CASCADE ON UPDATE NO ACTION
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tracking_event_summaries`
--

LOCK TABLES `tracking_event_summaries` WRITE;
/*!40000 ALTER TABLE `tracking_event_summaries` DISABLE KEYS */;
/*!40000 ALTER TABLE `tracking_event_summaries` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tracking_events`
--

DROP TABLE IF EXISTS `tracking_events`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tracking_events` (
  `id` int(11) NOT NULL,
  `token_id` int(11) DEFAULT NULL,
  `ip` varchar(15) DEFAULT NULL,
  `user_agent` text,
  `time` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `token_id_idx` (`token_id`),
  CONSTRAINT `token_id` FOREIGN KEY (`token_id`) REFERENCES `tokens` (`id`) ON DELETE CASCADE ON UPDATE NO ACTION
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tracking_events`
--

LOCK TABLES `tracking_events` WRITE;
/*!40000 ALTER TABLE `tracking_events` DISABLE KEYS */;
/*!40000 ALTER TABLE `tracking_events` ENABLE KEYS */;
UNLOCK TABLES;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8 */ ;
/*!50003 SET character_set_results = utf8 */ ;
/*!50003 SET collation_connection  = utf8_general_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_AUTO_CREATE_USER,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`localhost`*/ /*!50003 TRIGGER `mail_tracker`.`token_tracker_AFTER_INSERT` AFTER INSERT ON `tracking_events` FOR EACH ROW
BEGIN
	SET @bucket_start := UNIX_TIMESTAMP(NEW.time) - UNIX_TIMESTAMP(NEW.time)%300;
	INSERT INTO tracking_event_summaries (token_id, unique_user_string, bucket_start_time, count) 
	VALUES (NEW.token_id, MD5(NEW.user_agent), @bucket_start, 1) 
	ON DUPLICATE KEY UPDATE count = count + 1;
    
    SET @unique_count_increase = ROW_COUNT()%2;
    INSERT INTO token_summaries (token_id, first_open, last_open, total_opens, unique_opens) 
    VALUES (NEW.token_id, NEW.id, NEW.id, 1, 1)
    ON DUPLICATE KEY UPDATE last_open = NEW.id, total_opens = total_opens + 1, 
    unique_opens = unique_opens + @unique_count_increase;
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2017-09-06 10:33:01
