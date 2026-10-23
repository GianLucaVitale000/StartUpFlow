-- MySQL dump 10.13  Distrib 8.0.46, for Linux (x86_64)
--
-- Host: localhost    Database: login_auth_db
-- ------------------------------------------------------
-- Server version	8.0.46-0ubuntu0.24.04.4

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `audit_logs`
--

DROP TABLE IF EXISTS `audit_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `audit_logs` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int unsigned DEFAULT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `action` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `meta` json DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_audit_user` (`user_id`),
  KEY `idx_audit_action_created` (`action`,`created_at`),
  CONSTRAINT `fk_audit_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `audit_logs`
--

LOCK TABLES `audit_logs` WRITE;
/*!40000 ALTER TABLE `audit_logs` DISABLE KEYS */;
/*!40000 ALTER TABLE `audit_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `client_contacts`
--

DROP TABLE IF EXISTS `client_contacts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `client_contacts` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `client_id` int unsigned NOT NULL,
  `first_name` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `last_name` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `role` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `phone` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_primary` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_contacts_client` (`client_id`),
  CONSTRAINT `fk_contacts_client` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `client_contacts`
--

LOCK TABLES `client_contacts` WRITE;
/*!40000 ALTER TABLE `client_contacts` DISABLE KEYS */;
INSERT INTO `client_contacts` VALUES (1,5,'Giampiero','Mughini','referente commerciale','mughi@example.it','+39 0511234567',1,'2026-10-18 06:22:34'),(2,6,'Marco','Rossi','referente commerciale','marco.rossi@example.it','+39 051123456',1,'2026-10-21 09:17:37');
/*!40000 ALTER TABLE `client_contacts` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `clients`
--

DROP TABLE IF EXISTS `clients`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `clients` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int unsigned NOT NULL,
  `client_type` enum('individual','company') COLLATE utf8mb4_unicode_ci NOT NULL,
  `company_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `first_name` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `last_name` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `vat_number` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cf` varchar(16) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `address` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `city` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `state` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `postal_code` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `country` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `phone` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `pec` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sdi_code` char(7) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `crm_status` enum('lead','prospect','customer','lost') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'lead',
  `source` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `converted_at` datetime DEFAULT NULL,
  `lost_reason` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_clients_id_user` (`id`,`user_id`),
  UNIQUE KEY `uk_clients_user_vat` (`user_id`,`vat_number`),
  KEY `idx_clients_user_status` (`user_id`,`crm_status`),
  CONSTRAINT `fk_clients_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=15 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `clients`
--

LOCK TABLES `clients` WRITE;
/*!40000 ALTER TABLE `clients` DISABLE KEYS */;
INSERT INTO `clients` VALUES (5,1,'company','TechInnovate S.r.l.','Mario','Rossi','IT12345678901','RSSMRA80A01H501U','Via Roma 123','Milano','MI','20121','Italia','info@techinnovate.test','+39 021234567','techinnovate@pec.test','M5UXCR1','customer','LinkedIn','Test','2026-10-18 12:39:54',NULL,'2026-10-18 06:22:34','2026-10-18 10:39:54'),(6,1,'individual',NULL,'Giulia','Bianchi',NULL,'BNCGLI90A41F205X','Corso Vittorio Emanuele 45','Roma','RM','00186','Italia','giulia.bianchi@test.local','+39 069876543','giulia.bianchi@pec.test','KRRH6BB','customer','Sito web','Contatto interessato ai servizi di nota spese e time tracker.','2026-10-21 23:15:59',NULL,'2026-10-18 06:22:34','2026-10-21 21:15:59'),(7,1,'company','Alpha Software Lab S.p.A.','Luca','Verdi','IT98765432109','VRDLCU85M02Z100W','Via Torino 45','Torino','TO','10123','Italia','contatti@alphasoftware.test','+39 011987654','alphasoftware@pec.test','SUBM70N','prospect','Fiera','Richiesta demo.',NULL,NULL,'2026-10-18 06:22:34','2026-10-18 06:22:34'),(8,1,'company','Beta Next S.r.l.','Marco','Neri','IT11223344556','NRRMRC90B02L219K','Via Dante 88','Bologna','BO','40125','Italia','supporto@betanext.test','+39 051443322','betanext@pec.test','M7Z4VW2','lead','Referral','Cliente acquisito tramite partner strategico.',NULL,NULL,'2026-10-18 06:22:34','2026-10-18 11:25:34'),(9,1,'company','Gamma Web Solutions S.r.l.','Sara','Neri','IT33445566778','NRSRA90C41L219Z','Via Emilia 10','Firenze','FI','50123','Italia','info@gammaweb.test','+39 055123456','gammaweb@pec.test','W7XYZ99','lead','Google','Test inserimento manuale con pulsante Salva.',NULL,NULL,'2026-10-18 06:22:34','2026-10-18 06:22:34'),(10,1,'company','Delta Solutions S.r.l.','Andrea','Conti','IT55667788990','CNTNDR88C15E472W','Via Veneto 99','Napoli','NA','80121','Italia','info@deltasolutions.test','+39 081554433','deltasolutions@pec.test','D9XY123','lost','Campagna Email',' Test finale pulsante Salva con correzione action del form.',NULL,'rifiutato offerta','2026-10-18 06:22:34','2026-10-21 07:00:22'),(13,6,'individual',NULL,'Tech','Innovate',NULL,NULL,NULL,NULL,NULL,NULL,NULL,'tech@example.test',NULL,NULL,NULL,'customer',NULL,NULL,'2026-10-20 10:10:59',NULL,'2026-10-20 08:10:01','2026-10-20 08:10:59'),(14,1,'individual','TizioCaio','Tizio','Caio',NULL,'CATTZI80A01H501Z','via Roma','Bologna','Bologna','40100','Italia','utente@test.com','+39 333 0000000','tizio.caio@pec.example.it','X4Y5Z6W','lead','Google',NULL,NULL,NULL,'2026-10-22 06:10:55','2026-10-22 06:10:55');
/*!40000 ALTER TABLE `clients` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `crm_activities`
--

DROP TABLE IF EXISTS `crm_activities`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `crm_activities` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `client_id` int unsigned NOT NULL,
  `order_id` int unsigned DEFAULT NULL,
  `quote_id` int unsigned DEFAULT NULL,
  `user_id` int unsigned NOT NULL,
  `type` enum('call','email','meeting','note','status_change') COLLATE utf8mb4_unicode_ci NOT NULL,
  `subject` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `body` text COLLATE utf8mb4_unicode_ci,
  `occurred_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `next_action_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_act_client` (`client_id`,`occurred_at`),
  KEY `idx_act_next` (`user_id`,`next_action_at`),
  KEY `idx_act_order` (`order_id`),
  KEY `idx_act_quote` (`quote_id`),
  CONSTRAINT `fk_act_client` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_act_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_act_quote` FOREIGN KEY (`quote_id`) REFERENCES `quotes` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_act_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `crm_activities`
--

LOCK TABLES `crm_activities` WRITE;
/*!40000 ALTER TABLE `crm_activities` DISABLE KEYS */;
INSERT INTO `crm_activities` VALUES (1,5,NULL,NULL,1,'call','Primo contatto','proposta di acquisizione cliente per fatturazione','2026-10-18 08:26:00','2026-10-19 10:30:00','2026-10-18 06:26:21'),(2,5,NULL,NULL,1,'status_change','Cambio stato','Stato cambiato da lead a lead','2026-10-18 06:51:50',NULL,'2026-10-18 06:51:50'),(3,5,NULL,NULL,1,'status_change','Cambio stato','Stato cambiato da lead a lead','2026-10-18 06:54:20',NULL,'2026-10-18 06:54:20'),(4,5,NULL,NULL,1,'status_change','Cambio stato','Stato cambiato da lead a lead','2026-10-18 06:57:32',NULL,'2026-10-18 06:57:32'),(5,5,1,NULL,1,'status_change','Promosso a customer','Da lead a customer (ordine ORD-2026-001)','2026-10-18 12:39:54',NULL,'2026-10-18 10:39:54'),(6,5,NULL,NULL,1,'status_change','Cambio stato','Stato cambiato da customer a customer','2026-10-18 10:40:26',NULL,'2026-10-18 10:40:26'),(7,8,NULL,NULL,1,'status_change','Cambio stato','Stato cambiato da lead a lead','2026-10-18 11:25:34',NULL,'2026-10-18 11:25:34'),(9,13,8,NULL,6,'status_change','Promosso a customer','Da prospect a customer (ordine BROWSER-ORD-1)','2026-10-20 10:10:59',NULL,'2026-10-20 08:10:59'),(10,10,NULL,NULL,1,'status_change','Cambio stato','Da prospect a lost (motivo: rifiutato offerta)','2026-10-21 09:00:22',NULL,'2026-10-21 07:00:22'),(11,5,NULL,NULL,1,'call','Ordine confermato','Chiamare il cliente per confermale l\'esecuzione dell\'ordine','2026-10-21 09:10:32',NULL,'2026-10-21 09:10:32'),(12,6,NULL,NULL,1,'call','Presentazione','presentare il prodotto','2026-10-21 09:18:49','2026-10-22 11:30:00','2026-10-21 09:18:49'),(13,6,12,NULL,1,'status_change','Promosso a customer','Da lead a customer (ordine ORD-2026-008)','2026-10-21 23:15:59',NULL,'2026-10-21 21:15:59'),(14,6,NULL,NULL,1,'note','Acquisizione Cliente','il cliente ha confermato ordine','2026-10-22 18:58:54',NULL,'2026-10-22 18:58:54'),(15,14,NULL,NULL,1,'call','Primo contatto','presentazione servizi','2026-10-26 12:00:00','2026-11-30 12:00:00','2026-10-22 19:01:44');
/*!40000 ALTER TABLE `crm_activities` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `documents`
--

DROP TABLE IF EXISTS `documents`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `documents` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `client_id` int unsigned NOT NULL,
  `type` enum('NDA','Contract') COLLATE utf8mb4_unicode_ci NOT NULL,
  `file_path` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `client_id` (`client_id`),
  CONSTRAINT `documents_ibfk_1` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `documents`
--

LOCK TABLES `documents` WRITE;
/*!40000 ALTER TABLE `documents` DISABLE KEYS */;
/*!40000 ALTER TABLE `documents` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `email_change_requests`
--

DROP TABLE IF EXISTS `email_change_requests`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `email_change_requests` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int unsigned NOT NULL,
  `new_email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `token_hash` char(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `expires_at` datetime NOT NULL,
  `used_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_email_change_user_id` (`user_id`),
  CONSTRAINT `fk_email_change_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `email_change_requests`
--

LOCK TABLES `email_change_requests` WRITE;
/*!40000 ALTER TABLE `email_change_requests` DISABLE KEYS */;
/*!40000 ALTER TABLE `email_change_requests` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `email_verifications`
--

DROP TABLE IF EXISTS `email_verifications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `email_verifications` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int unsigned NOT NULL,
  `token_hash` char(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `expires_at` datetime NOT NULL,
  `used_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_email_verifications_token_hash` (`token_hash`),
  KEY `idx_email_verifications_user_id` (`user_id`),
  CONSTRAINT `fk_email_verifications_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `email_verifications`
--

LOCK TABLES `email_verifications` WRITE;
/*!40000 ALTER TABLE `email_verifications` DISABLE KEYS */;
INSERT INTO `email_verifications` VALUES (1,2,'0cddf695157b205aed22776c1d801cb44ab2b8f6c35eb3c6bea086e8c3c87288','2026-08-27 11:57:01','2026-08-26 13:57:21','2026-08-26 11:57:01'),(2,7,'a86eafe97f1d1664933fd454c514abee83c5b0c4af2c8695e26398a4ffb7a2c9','2026-10-23 16:25:47','2026-10-22 18:28:11','2026-10-22 16:25:47');
/*!40000 ALTER TABLE `email_verifications` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `invoices`
--

DROP TABLE IF EXISTS `invoices`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `invoices` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `client_id` int unsigned NOT NULL,
  `user_id` int unsigned NOT NULL,
  `document_number` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `issue_date` date NOT NULL,
  `due_date` date NOT NULL,
  `currency` varchar(3) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'EUR',
  `tax_regime` enum('forfettario','ordinario') COLLATE utf8mb4_unicode_ci NOT NULL,
  `total` decimal(10,2) NOT NULL DEFAULT '0.00',
  `is_paid` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_invoices_document_number` (`document_number`),
  KEY `fk_invoices_client` (`client_id`),
  KEY `fk_invoices_user` (`user_id`),
  CONSTRAINT `fk_invoices_client` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`),
  CONSTRAINT `fk_invoices_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `invoices`
--

LOCK TABLES `invoices` WRITE;
/*!40000 ALTER TABLE `invoices` DISABLE KEYS */;
INSERT INTO `invoices` VALUES (1,6,1,'SF-134C18BF294E44A8','2026-10-22','2026-11-22','EUR','forfettario',10.00,0,'2026-10-22 10:07:14','2026-10-22 10:07:14'),(2,6,1,'SF-D79818FBC12CCBA3','2026-10-22','2026-10-22','EUR','forfettario',1.00,0,'2026-10-22 15:42:35','2026-10-22 15:42:35');
/*!40000 ALTER TABLE `invoices` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `order_items`
--

DROP TABLE IF EXISTS `order_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `order_items` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `order_id` int unsigned NOT NULL,
  `position` smallint unsigned NOT NULL DEFAULT '1',
  `description` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL,
  `quantity` decimal(10,2) NOT NULL DEFAULT '1.00',
  `unit_price` decimal(10,2) NOT NULL,
  `vat_rate` decimal(5,2) NOT NULL DEFAULT '0.00',
  `line_total` decimal(10,2) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_order_items_order` (`order_id`),
  CONSTRAINT `fk_order_items_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `order_items`
--

LOCK TABLES `order_items` WRITE;
/*!40000 ALTER TABLE `order_items` DISABLE KEYS */;
INSERT INTO `order_items` VALUES (6,8,1,'Riga da browser',2.00,100.00,22.00,244.00),(7,9,1,'sito',1.00,100.00,22.00,122.00),(8,10,1,'mouse ergonomico',1.00,50.00,22.00,61.00),(9,11,1,'mouse',1.00,50.00,22.00,61.00),(10,11,2,'keyboard',1.00,16.00,22.00,19.52),(11,12,1,'matite',100.00,2.00,22.00,244.00),(12,12,2,'evidenziatori',10.00,1.59,22.00,19.40);
/*!40000 ALTER TABLE `order_items` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `orders`
--

DROP TABLE IF EXISTS `orders`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `orders` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int unsigned NOT NULL,
  `client_id` int unsigned NOT NULL,
  `quote_id` int unsigned DEFAULT NULL,
  `order_number` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` enum('draft','confirmed','fulfilled','cancelled') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `payment_status` enum('unpaid','partial','paid') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'unpaid',
  `currency` char(3) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'EUR',
  `total` decimal(10,2) NOT NULL DEFAULT '0.00',
  `notes` text COLLATE utf8mb4_unicode_ci,
  `confirmed_at` datetime DEFAULT NULL,
  `fulfilled_at` datetime DEFAULT NULL,
  `paid_at` datetime DEFAULT NULL,
  `cancelled_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_orders_user_number` (`user_id`,`order_number`),
  UNIQUE KEY `uk_orders_quote` (`quote_id`),
  KEY `idx_orders_client_user` (`client_id`,`user_id`),
  KEY `idx_orders_user_status` (`user_id`,`status`),
  CONSTRAINT `fk_orders_client_user` FOREIGN KEY (`client_id`, `user_id`) REFERENCES `clients` (`id`, `user_id`),
  CONSTRAINT `fk_orders_quote` FOREIGN KEY (`quote_id`) REFERENCES `quotes` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `orders`
--

LOCK TABLES `orders` WRITE;
/*!40000 ALTER TABLE `orders` DISABLE KEYS */;
INSERT INTO `orders` VALUES (1,1,5,NULL,'ORD-2026-001','confirmed','unpaid','EUR',100.00,'Regime: forfettario, Scadenza: 2026-10-19','2026-10-18 12:39:54',NULL,NULL,NULL,'2026-10-18 10:39:54','2026-10-18 10:39:54'),(2,1,5,NULL,'ORD-2026-002','confirmed','unpaid','EUR',120.22,'Regime: forfettario, Scadenza: 2026-10-20','2026-10-18 13:10:03',NULL,NULL,NULL,'2026-10-18 11:10:03','2026-10-18 11:10:03'),(3,1,5,NULL,'ORD-2026-003','confirmed','unpaid','EUR',500.99,'Regime: forfettario, Scadenza: 2026-10-21','2026-10-18 13:20:08',NULL,NULL,NULL,'2026-10-18 11:20:08','2026-10-18 11:20:08'),(4,1,5,NULL,'ORD-2026-004','confirmed','unpaid','EUR',70.00,'Regime: forfettario, Scadenza: 2026-10-22','2026-10-18 13:24:33',NULL,NULL,NULL,'2026-10-18 11:24:33','2026-10-18 11:24:33'),(8,6,13,NULL,'BROWSER-ORD-1','confirmed','unpaid','EUR',244.00,NULL,'2026-10-20 10:10:59',NULL,NULL,NULL,'2026-10-20 08:10:59','2026-10-20 08:10:59'),(9,1,5,NULL,'ORD-2026-005','confirmed','unpaid','EUR',122.00,'manutenzione sito','2026-10-20 10:12:47',NULL,NULL,NULL,'2026-10-20 08:12:47','2026-10-20 08:12:47'),(10,1,5,NULL,'ORD-2026-006','confirmed','unpaid','EUR',61.00,NULL,'2026-10-20 10:44:08',NULL,NULL,NULL,'2026-10-20 08:44:08','2026-10-20 08:44:08'),(11,1,5,NULL,'ORD-2026-007','confirmed','unpaid','EUR',80.52,NULL,'2026-10-20 10:44:45',NULL,NULL,NULL,'2026-10-20 08:44:45','2026-10-20 08:44:45'),(12,1,6,NULL,'ORD-2026-008','confirmed','unpaid','EUR',263.40,NULL,'2026-10-21 23:15:59',NULL,NULL,NULL,'2026-10-21 21:15:59','2026-10-21 21:15:59');
/*!40000 ALTER TABLE `orders` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `password_resets`
--

DROP TABLE IF EXISTS `password_resets`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `password_resets` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int unsigned NOT NULL,
  `token_hash` char(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `expires_at` datetime NOT NULL,
  `used_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_password_resets_token_hash` (`token_hash`),
  KEY `idx_password_resets_user_id` (`user_id`),
  CONSTRAINT `fk_password_resets_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `password_resets`
--

LOCK TABLES `password_resets` WRITE;
/*!40000 ALTER TABLE `password_resets` DISABLE KEYS */;
INSERT INTO `password_resets` VALUES (1,2,'eaa7d398896def68142504c794598bfed1df7e66637fa8b621ec61b034dcac94','2026-08-26 15:01:11','2026-08-26 14:01:11','2026-08-26 12:01:11'),(2,2,'5786d5d7dbb427c2aaf89f95c3d2cd9c78cd4020feedcc5ecda0f8a01aa4e1bf','2026-08-26 13:30:04','2026-08-26 14:31:09','2026-08-26 12:30:04');
/*!40000 ALTER TABLE `password_resets` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `payments`
--

DROP TABLE IF EXISTS `payments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `payments` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `order_id` int unsigned NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `method` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `reference` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `received_at` datetime NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_payments_order` (`order_id`),
  CONSTRAINT `fk_payments_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `payments`
--

LOCK TABLES `payments` WRITE;
/*!40000 ALTER TABLE `payments` DISABLE KEYS */;
/*!40000 ALTER TABLE `payments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `quote_items`
--

DROP TABLE IF EXISTS `quote_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `quote_items` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `quote_id` int unsigned NOT NULL,
  `position` smallint unsigned NOT NULL DEFAULT '1',
  `description` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL,
  `quantity` decimal(10,2) NOT NULL DEFAULT '1.00',
  `unit_price` decimal(10,2) NOT NULL,
  `vat_rate` decimal(5,2) NOT NULL DEFAULT '0.00',
  `line_total` decimal(10,2) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_quote_items_quote` (`quote_id`),
  CONSTRAINT `fk_quote_items_quote` FOREIGN KEY (`quote_id`) REFERENCES `quotes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `quote_items`
--

LOCK TABLES `quote_items` WRITE;
/*!40000 ALTER TABLE `quote_items` DISABLE KEYS */;
/*!40000 ALTER TABLE `quote_items` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `quotes`
--

DROP TABLE IF EXISTS `quotes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `quotes` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int unsigned NOT NULL,
  `client_id` int unsigned NOT NULL,
  `quote_number` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` enum('draft','sent','accepted','rejected','expired') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `issue_date` date NOT NULL,
  `valid_until` date DEFAULT NULL,
  `currency` char(3) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'EUR',
  `total` decimal(10,2) NOT NULL DEFAULT '0.00',
  `notes` text COLLATE utf8mb4_unicode_ci,
  `accepted_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_quotes_user_number` (`user_id`,`quote_number`),
  KEY `idx_quotes_client_user` (`client_id`,`user_id`),
  CONSTRAINT `fk_quotes_client_user` FOREIGN KEY (`client_id`, `user_id`) REFERENCES `clients` (`id`, `user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `quotes`
--

LOCK TABLES `quotes` WRITE;
/*!40000 ALTER TABLE `quotes` DISABLE KEYS */;
/*!40000 ALTER TABLE `quotes` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `remember_me_tokens`
--

DROP TABLE IF EXISTS `remember_me_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `remember_me_tokens` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int unsigned NOT NULL,
  `selector` char(24) COLLATE utf8mb4_unicode_ci NOT NULL,
  `token_hash` char(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `expires_at` datetime NOT NULL,
  `used_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_remember_me_tokens_selector` (`selector`),
  UNIQUE KEY `uk_remember_me_tokens_token_hash` (`token_hash`),
  KEY `idx_remember_me_tokens_user_id` (`user_id`),
  CONSTRAINT `fk_remember_me_tokens_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `remember_me_tokens`
--

LOCK TABLES `remember_me_tokens` WRITE;
/*!40000 ALTER TABLE `remember_me_tokens` DISABLE KEYS */;
/*!40000 ALTER TABLE `remember_me_tokens` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `users` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `username` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `avatar_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `password_hash` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `failed_login_attempts` int unsigned NOT NULL DEFAULT '0',
  `locked_until` datetime DEFAULT NULL,
  `last_login_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_users_username` (`username`),
  UNIQUE KEY `uk_users_email` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (1,'utente','utente@test.com','uploads/avatars/098eef61b475b27a698f6bc5aeca8db0.jpg','$2y$12$L7C/M6Bm6Su9IwuFjXDqt.vKMzX.6IOvqPSVAOMAbwHhoLtKFyzxq',1,0,NULL,'2026-10-23 08:05:44','2026-08-26 11:55:31','2026-10-23 06:05:44'),(2,'mattarella','mattarella@test.com','uploads/avatars/f540cf096ff3d2ff84cf59b569f8f331.jpg','$2y$10$UbyKYjbnXD9OaLUqb7MoP.zw0dcp35yIg/JVsyBHvZ5Z2/7uA/Rb6',1,0,NULL,'2026-08-26 14:33:25','2026-08-26 11:57:01','2026-08-26 12:33:25'),(6,'TechUser','tech@example.test',NULL,'$2y$10$MK01QNOgxCdtGpcCt.tCV.uZBRejrzRhw3gkq2l4.TEyuxoyGzc02',1,0,NULL,'2026-10-20 10:10:55','2026-10-20 08:10:01','2026-10-20 08:10:55'),(7,'sergio','sergio@example.it','uploads/avatars/c15e16448a6de6ece585b9201aece136.jpg','$2y$10$vIWcMo3yDlby0EeBreAD4uPmD6AjPoHOUcgL0/pSgtIUFKerSWFQ6',1,0,NULL,'2026-10-22 18:28:25','2026-10-22 16:25:47','2026-10-22 16:38:34');
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `users_profiles`
--

DROP TABLE IF EXISTS `users_profiles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `users_profiles` (
  `user_id` int unsigned NOT NULL,
  `first_name` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `last_name` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `birth_date` date DEFAULT NULL,
  `birth_place` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `birth_country` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cf` varchar(16) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `address` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `city` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `postal_code` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`user_id`),
  UNIQUE KEY `cf` (`cf`),
  CONSTRAINT `fk_users_profiles_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users_profiles`
--

LOCK TABLES `users_profiles` WRITE;
/*!40000 ALTER TABLE `users_profiles` DISABLE KEYS */;
INSERT INTO `users_profiles` VALUES (1,'Tizio','Caio','1990-01-01','Roma','IT','CATTZI80A01H501Z','via Milano 10','Bologna','40100','2026-10-22 08:25:14','2026-10-22 08:26:34'),(7,'Sergio','Mattarella','1941-07-23','Palermo','IT','MTTSRG41L23G273V','via del Quirinale 1','Roma','00118','2026-10-22 18:25:47','2026-10-22 18:36:01');
/*!40000 ALTER TABLE `users_profiles` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-10-23 17:39:02
