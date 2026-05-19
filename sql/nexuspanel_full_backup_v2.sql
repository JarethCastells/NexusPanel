-- MariaDB dump 10.19  Distrib 10.4.32-MariaDB, for Win64 (AMD64)
--
-- Host: localhost    Database: nexuspanel
-- ------------------------------------------------------
-- Server version	10.4.32-MariaDB

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `auditoria_eventos`
--

DROP TABLE IF EXISTS `auditoria_eventos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `auditoria_eventos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `usuario_id` int(11) DEFAULT NULL,
  `rol` varchar(40) DEFAULT NULL,
  `modulo` varchar(60) NOT NULL,
  `accion` varchar(80) NOT NULL,
  `referencia_tipo` varchar(40) DEFAULT NULL,
  `referencia_id` int(11) DEFAULT NULL,
  `detalles` text DEFAULT NULL,
  `ip_origen` varchar(64) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_ae_usuario` (`usuario_id`),
  KEY `idx_ae_modulo` (`modulo`),
  KEY `idx_ae_fecha` (`created_at`),
  CONSTRAINT `fk_ae_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=28 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `auditoria_eventos`
--

LOCK TABLES `auditoria_eventos` WRITE;
/*!40000 ALTER TABLE `auditoria_eventos` DISABLE KEYS */;
INSERT INTO `auditoria_eventos` VALUES (1,1,'administrador','pedidos','asignar_operador_manual','pedido',13,'Asignado a operador #2','189.226.235.35','2026-04-19 17:21:05'),(2,1,'administrador','pedidos','cancelar','pedido',13,'Cancelado por coordinacion desde resumen de pedido','189.226.235.35','2026-04-19 17:21:13'),(3,1,'administrador','pedidos','cambiar_estado','pedido',13,'Estado -> cancelado','189.226.235.35','2026-04-19 17:21:20'),(4,1,'administrador','pedidos','cambiar_estado','pedido',11,'Estado -> en_camino','189.226.235.35','2026-04-19 17:21:25'),(5,1,'administrador','pedidos','cambiar_estado','pedido',14,'Estado -> pendiente','189.226.235.35','2026-04-19 17:22:08'),(6,1,'administrador','pedidos','asignar_operador_manual','pedido',14,'Asignado a operador #2','189.226.235.35','2026-04-19 17:23:47'),(7,1,'administrador','pedidos','cambiar_estado','pedido',15,'Estado -> cancelado','189.226.235.35','2026-04-19 17:23:58'),(8,4,'cliente','pedidos','crear_pedido','pedido',16,'Pedido creado con 2 productos','189.226.235.35','2026-04-20 11:49:10'),(9,1,'administrador','pedidos','asignar_operador_manual','pedido',16,'Asignado a operador #2','189.226.235.35','2026-04-20 11:49:47'),(10,1,'administrador','pedidos','cambiar_estado','pedido',15,'Estado -> entregado','189.226.235.35','2026-04-20 11:54:39'),(11,1,'administrador','pedidos','cambiar_estado','pedido',13,'Estado -> entregado','189.226.235.35','2026-04-20 11:54:44'),(12,1,'administrador','pedidos','cambiar_estado','pedido',11,'Estado -> entregado','189.226.235.35','2026-04-20 11:54:50'),(13,1,'administrador','pedidos','cambiar_estado','pedido',9,'Estado -> entregado','189.226.235.35','2026-04-20 11:54:55'),(14,1,'administrador','pedidos','cambiar_estado','pedido',5,'Estado -> entregado','189.226.235.35','2026-04-20 11:55:01'),(15,1,'administrador','pedidos','cambiar_estado','pedido',16,'Estado -> en_camino','189.226.235.35','2026-04-20 11:55:08'),(16,4,'cliente','pedidos','crear_pedido','pedido',17,'Pedido creado con 4 productos','187.188.14.232','2026-04-20 14:26:30'),(17,1,'administrador','pedidos','asignar_operador_manual','pedido',17,'Asignado a operador #2','187.188.14.232','2026-04-20 14:45:55'),(18,2,'operador','pedidos','iniciar_viaje','pedido',17,'Pedido en camino','::1','2026-04-21 23:30:43'),(19,2,'operador','pedidos','iniciar_viaje','pedido',14,'Pedido en camino','::1','2026-04-21 23:47:08'),(20,2,'operador','pedidos','entregar','pedido',14,'Pedido entregado','::1','2026-04-21 23:52:04'),(21,2,'operador','pedidos','entregar','pedido',17,'Pedido entregado','::1','2026-04-21 23:53:38'),(22,1,'administrador','pedidos','cambiar_estado','pedido',16,'Estado -> cancelado','::1','2026-05-12 14:12:16'),(23,1,'administrador','pedidos','cambiar_estado','pedido',17,'Estado -> cancelado','::1','2026-05-12 14:12:18'),(24,1,'administrador','pedidos','cambiar_estado','pedido',15,'Estado -> cancelado','::1','2026-05-12 14:12:21'),(25,4,'cliente','pedidos','crear_pedido','pedido',18,'Pedido creado con 2 productos','::1','2026-05-12 15:04:44'),(26,4,'cliente','pedidos','crear_pedido','pedido',19,'Pedido creado con 2 productos','::1','2026-05-13 03:18:55'),(27,4,'cliente','pedidos','crear_pedido','pedido',20,'Pedido creado con 1 productos','::1','2026-05-13 13:34:38');
/*!40000 ALTER TABLE `auditoria_eventos` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `cfdi_eventos`
--

DROP TABLE IF EXISTS `cfdi_eventos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `cfdi_eventos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `pedido_id` int(11) NOT NULL,
  `estado` enum('pendiente','timbrado','error') NOT NULL DEFAULT 'pendiente',
  `mensaje` varchar(255) DEFAULT NULL,
  `payload_json` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_cfdi_pedido` (`pedido_id`),
  KEY `idx_cfdi_estado` (`estado`),
  CONSTRAINT `fk_cfdi_pedido` FOREIGN KEY (`pedido_id`) REFERENCES `pedidos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cfdi_eventos`
--

LOCK TABLES `cfdi_eventos` WRITE;
/*!40000 ALTER TABLE `cfdi_eventos` DISABLE KEYS */;
/*!40000 ALTER TABLE `cfdi_eventos` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `chat`
--

DROP TABLE IF EXISTS `chat`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `chat` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `pedido_id` int(11) NOT NULL,
  `usuario_id` int(11) NOT NULL,
  `mensaje` text NOT NULL,
  `ts` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `pedido_id` (`pedido_id`),
  KEY `usuario_id` (`usuario_id`),
  CONSTRAINT `chat_ibfk_1` FOREIGN KEY (`pedido_id`) REFERENCES `pedidos` (`id`),
  CONSTRAINT `chat_ibfk_2` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=71 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `chat`
--

LOCK TABLES `chat` WRITE;
/*!40000 ALTER TABLE `chat` DISABLE KEYS */;
INSERT INTO `chat` VALUES (1,1,2,'✅ He aceptado tu pedido. En breve estaré en camino.','2026-03-23 16:55:35'),(2,1,2,'🚛 ¡Ya voy en camino! Puedes ver mi ubicación en tiempo real en el mapa.','2026-03-23 16:55:43'),(3,1,2,'Hola','2026-03-23 16:55:46'),(4,1,4,'Como estas','2026-03-23 16:56:17'),(5,2,2,'✅ He aceptado tu pedido. En breve estaré en camino.','2026-03-23 17:08:29'),(6,1,2,'Ya voy de camino','2026-03-23 17:12:41'),(7,2,2,'🚛 ¡Ya voy en camino! Puedes ver mi ubicación en tiempo real en el mapa.','2026-03-23 17:12:46'),(8,2,2,'Ya voy para alla','2026-03-23 17:12:52'),(9,3,2,'✅ He aceptado tu pedido. En breve estaré en camino.','2026-03-23 17:15:42'),(10,4,2,'✅ He aceptado tu pedido. En breve estaré en camino.','2026-03-23 17:15:48'),(11,3,2,'🚛 ¡Ya voy en camino! Puedes ver mi ubicación en tiempo real en el mapa.','2026-03-23 17:16:09'),(12,3,2,'📦 ¡Pedido entregado! Gracias por tu compra.','2026-03-23 17:16:12'),(13,1,2,'📦 ¡Pedido entregado! Gracias por tu compra.','2026-03-23 17:16:22'),(14,4,2,'📦 ¡Pedido entregado! Gracias por tu compra.','2026-03-23 17:19:32'),(15,2,2,'📦 ¡Pedido entregado! Gracias por tu compra.','2026-03-23 17:19:37'),(16,7,2,'✅ He aceptado tu pedido. En breve estaré en camino.','2026-03-23 17:35:10'),(17,6,2,'✅ He aceptado tu pedido. En breve estaré en camino.','2026-03-23 17:35:19'),(18,6,2,'🚛 ¡Ya voy en camino! Puedes ver mi ubicación en tiempo real en el mapa.','2026-03-23 17:35:24'),(19,7,2,'🚛 ¡Ya voy en camino! Puedes ver mi ubicación en tiempo real en el mapa.','2026-03-23 17:35:27'),(20,6,2,'hola ya voy de camino','2026-03-23 17:35:40'),(21,7,2,'tardare en llegar','2026-03-23 17:35:51'),(22,7,5,'ok','2026-03-23 17:36:26'),(23,6,4,'okay','2026-03-23 17:36:58'),(24,6,2,'ya llegue','2026-03-23 17:37:27'),(25,6,2,'📦 ¡Pedido entregado! Gracias por tu compra.','2026-03-23 17:37:32'),(26,7,5,'donde estas','2026-03-23 17:38:26'),(27,7,2,'ya llegue','2026-03-23 17:40:13'),(28,7,2,'📦 ¡Pedido entregado! Gracias por tu compra.','2026-03-23 17:40:18'),(29,5,2,'✅ He aceptado tu pedido. En breve estaré en camino.','2026-03-23 20:37:52'),(30,5,2,'Hola','2026-03-23 20:37:57'),(31,8,2,'✅ He aceptado tu pedido. En breve estaré en camino.','2026-03-23 20:43:45'),(32,8,2,'Perfecto','2026-03-23 20:43:58'),(33,8,4,'Okay','2026-03-23 20:45:01'),(34,9,2,'✅ He aceptado tu pedido. En breve estaré en camino.','2026-03-23 22:10:13'),(35,8,2,'ok','2026-03-23 22:46:02'),(36,5,2,'hola','2026-03-24 12:36:58'),(37,8,2,'🚛 ¡Ya voy en camino! Puedes ver mi ubicación en tiempo real en el mapa.','2026-03-24 13:17:28'),(38,8,2,'📦 ¡Pedido entregado! Gracias por tu compra.','2026-03-24 13:17:35'),(39,9,4,'ok','2026-03-24 14:03:50'),(40,9,2,'ok','2026-03-24 14:04:30'),(41,9,2,'🚛 ¡Ya voy en camino! Puedes ver mi ubicación en tiempo real en el mapa.','2026-03-25 12:44:07'),(42,9,2,'hola','2026-03-25 13:06:27'),(43,10,2,'He aceptado tu pedido. En breve estare en camino.','2026-04-09 09:56:59'),(44,10,2,'Pedido entregado. Gracias por tu compra.','2026-04-09 09:57:14'),(45,11,2,'He aceptado tu pedido. En breve estare en camino.','2026-04-09 09:58:05'),(46,11,2,'Ya voy en camino. Puedes ver mi ubicacion en tiempo real en el mapa.','2026-04-09 09:58:08'),(47,11,2,'Hola ya vo de camino','2026-04-09 09:58:18'),(48,12,2,'He aceptado tu pedido. En breve estare en camino.','2026-04-09 18:28:11'),(49,12,2,'Ya voy en camino. Puedes ver mi ubicacion en tiempo real en el mapa.','2026-04-09 18:28:24'),(50,12,2,'Pedido entregado. Gracias por tu compra.','2026-04-09 18:30:19'),(51,5,2,'Ya voy en camino. Puedes ver mi ubicacion en tiempo real en el mapa.','2026-04-10 00:36:22'),(52,5,2,'Hola','2026-04-10 00:36:41'),(53,14,2,'He aceptado tu pedido. En breve estare en camino.','2026-04-10 11:10:51'),(54,14,2,'Ya voy en camino. Puedes ver mi ubicacion en tiempo real en el mapa.','2026-04-10 11:11:01'),(55,14,2,'Hola, ya va tu pedido','2026-04-10 11:11:29'),(56,14,4,'muy bien','2026-04-10 11:12:29'),(57,14,2,'Pedido entregado. Gracias por tu compra.','2026-04-10 11:13:08'),(58,15,2,'He aceptado tu pedido. En breve estare en camino.','2026-04-16 10:13:40'),(59,15,2,'Ya voy en camino. Puedes ver mi ubicacion en tiempo real en el mapa.','2026-04-16 10:13:51'),(60,13,2,'Pedido asignado por coordinacion. Confirmo recepcion.','2026-04-19 17:21:05'),(61,14,2,'Pedido asignado por coordinacion. Confirmo recepcion.','2026-04-19 17:23:47'),(62,16,2,'Pedido asignado por coordinacion. Confirmo recepcion.','2026-04-20 11:49:47'),(63,17,2,'Pedido asignado por coordinacion. Confirmo recepcion.','2026-04-20 14:45:55'),(64,17,2,'Ya voy en camino. Puedes ver mi ubicacion en tiempo real en el mapa.','2026-04-21 23:30:43'),(65,14,2,'Ya voy en camino. Puedes ver mi ubicacion en tiempo real en el mapa.','2026-04-21 23:47:08'),(66,14,2,'Pedido entregado. Gracias por tu compra.','2026-04-21 23:52:04'),(67,17,2,'Pedido entregado. Gracias por tu compra.','2026-04-21 23:53:38'),(68,18,3,'He aceptado tu pedido. En breve estare en camino.','2026-05-12 15:04:44'),(69,19,3,'He aceptado tu pedido. En breve estare en camino.','2026-05-13 03:18:55'),(70,20,2,'Ya voy en camino. Puedes ver mi ubicacion en tiempo real en el mapa.','2026-05-14 02:38:34');
/*!40000 ALTER TABLE `chat` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `chat_ayuda_operador`
--

DROP TABLE IF EXISTS `chat_ayuda_operador`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `chat_ayuda_operador` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `operador_id` int(11) NOT NULL,
  `admin_id` int(11) NOT NULL DEFAULT 0,
  `remitente_id` int(11) NOT NULL,
  `mensaje` text NOT NULL,
  `ts` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_chat_ayuda_operador_lookup` (`operador_id`,`id`),
  KEY `idx_chat_ayuda_operador_remitente` (`remitente_id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `chat_ayuda_operador`
--

LOCK TABLES `chat_ayuda_operador` WRITE;
/*!40000 ALTER TABLE `chat_ayuda_operador` DISABLE KEYS */;
INSERT INTO `chat_ayuda_operador` VALUES (1,2,1,1,'HOLA','2026-05-14 01:44:22'),(2,2,0,2,'que pasó?','2026-05-14 01:54:43');
/*!40000 ALTER TABLE `chat_ayuda_operador` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `cliente_producto_historial`
--

DROP TABLE IF EXISTS `cliente_producto_historial`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `cliente_producto_historial` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `cliente_id` int(11) NOT NULL,
  `producto_id` int(11) NOT NULL,
  `cantidad_total` int(11) NOT NULL DEFAULT 0,
  `veces_pedido` int(11) NOT NULL DEFAULT 0,
  `ultima_fecha` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `ux_cliente_producto` (`cliente_id`,`producto_id`),
  KEY `idx_historial_cliente` (`cliente_id`),
  KEY `idx_historial_producto` (`producto_id`),
  CONSTRAINT `fk_historial_cliente` FOREIGN KEY (`cliente_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_historial_producto` FOREIGN KEY (`producto_id`) REFERENCES `productos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=164 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cliente_producto_historial`
--

LOCK TABLES `cliente_producto_historial` WRITE;
/*!40000 ALTER TABLE `cliente_producto_historial` DISABLE KEYS */;
INSERT INTO `cliente_producto_historial` VALUES (145,4,1,10,6,'2026-04-10 03:18:49','2026-05-13 13:34:38','2026-05-13 13:34:38'),(146,4,2,11,5,'2026-05-13 11:18:54','2026-05-13 13:34:38','2026-05-13 13:34:38'),(147,4,3,6,6,'2026-05-13 11:18:54','2026-05-13 13:34:38','2026-05-13 13:34:38'),(148,4,4,12,5,'2026-05-13 21:34:38','2026-05-13 13:34:38','2026-05-13 13:34:38'),(149,4,5,4,4,'2026-03-23 22:09:55','2026-05-13 13:34:38','2026-05-13 13:34:38'),(150,4,6,2,2,'2026-04-10 03:18:49','2026-05-13 13:34:38','2026-05-13 13:34:38'),(151,4,9,2,2,'2026-03-23 17:22:21','2026-05-13 13:34:38','2026-05-13 13:34:38'),(152,4,10,2,2,'2026-03-23 17:22:21','2026-05-13 13:34:38','2026-05-13 13:34:38'),(153,4,11,1,1,'2026-03-23 17:14:14','2026-05-13 13:34:38','2026-05-13 13:34:38'),(154,4,12,2,2,'2026-03-23 17:14:14','2026-05-13 13:34:38','2026-05-13 13:34:38'),(155,4,15,1,1,'2026-03-23 17:14:14','2026-05-13 13:34:38','2026-05-13 13:34:38'),(156,4,17,1,1,'2026-03-23 17:14:14','2026-05-13 13:34:38','2026-05-13 13:34:38'),(157,4,18,1,1,'2026-03-23 17:13:46','2026-05-13 13:34:38','2026-05-13 13:34:38'),(158,4,20,1,1,'2026-03-23 17:13:46','2026-05-13 13:34:38','2026-05-13 13:34:38'),(159,4,22,1,1,'2026-03-23 17:08:16','2026-05-13 13:34:38','2026-05-13 13:34:38'),(160,4,25,1,1,'2026-03-23 17:13:46','2026-05-13 13:34:38','2026-05-13 13:34:38'),(161,4,26,1,1,'2026-03-23 17:13:46','2026-05-13 13:34:38','2026-05-13 13:34:38'),(162,4,27,1,1,'2026-03-23 17:08:16','2026-05-13 13:34:38','2026-05-13 13:34:38'),(163,4,29,4,1,'2026-04-10 11:06:12','2026-05-13 13:34:38','2026-05-13 13:34:38');
/*!40000 ALTER TABLE `cliente_producto_historial` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `especies`
--

DROP TABLE IF EXISTS `especies`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `especies` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(80) NOT NULL,
  `slug` varchar(80) NOT NULL,
  `descripcion` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `nombre` (`nombre`),
  UNIQUE KEY `slug` (`slug`)
) ENGINE=InnoDB AUTO_INCREMENT=28685 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `especies`
--

LOCK TABLES `especies` WRITE;
/*!40000 ALTER TABLE `especies` DISABLE KEYS */;
INSERT INTO `especies` VALUES (1,'Pollos','pollos',NULL,'2026-04-13 15:55:24'),(2,'Vacas','vacas',NULL,'2026-04-13 15:55:24'),(3,'Cerdos','cerdos',NULL,'2026-04-13 15:55:24'),(4,'Acuacultura','acuacultura',NULL,'2026-04-13 15:55:24'),(2471,'Alimento','alimento','Categoria base de insumos y nutricion','2026-04-19 16:15:29'),(2472,'Comida','comida','Categoria base de alimento terminado','2026-04-19 16:15:29'),(2629,'Pollo','pollo','Categoria base','2026-04-19 17:10:51'),(2630,'Vaca','vaca','Categoria base','2026-04-19 17:10:51'),(2631,'Cerdo','cerdo','Categoria base','2026-04-19 17:10:51');
/*!40000 ALTER TABLE `especies` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `inventario_movimientos`
--

DROP TABLE IF EXISTS `inventario_movimientos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `inventario_movimientos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `producto_id` int(11) NOT NULL,
  `tipo` enum('entrada','salida','ajuste','reserva','liberacion') NOT NULL,
  `cantidad` int(11) NOT NULL,
  `stock_anterior` int(11) NOT NULL,
  `stock_nuevo` int(11) NOT NULL,
  `referencia_tipo` varchar(40) DEFAULT NULL,
  `referencia_id` int(11) DEFAULT NULL,
  `nota` varchar(255) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_im_producto` (`producto_id`),
  KEY `idx_im_fecha` (`created_at`),
  KEY `idx_im_ref` (`referencia_tipo`,`referencia_id`),
  KEY `fk_im_user` (`created_by`),
  CONSTRAINT `fk_im_producto` FOREIGN KEY (`producto_id`) REFERENCES `productos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_im_user` FOREIGN KEY (`created_by`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `inventario_movimientos`
--

LOCK TABLES `inventario_movimientos` WRITE;
/*!40000 ALTER TABLE `inventario_movimientos` DISABLE KEYS */;
INSERT INTO `inventario_movimientos` VALUES (1,3,'salida',1,100,99,'pedido',16,'Descuento por creacion de pedido',4,'2026-04-20 11:49:10'),(2,4,'salida',3,100,97,'pedido',16,'Descuento por creacion de pedido',4,'2026-04-20 11:49:10'),(3,1,'salida',6,100,94,'pedido',17,'Descuento por creacion de pedido',4,'2026-04-20 14:26:30'),(4,2,'salida',2,100,98,'pedido',17,'Descuento por creacion de pedido',4,'2026-04-20 14:26:30'),(5,3,'salida',4,99,95,'pedido',17,'Descuento por creacion de pedido',4,'2026-04-20 14:26:30'),(6,6,'salida',1,100,99,'pedido',17,'Descuento por creacion de pedido',4,'2026-04-20 14:26:30'),(7,3,'salida',1,95,94,'pedido',18,'Descuento por creacion de pedido',4,'2026-05-12 15:04:44'),(8,4,'salida',3,97,94,'pedido',18,'Descuento por creacion de pedido',4,'2026-05-12 15:04:44'),(9,2,'salida',1,98,97,'pedido',19,'Descuento por creacion de pedido',4,'2026-05-13 03:18:55'),(10,3,'salida',1,94,93,'pedido',19,'Descuento por creacion de pedido',4,'2026-05-13 03:18:55'),(11,4,'salida',4,94,90,'pedido',20,'Descuento por creacion de pedido',4,'2026-05-13 13:34:38');
/*!40000 ALTER TABLE `inventario_movimientos` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `login_throttles`
--

DROP TABLE IF EXISTS `login_throttles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `login_throttles` (
  `bucket` varchar(190) NOT NULL,
  `hits` int(11) NOT NULL DEFAULT 0,
  `window_start` datetime NOT NULL,
  `blocked_until` datetime DEFAULT NULL,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`bucket`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `login_throttles`
--

LOCK TABLES `login_throttles` WRITE;
/*!40000 ALTER TABLE `login_throttles` DISABLE KEYS */;
INSERT INTO `login_throttles` VALUES ('login:email:19fb9bb6430c5d375c60be9acc1abfc2408343af4271ba44a5e5dc6913136e67',1,'2026-05-13 10:56:36',NULL,'2026-05-13 02:56:36'),('login:email:24d250b246914a29030b3a4540f31b7da0ac717284feb1628efb87d5593dd46f',2,'2026-04-20 20:17:07',NULL,'2026-04-20 14:17:13');
/*!40000 ALTER TABLE `login_throttles` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `logistica_citas`
--

DROP TABLE IF EXISTS `logistica_citas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `logistica_citas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `pedido_id` int(11) DEFAULT NULL,
  `pl_folio` varchar(50) DEFAULT NULL,
  `linea_id` int(11) NOT NULL,
  `fecha_cita` datetime NOT NULL,
  `estado` enum('programada','completada','cancelada') DEFAULT 'programada',
  `created_at` datetime DEFAULT current_timestamp(),
  `operador_id` int(11) DEFAULT NULL,
  `carga_kg` int(11) DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `logistica_citas`
--

LOCK TABLES `logistica_citas` WRITE;
/*!40000 ALTER TABLE `logistica_citas` DISABLE KEYS */;
INSERT INTO `logistica_citas` VALUES (2,NULL,'MULTI-PED',2,'2026-05-14 08:00:00','programada','2026-05-14 02:30:22',2,1175);
/*!40000 ALTER TABLE `logistica_citas` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `logistica_eventos`
--

DROP TABLE IF EXISTS `logistica_eventos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `logistica_eventos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `lote_id` int(11) NOT NULL,
  `usuario_id` int(11) DEFAULT NULL,
  `tipo_evento` varchar(60) NOT NULL,
  `detalle` varchar(500) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_le_lote` (`lote_id`),
  KEY `fk_le_usuario` (`usuario_id`),
  CONSTRAINT `fk_le_lote` FOREIGN KEY (`lote_id`) REFERENCES `logistica_lotes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_le_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `logistica_eventos`
--

LOCK TABLES `logistica_eventos` WRITE;
/*!40000 ALTER TABLE `logistica_eventos` DISABLE KEYS */;
INSERT INTO `logistica_eventos` VALUES (1,1,1,'lote_creado','Lote creado con asignacion sugerida automatica','2026-05-12 15:43:13'),(2,2,1,'lote_creado','Lote creado con asignacion sugerida automatica','2026-05-14 01:24:51');
/*!40000 ALTER TABLE `logistica_eventos` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `logistica_lineas`
--

DROP TABLE IF EXISTS `logistica_lineas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `logistica_lineas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(100) NOT NULL,
  `prioridad` int(11) DEFAULT 1,
  `rastreo_tiempo_real` tinyint(1) DEFAULT 0,
  `horario_carga_inicio` time DEFAULT '08:00:00',
  `horario_carga_fin` time DEFAULT '18:00:00',
  `dias_operacion` varchar(50) DEFAULT 'Lunes-Sábado',
  `promedio_carga_min` int(11) DEFAULT 60,
  `requiere_cita` tinyint(1) DEFAULT 1,
  `anticipacion_agenda_hrs` int(11) DEFAULT 24,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `logistica_lineas`
--

LOCK TABLES `logistica_lineas` WRITE;
/*!40000 ALTER TABLE `logistica_lineas` DISABLE KEYS */;
INSERT INTO `logistica_lineas` VALUES (1,'Transportes Rojas',1,1,'08:00:00','18:00:00','Lunes-Sábado',60,1,12,'2026-05-14 01:19:21'),(2,'Loxagon',2,1,'08:00:00','18:00:00','Lunes-Sábado',60,1,24,'2026-05-14 01:19:21'),(3,'Bisonte',3,0,'08:00:00','18:00:00','Lunes-Sábado',60,1,12,'2026-05-14 01:19:21'),(4,'Tres Guerras',4,1,'08:00:00','18:00:00','Lunes-Sábado',60,1,48,'2026-05-14 01:19:21');
/*!40000 ALTER TABLE `logistica_lineas` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `logistica_lote_operadores`
--

DROP TABLE IF EXISTS `logistica_lote_operadores`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `logistica_lote_operadores` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `lote_id` int(11) NOT NULL,
  `operador_id` int(11) NOT NULL,
  `camion_alias` varchar(80) DEFAULT NULL,
  `capacidad_cajas` int(11) NOT NULL DEFAULT 25,
  `cajas_asignadas` int(11) NOT NULL DEFAULT 0,
  `hora_salida` datetime DEFAULT NULL,
  `hora_entrega` datetime DEFAULT NULL,
  `estado` enum('pendiente','cargando','en_ruta','cerrado') NOT NULL DEFAULT 'pendiente',
  `checklist_salida_json` text DEFAULT NULL,
  `checklist_cierre_json` text DEFAULT NULL,
  `fecha_operacion` date DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `ux_llo_lote_operador` (`lote_id`,`operador_id`),
  KEY `idx_llo_estado` (`estado`),
  KEY `fk_llo_operador` (`operador_id`),
  CONSTRAINT `fk_llo_lote` FOREIGN KEY (`lote_id`) REFERENCES `logistica_lotes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_llo_operador` FOREIGN KEY (`operador_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `logistica_lote_operadores`
--

LOCK TABLES `logistica_lote_operadores` WRITE;
/*!40000 ALTER TABLE `logistica_lote_operadores` DISABLE KEYS */;
INSERT INTO `logistica_lote_operadores` VALUES (1,1,3,'Camion Ana Martínez',25,1,NULL,NULL,'pendiente',NULL,NULL,NULL,'2026-05-12 15:43:13','2026-05-12 15:43:13'),(2,1,2,'Camion Carlos López',25,0,NULL,NULL,'pendiente',NULL,NULL,NULL,'2026-05-12 15:43:13','2026-05-12 15:43:13'),(3,2,3,'Camion Ana Martínez',25,2,NULL,NULL,'pendiente',NULL,NULL,NULL,'2026-05-14 01:24:50','2026-05-14 01:24:51'),(4,2,2,'Camion Carlos López',25,1,NULL,NULL,'pendiente',NULL,NULL,NULL,'2026-05-14 01:24:50','2026-05-14 01:24:51');
/*!40000 ALTER TABLE `logistica_lote_operadores` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `logistica_lote_pedidos`
--

DROP TABLE IF EXISTS `logistica_lote_pedidos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `logistica_lote_pedidos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `lote_id` int(11) NOT NULL,
  `pedido_id` int(11) NOT NULL,
  `ruta_grupo` varchar(80) DEFAULT NULL,
  `prioridad_ruta` int(11) NOT NULL DEFAULT 100,
  `cajas_sugeridas` int(11) NOT NULL DEFAULT 1,
  `cajas_asignadas` int(11) NOT NULL DEFAULT 1,
  `operador_id` int(11) DEFAULT NULL,
  `estado` enum('sugerido','asignado','en_ruta','entregado_parcial','entregado','cancelado') NOT NULL DEFAULT 'sugerido',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `ux_llp_lote_pedido` (`lote_id`,`pedido_id`),
  KEY `idx_llp_operador` (`operador_id`),
  KEY `idx_llp_estado` (`estado`),
  KEY `fk_llp_pedido` (`pedido_id`),
  CONSTRAINT `fk_llp_lote` FOREIGN KEY (`lote_id`) REFERENCES `logistica_lotes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_llp_operador` FOREIGN KEY (`operador_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_llp_pedido` FOREIGN KEY (`pedido_id`) REFERENCES `pedidos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `logistica_lote_pedidos`
--

LOCK TABLES `logistica_lote_pedidos` WRITE;
/*!40000 ALTER TABLE `logistica_lote_pedidos` DISABLE KEYS */;
INSERT INTO `logistica_lote_pedidos` VALUES (1,1,18,'av lvaro obreg n 88 col roma cdmx',1,1,1,3,'sugerido','2026-05-12 15:43:13','2026-05-12 15:43:13'),(2,2,18,'av lvaro obreg n 88 col roma cdmx',1,1,1,3,'sugerido','2026-05-14 01:24:50','2026-05-14 01:24:50'),(3,2,19,'av lvaro obreg n 88 col roma cdmx',2,1,1,2,'sugerido','2026-05-14 01:24:51','2026-05-14 01:24:51'),(4,2,20,'av lvaro obreg n 88 col roma cdmx',3,1,1,3,'sugerido','2026-05-14 01:24:51','2026-05-14 01:24:51');
/*!40000 ALTER TABLE `logistica_lote_pedidos` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `logistica_lotes`
--

DROP TABLE IF EXISTS `logistica_lotes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `logistica_lotes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `folio` varchar(40) NOT NULL,
  `manager_id` int(11) NOT NULL,
  `modo_envio` enum('flotilla','paqueteria') NOT NULL DEFAULT 'flotilla',
  `paqueteria` varchar(80) DEFAULT NULL,
  `costo_pct` decimal(8,2) NOT NULL DEFAULT 0.00,
  `costo_por_caja` decimal(12,2) NOT NULL DEFAULT 0.00,
  `costo_estimado_total` decimal(14,2) NOT NULL DEFAULT 0.00,
  `estado` enum('borrador','pendiente_aceptacion','aceptado','en_embarque','cerrado','cancelado') NOT NULL DEFAULT 'borrador',
  `fecha_salida_programada` date DEFAULT NULL,
  `fecha_entrega_estimada` date DEFAULT NULL,
  `hora_salida` datetime DEFAULT NULL,
  `hora_cierre` datetime DEFAULT NULL,
  `observaciones` varchar(500) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `folio` (`folio`),
  KEY `idx_ll_estado` (`estado`),
  KEY `idx_ll_manager` (`manager_id`),
  CONSTRAINT `fk_ll_manager` FOREIGN KEY (`manager_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `logistica_lotes`
--

LOCK TABLES `logistica_lotes` WRITE;
/*!40000 ALTER TABLE `logistica_lotes` DISABLE KEYS */;
INSERT INTO `logistica_lotes` VALUES (1,'LM-20260512-234313',1,'paqueteria',NULL,0.00,0.00,0.00,'borrador',NULL,NULL,NULL,NULL,NULL,'2026-05-12 15:43:13','2026-05-12 15:43:13'),(2,'LM-20260514-092450',1,'flotilla',NULL,0.00,0.00,0.00,'borrador',NULL,NULL,NULL,NULL,NULL,'2026-05-14 01:24:50','2026-05-14 01:24:50');
/*!40000 ALTER TABLE `logistica_lotes` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `logistica_rutas`
--

DROP TABLE IF EXISTS `logistica_rutas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `logistica_rutas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `linea_id` int(11) NOT NULL,
  `origen` varchar(100) NOT NULL,
  `destino` varchar(100) NOT NULL,
  `frecuencia` varchar(50) DEFAULT 'diaria',
  `tarifa_fija` decimal(10,2) DEFAULT 0.00,
  `costo_km_adicional` decimal(10,2) DEFAULT 0.00,
  `recargo_combustible_pct` decimal(5,2) DEFAULT 0.00,
  `tiempo_estimado_hrs` int(11) DEFAULT 24,
  `tiempo_min_hrs` int(11) DEFAULT 12,
  `tiempo_max_hrs` int(11) DEFAULT 48,
  `cumplimiento_pct` int(11) DEFAULT 100,
  PRIMARY KEY (`id`),
  KEY `linea_id` (`linea_id`),
  CONSTRAINT `logistica_rutas_ibfk_1` FOREIGN KEY (`linea_id`) REFERENCES `logistica_lineas` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `logistica_rutas`
--

LOCK TABLES `logistica_rutas` WRITE;
/*!40000 ALTER TABLE `logistica_rutas` DISABLE KEYS */;
INSERT INTO `logistica_rutas` VALUES (1,1,'ALMACEN','QRO','diaria',3200.00,0.00,0.00,4,12,48,99),(2,1,'ALMACEN','CDMX','diaria',4100.00,0.00,0.00,6,12,48,97),(3,1,'ALMACEN','TIZAYUCA','diaria',5200.00,0.00,0.00,8,12,48,95),(4,2,'ALMACEN','QRO','diaria',3500.00,0.00,0.00,4,12,48,98);
/*!40000 ALTER TABLE `logistica_rutas` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `logistica_unidades`
--

DROP TABLE IF EXISTS `logistica_unidades`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `logistica_unidades` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `linea_id` int(11) NOT NULL,
  `tipo` varchar(50) NOT NULL,
  `tipo_caja` varchar(50) NOT NULL,
  `capacidad_kg` int(11) NOT NULL,
  `peso_minimo` int(11) DEFAULT 0,
  `mercancia_permitida` varchar(255) DEFAULT 'General',
  PRIMARY KEY (`id`),
  KEY `linea_id` (`linea_id`),
  CONSTRAINT `logistica_unidades_ibfk_1` FOREIGN KEY (`linea_id`) REFERENCES `logistica_lineas` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `logistica_unidades`
--

LOCK TABLES `logistica_unidades` WRITE;
/*!40000 ALTER TABLE `logistica_unidades` DISABLE KEYS */;
INSERT INTO `logistica_unidades` VALUES (1,1,'1 TON','Seca',1000,0,'General, Alimentos'),(2,1,'3.5 TON','Seca',3500,1000,'General'),(3,1,'TORTON','Seca',15000,5000,'General, Peligrosa'),(4,2,'TORTON','Seca',15000,2000,'General'),(5,3,'Tráiler','Seca',25000,5000,'General'),(6,4,'Full','Seca',50000,10000,'General');
/*!40000 ALTER TABLE `logistica_unidades` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `notificaciones_eventos`
--

DROP TABLE IF EXISTS `notificaciones_eventos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `notificaciones_eventos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `pedido_id` int(11) NOT NULL,
  `cliente_id` int(11) NOT NULL,
  `canal` enum('interno','email','whatsapp','sms','llamada') NOT NULL,
  `evento` varchar(60) NOT NULL,
  `mensaje` text NOT NULL,
  `estado` enum('pendiente','enviado','error') NOT NULL DEFAULT 'pendiente',
  `metadata_json` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `sent_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_ne_pedido` (`pedido_id`),
  KEY `idx_ne_cliente` (`cliente_id`),
  KEY `idx_ne_evento` (`evento`),
  CONSTRAINT `fk_ne_cliente` FOREIGN KEY (`cliente_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ne_pedido` FOREIGN KEY (`pedido_id`) REFERENCES `pedidos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=61 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `notificaciones_eventos`
--

LOCK TABLES `notificaciones_eventos` WRITE;
/*!40000 ALTER TABLE `notificaciones_eventos` DISABLE KEYS */;
INSERT INTO `notificaciones_eventos` VALUES (1,13,4,'interno','pedido_asignado_manual','Tu pedido fue asignado a operador y esta en proceso.','pendiente',NULL,'2026-04-19 17:21:05',NULL),(2,13,4,'whatsapp','pedido_asignado_manual','Tu pedido fue asignado a operador y esta en proceso.','pendiente',NULL,'2026-04-19 17:21:05',NULL),(3,13,4,'sms','pedido_asignado_manual','Tu pedido fue asignado a operador y esta en proceso.','pendiente',NULL,'2026-04-19 17:21:05',NULL),(4,13,4,'email','pedido_asignado_manual','Tu pedido fue asignado a operador y esta en proceso.','pendiente',NULL,'2026-04-19 17:21:05',NULL),(5,13,4,'interno','pedido_cancelado','Tu pedido fue cancelado. Contacta a soporte para mas informacion.','pendiente',NULL,'2026-04-19 17:21:13',NULL),(6,13,4,'whatsapp','pedido_cancelado','Tu pedido fue cancelado. Contacta a soporte para mas informacion.','pendiente',NULL,'2026-04-19 17:21:13',NULL),(7,13,4,'sms','pedido_cancelado','Tu pedido fue cancelado. Contacta a soporte para mas informacion.','pendiente',NULL,'2026-04-19 17:21:13',NULL),(8,13,4,'email','pedido_cancelado','Tu pedido fue cancelado. Contacta a soporte para mas informacion.','pendiente',NULL,'2026-04-19 17:21:13',NULL),(9,14,4,'interno','pedido_asignado_manual','Tu pedido fue asignado a operador y esta en proceso.','pendiente',NULL,'2026-04-19 17:23:47',NULL),(10,14,4,'whatsapp','pedido_asignado_manual','Tu pedido fue asignado a operador y esta en proceso.','pendiente',NULL,'2026-04-19 17:23:47',NULL),(11,14,4,'sms','pedido_asignado_manual','Tu pedido fue asignado a operador y esta en proceso.','pendiente',NULL,'2026-04-19 17:23:47',NULL),(12,14,4,'email','pedido_asignado_manual','Tu pedido fue asignado a operador y esta en proceso.','pendiente',NULL,'2026-04-19 17:23:47',NULL),(13,16,4,'interno','pedido_creado','Tu pedido fue creado y esta en revision.','pendiente','{\"tipo_pedido\":\"formal\",\"fecha_requerida\":null,\"total\":7350}','2026-04-20 11:49:10',NULL),(14,16,4,'whatsapp','pedido_creado','Tu pedido fue creado y esta en revision.','pendiente','{\"tipo_pedido\":\"formal\",\"fecha_requerida\":null,\"total\":7350}','2026-04-20 11:49:10',NULL),(15,16,4,'sms','pedido_creado','Tu pedido fue creado y esta en revision.','pendiente','{\"tipo_pedido\":\"formal\",\"fecha_requerida\":null,\"total\":7350}','2026-04-20 11:49:10',NULL),(16,16,4,'email','pedido_creado','Tu pedido fue creado y esta en revision.','pendiente','{\"tipo_pedido\":\"formal\",\"fecha_requerida\":null,\"total\":7350}','2026-04-20 11:49:10',NULL),(17,16,4,'interno','pedido_asignado_manual','Tu pedido fue asignado a operador y esta en proceso.','pendiente',NULL,'2026-04-20 11:49:47',NULL),(18,16,4,'whatsapp','pedido_asignado_manual','Tu pedido fue asignado a operador y esta en proceso.','pendiente',NULL,'2026-04-20 11:49:47',NULL),(19,16,4,'sms','pedido_asignado_manual','Tu pedido fue asignado a operador y esta en proceso.','pendiente',NULL,'2026-04-20 11:49:47',NULL),(20,16,4,'email','pedido_asignado_manual','Tu pedido fue asignado a operador y esta en proceso.','pendiente',NULL,'2026-04-20 11:49:47',NULL),(21,17,4,'interno','pedido_creado','Tu pedido fue creado y esta en revision.','pendiente','{\"tipo_pedido\":\"formal\",\"fecha_requerida\":null,\"total\":20880}','2026-04-20 14:26:30',NULL),(22,17,4,'whatsapp','pedido_creado','Tu pedido fue creado y esta en revision.','pendiente','{\"tipo_pedido\":\"formal\",\"fecha_requerida\":null,\"total\":20880}','2026-04-20 14:26:30',NULL),(23,17,4,'sms','pedido_creado','Tu pedido fue creado y esta en revision.','pendiente','{\"tipo_pedido\":\"formal\",\"fecha_requerida\":null,\"total\":20880}','2026-04-20 14:26:30',NULL),(24,17,4,'email','pedido_creado','Tu pedido fue creado y esta en revision.','pendiente','{\"tipo_pedido\":\"formal\",\"fecha_requerida\":null,\"total\":20880}','2026-04-20 14:26:30',NULL),(25,17,4,'interno','pedido_asignado_manual','Tu pedido fue asignado a operador y esta en proceso.','pendiente',NULL,'2026-04-20 14:45:55',NULL),(26,17,4,'whatsapp','pedido_asignado_manual','Tu pedido fue asignado a operador y esta en proceso.','pendiente',NULL,'2026-04-20 14:45:55',NULL),(27,17,4,'sms','pedido_asignado_manual','Tu pedido fue asignado a operador y esta en proceso.','pendiente',NULL,'2026-04-20 14:45:55',NULL),(28,17,4,'email','pedido_asignado_manual','Tu pedido fue asignado a operador y esta en proceso.','pendiente',NULL,'2026-04-20 14:45:55',NULL),(29,17,4,'interno','pedido_en_camino','Tu pedido salio a ruta y va en camino.','pendiente',NULL,'2026-04-21 23:30:43',NULL),(30,17,4,'whatsapp','pedido_en_camino','Tu pedido salio a ruta y va en camino.','pendiente',NULL,'2026-04-21 23:30:43',NULL),(31,17,4,'sms','pedido_en_camino','Tu pedido salio a ruta y va en camino.','pendiente',NULL,'2026-04-21 23:30:43',NULL),(32,17,4,'email','pedido_en_camino','Tu pedido salio a ruta y va en camino.','pendiente',NULL,'2026-04-21 23:30:43',NULL),(33,14,4,'interno','pedido_en_camino','Tu pedido salio a ruta y va en camino.','pendiente',NULL,'2026-04-21 23:47:08',NULL),(34,14,4,'whatsapp','pedido_en_camino','Tu pedido salio a ruta y va en camino.','pendiente',NULL,'2026-04-21 23:47:08',NULL),(35,14,4,'sms','pedido_en_camino','Tu pedido salio a ruta y va en camino.','pendiente',NULL,'2026-04-21 23:47:08',NULL),(36,14,4,'email','pedido_en_camino','Tu pedido salio a ruta y va en camino.','pendiente',NULL,'2026-04-21 23:47:08',NULL),(37,14,4,'interno','pedido_entregado','Tu pedido fue entregado correctamente.','pendiente',NULL,'2026-04-21 23:52:04',NULL),(38,14,4,'whatsapp','pedido_entregado','Tu pedido fue entregado correctamente.','pendiente',NULL,'2026-04-21 23:52:04',NULL),(39,14,4,'sms','pedido_entregado','Tu pedido fue entregado correctamente.','pendiente',NULL,'2026-04-21 23:52:04',NULL),(40,14,4,'email','pedido_entregado','Tu pedido fue entregado correctamente.','pendiente',NULL,'2026-04-21 23:52:04',NULL),(41,17,4,'interno','pedido_entregado','Tu pedido fue entregado correctamente.','pendiente',NULL,'2026-04-21 23:53:38',NULL),(42,17,4,'whatsapp','pedido_entregado','Tu pedido fue entregado correctamente.','pendiente',NULL,'2026-04-21 23:53:38',NULL),(43,17,4,'sms','pedido_entregado','Tu pedido fue entregado correctamente.','pendiente',NULL,'2026-04-21 23:53:38',NULL),(44,17,4,'email','pedido_entregado','Tu pedido fue entregado correctamente.','pendiente',NULL,'2026-04-21 23:53:38',NULL),(45,18,4,'interno','pedido_creado','Tu pedido fue creado y esta en revision.','pendiente','{\"tipo_pedido\":\"formal\",\"fecha_requerida\":null,\"total\":7350}','2026-05-12 15:04:44',NULL),(46,18,4,'whatsapp','pedido_creado','Tu pedido fue creado y esta en revision.','pendiente','{\"tipo_pedido\":\"formal\",\"fecha_requerida\":null,\"total\":7350}','2026-05-12 15:04:44',NULL),(47,18,4,'sms','pedido_creado','Tu pedido fue creado y esta en revision.','pendiente','{\"tipo_pedido\":\"formal\",\"fecha_requerida\":null,\"total\":7350}','2026-05-12 15:04:44',NULL),(48,18,4,'email','pedido_creado','Tu pedido fue creado y esta en revision.','pendiente','{\"tipo_pedido\":\"formal\",\"fecha_requerida\":null,\"total\":7350}','2026-05-12 15:04:44',NULL),(49,19,4,'interno','pedido_creado','Tu pedido fue creado y esta en revision.','pendiente','{\"tipo_pedido\":\"formal\",\"fecha_requerida\":null,\"total\":2990}','2026-05-13 03:18:55',NULL),(50,19,4,'whatsapp','pedido_creado','Tu pedido fue creado y esta en revision.','pendiente','{\"tipo_pedido\":\"formal\",\"fecha_requerida\":null,\"total\":2990}','2026-05-13 03:18:55',NULL),(51,19,4,'sms','pedido_creado','Tu pedido fue creado y esta en revision.','pendiente','{\"tipo_pedido\":\"formal\",\"fecha_requerida\":null,\"total\":2990}','2026-05-13 03:18:55',NULL),(52,19,4,'email','pedido_creado','Tu pedido fue creado y esta en revision.','pendiente','{\"tipo_pedido\":\"formal\",\"fecha_requerida\":null,\"total\":2990}','2026-05-13 03:18:55',NULL),(53,20,4,'interno','pedido_creado','Tu pedido fue creado y esta en revision.','pendiente','{\"tipo_pedido\":\"formal\",\"fecha_requerida\":null,\"total\":7000}','2026-05-13 13:34:38',NULL),(54,20,4,'whatsapp','pedido_creado','Tu pedido fue creado y esta en revision.','pendiente','{\"tipo_pedido\":\"formal\",\"fecha_requerida\":null,\"total\":7000}','2026-05-13 13:34:38',NULL),(55,20,4,'sms','pedido_creado','Tu pedido fue creado y esta en revision.','pendiente','{\"tipo_pedido\":\"formal\",\"fecha_requerida\":null,\"total\":7000}','2026-05-13 13:34:38',NULL),(56,20,4,'email','pedido_creado','Tu pedido fue creado y esta en revision.','pendiente','{\"tipo_pedido\":\"formal\",\"fecha_requerida\":null,\"total\":7000}','2026-05-13 13:34:38',NULL),(57,20,4,'interno','pedido_en_camino','Tu pedido salio a ruta y va en camino.','pendiente',NULL,'2026-05-14 02:38:34',NULL),(58,20,4,'whatsapp','pedido_en_camino','Tu pedido salio a ruta y va en camino.','pendiente',NULL,'2026-05-14 02:38:34',NULL),(59,20,4,'sms','pedido_en_camino','Tu pedido salio a ruta y va en camino.','pendiente',NULL,'2026-05-14 02:38:34',NULL),(60,20,4,'email','pedido_en_camino','Tu pedido salio a ruta y va en camino.','pendiente',NULL,'2026-05-14 02:38:34',NULL);
/*!40000 ALTER TABLE `notificaciones_eventos` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `pedido_evidencias`
--

DROP TABLE IF EXISTS `pedido_evidencias`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `pedido_evidencias` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `pedido_id` int(11) NOT NULL,
  `foto_url` varchar(255) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_pe_pedido` (`pedido_id`),
  CONSTRAINT `fk_pe_pedido` FOREIGN KEY (`pedido_id`) REFERENCES `pedidos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `pedido_evidencias`
--

LOCK TABLES `pedido_evidencias` WRITE;
/*!40000 ALTER TABLE `pedido_evidencias` DISABLE KEYS */;
INSERT INTO `pedido_evidencias` VALUES (1,14,'/uploads/evidencias/ev_69e86203de939_14.png','2026-04-21 23:52:05'),(2,14,'/uploads/evidencias/ev_69e86203deccd_14.png','2026-04-21 23:52:05'),(3,14,'/uploads/evidencias/ev_69e86203dfe10_14.png','2026-04-21 23:52:05'),(4,17,'/uploads/evidencias/ev_69e86262017c6_17.png','2026-04-21 23:53:38'),(5,17,'/uploads/evidencias/ev_69e8626201b16_17.png','2026-04-21 23:53:38'),(6,17,'/uploads/evidencias/ev_69e8626201e4e_17.png','2026-04-21 23:53:38');
/*!40000 ALTER TABLE `pedido_evidencias` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `pedido_historial_estados`
--

DROP TABLE IF EXISTS `pedido_historial_estados`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `pedido_historial_estados` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `pedido_id` int(11) NOT NULL,
  `estado` varchar(40) NOT NULL,
  `nota` varchar(255) DEFAULT NULL,
  `usuario_id` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_phe_pedido` (`pedido_id`),
  KEY `idx_phe_estado` (`estado`),
  KEY `fk_phe_usuario` (`usuario_id`),
  CONSTRAINT `fk_phe_pedido` FOREIGN KEY (`pedido_id`) REFERENCES `pedidos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_phe_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=31 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `pedido_historial_estados`
--

LOCK TABLES `pedido_historial_estados` WRITE;
/*!40000 ALTER TABLE `pedido_historial_estados` DISABLE KEYS */;
INSERT INTO `pedido_historial_estados` VALUES (1,13,'aceptado','Asignado manualmente a operador: Carlos López (distancia: 40.49 km) [fuera de rango autorizado]',1,'2026-04-19 17:21:05'),(2,13,'cancelado','Cancelado por coordinacion desde resumen de pedido',1,'2026-04-19 17:21:13'),(3,13,'cancelado','Cambio de estado desde panel admin',1,'2026-04-19 17:21:20'),(4,11,'en_camino','Cambio de estado desde panel admin',1,'2026-04-19 17:21:25'),(5,14,'pendiente','Cambio de estado desde panel admin',1,'2026-04-19 17:22:08'),(6,14,'aceptado','Asignado manualmente a operador: Carlos López (distancia: 40.49 km) [fuera de rango autorizado]',1,'2026-04-19 17:23:47'),(7,15,'cancelado','Cambio de estado desde panel admin',1,'2026-04-19 17:23:58'),(8,16,'pendiente','Pedido creado por cliente',4,'2026-04-20 11:49:10'),(9,16,'aceptado','Asignado manualmente a operador: Carlos López (distancia: 40.49 km) [fuera de rango autorizado]',1,'2026-04-20 11:49:47'),(10,15,'entregado','Cambio de estado desde panel admin',1,'2026-04-20 11:54:39'),(11,13,'entregado','Cambio de estado desde panel admin',1,'2026-04-20 11:54:44'),(12,11,'entregado','Cambio de estado desde panel admin',1,'2026-04-20 11:54:50'),(13,9,'entregado','Cambio de estado desde panel admin',1,'2026-04-20 11:54:55'),(14,5,'entregado','Cambio de estado desde panel admin',1,'2026-04-20 11:55:01'),(15,16,'en_camino','Cambio de estado desde panel admin',1,'2026-04-20 11:55:08'),(16,17,'pendiente','Pedido creado por cliente',4,'2026-04-20 14:26:30'),(17,17,'aceptado','Asignado manualmente a operador: Carlos López (distancia: 40.49 km) [fuera de rango autorizado]',1,'2026-04-20 14:45:55'),(18,17,'en_camino','Operador inicio ruta',2,'2026-04-21 23:30:43'),(19,14,'en_camino','Operador inicio ruta',2,'2026-04-21 23:47:08'),(20,14,'entregado','Entrega confirmada',2,'2026-04-21 23:52:04'),(21,17,'entregado','Entrega confirmada',2,'2026-04-21 23:53:38'),(22,16,'cancelado','Cambio de estado desde panel admin',1,'2026-05-12 14:12:16'),(23,17,'cancelado','Cambio de estado desde panel admin',1,'2026-05-12 14:12:18'),(24,15,'cancelado','Cambio de estado desde panel admin',1,'2026-05-12 14:12:21'),(25,18,'pendiente','Pedido creado por cliente',4,'2026-05-12 15:04:44'),(26,18,'aceptado','Asignacion automatica por cercania en km',3,'2026-05-12 15:04:44'),(27,19,'pendiente','Pedido creado por cliente',4,'2026-05-13 03:18:55'),(28,19,'aceptado','Asignacion automatica por cercania en km',3,'2026-05-13 03:18:55'),(29,20,'pendiente','Pedido creado por cliente',4,'2026-05-13 13:34:38'),(30,20,'en_camino','Viaje iniciado automaticamente por autocorreccion operativa',2,'2026-05-14 02:38:34');
/*!40000 ALTER TABLE `pedido_historial_estados` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `pedido_items`
--

DROP TABLE IF EXISTS `pedido_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `pedido_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `pedido_id` int(11) NOT NULL,
  `producto_id` int(11) NOT NULL,
  `cantidad` int(11) NOT NULL DEFAULT 1,
  `precio_unit` decimal(10,2) NOT NULL,
  `ajuste_cliente` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `pedido_id` (`pedido_id`),
  KEY `producto_id` (`producto_id`),
  CONSTRAINT `pedido_items_ibfk_1` FOREIGN KEY (`pedido_id`) REFERENCES `pedidos` (`id`),
  CONSTRAINT `pedido_items_ibfk_2` FOREIGN KEY (`producto_id`) REFERENCES `productos` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=58 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `pedido_items`
--

LOCK TABLES `pedido_items` WRITE;
/*!40000 ALTER TABLE `pedido_items` DISABLE KEYS */;
INSERT INTO `pedido_items` VALUES (1,1,1,1,1250.00,NULL),(2,1,5,1,1980.00,NULL),(3,1,12,1,1820.00,NULL),(4,2,1,2,1250.00,NULL),(5,2,22,1,1620.00,NULL),(6,2,27,1,6200.00,NULL),(7,3,5,1,1980.00,NULL),(8,3,18,1,2750.00,NULL),(9,3,20,1,1480.00,NULL),(10,3,25,1,2350.00,NULL),(11,3,26,1,5800.00,NULL),(12,4,1,1,1250.00,NULL),(13,4,2,1,890.00,NULL),(14,4,9,1,2800.00,NULL),(15,4,10,1,1650.00,NULL),(16,4,11,1,2200.00,NULL),(17,4,12,1,1820.00,NULL),(18,4,15,1,4500.00,NULL),(19,4,17,1,920.00,NULL),(20,5,1,1,1250.00,NULL),(21,5,2,1,890.00,NULL),(22,5,16,1,1350.00,NULL),(23,5,17,1,920.00,NULL),(24,6,1,1,1250.00,NULL),(25,6,9,1,2800.00,NULL),(26,6,10,1,1650.00,NULL),(27,7,1,1,1250.00,NULL),(28,8,4,1,1750.00,NULL),(29,8,5,1,1980.00,NULL),(30,9,3,1,2100.00,NULL),(31,9,5,1,1980.00,NULL),(32,9,6,1,3200.00,NULL),(33,10,1,1,1250.00,NULL),(34,10,2,1,890.00,NULL),(35,10,3,1,2100.00,NULL),(36,11,2,1,890.00,NULL),(37,11,3,1,2100.00,NULL),(38,11,4,1,1750.00,NULL),(39,12,3,1,2100.00,NULL),(40,12,4,3,1750.00,NULL),(41,13,1,4,1250.00,NULL),(42,13,2,7,890.00,NULL),(43,13,6,1,3200.00,NULL),(44,14,29,4,1112.00,NULL),(45,15,5,1,1980.00,NULL),(46,15,8,20,560.00,NULL),(47,16,3,1,2100.00,NULL),(48,16,4,3,1750.00,NULL),(49,17,1,6,1250.00,NULL),(50,17,2,2,890.00,NULL),(51,17,3,4,2100.00,NULL),(52,17,6,1,3200.00,NULL),(53,18,3,1,2100.00,NULL),(54,18,4,3,1750.00,NULL),(55,19,2,1,890.00,NULL),(56,19,3,1,2100.00,NULL),(57,20,4,4,1750.00,NULL);
/*!40000 ALTER TABLE `pedido_items` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `pedidos`
--

DROP TABLE IF EXISTS `pedidos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `pedidos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `cliente_id` int(11) NOT NULL,
  `operador_id` int(11) DEFAULT NULL,
  `estado` enum('pendiente','aceptado','en_camino','entregado','cancelado') DEFAULT 'pendiente',
  `persona_recibe` varchar(120) DEFAULT NULL,
  `estado_paquete` varchar(80) DEFAULT NULL,
  `tipo_pedido` enum('formal','informal') NOT NULL DEFAULT 'formal',
  `prioridad` enum('baja','media','alta','urgente') NOT NULL DEFAULT 'media',
  `total` decimal(10,2) DEFAULT 0.00,
  `lat_entrega` double DEFAULT NULL,
  `lng_entrega` double DEFAULT NULL,
  `domicilio_entrega` varchar(255) DEFAULT NULL,
  `fecha_requerida` datetime DEFAULT NULL,
  `fecha_programada` datetime DEFAULT NULL,
  `transporte_linea` varchar(120) DEFAULT NULL,
  `pl_documento` varchar(140) DEFAULT NULL,
  `area_flujo` enum('ventas','embarque','logistica','entrega') NOT NULL DEFAULT 'ventas',
  `cfdi_status` enum('pendiente','timbrado','error') NOT NULL DEFAULT 'pendiente',
  `cfdi_uuid` varchar(64) DEFAULT NULL,
  `cfdi_pdf_url` varchar(255) DEFAULT NULL,
  `cfdi_xml_url` varchar(255) DEFAULT NULL,
  `notas` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `destino_demo` varchar(100) DEFAULT 'QRO',
  `ruta_punto` int(11) DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `cliente_id` (`cliente_id`),
  KEY `operador_id` (`operador_id`),
  KEY `idx_pedidos_tipo` (`tipo_pedido`),
  KEY `idx_pedidos_prioridad` (`prioridad`),
  KEY `idx_pedidos_fecha_programada` (`fecha_programada`),
  CONSTRAINT `pedidos_ibfk_1` FOREIGN KEY (`cliente_id`) REFERENCES `usuarios` (`id`),
  CONSTRAINT `pedidos_ibfk_2` FOREIGN KEY (`operador_id`) REFERENCES `usuarios` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=21 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `pedidos`
--

LOCK TABLES `pedidos` WRITE;
/*!40000 ALTER TABLE `pedidos` DISABLE KEYS */;
INSERT INTO `pedidos` VALUES (1,4,2,'entregado',NULL,NULL,'formal','media',5050.00,19.7,-98.98,'Av. Álvaro Obregón 88, Col. Roma, CDMX',NULL,NULL,NULL,NULL,'ventas','pendiente',NULL,NULL,NULL,NULL,'2026-03-23 16:54:45','2026-04-29 05:11:14','QRO',0),(2,4,2,'entregado',NULL,NULL,'formal','media',10320.00,19.7,-98.98,'Av. Álvaro Obregón 88, Col. Roma, CDMX',NULL,NULL,NULL,NULL,'ventas','pendiente',NULL,NULL,NULL,NULL,'2026-03-23 17:08:16','2026-04-29 05:11:14','QRO',0),(3,4,2,'entregado',NULL,NULL,'formal','media',14360.00,19.7,-98.98,'Av. Álvaro Obregón 88, Col. Roma, CDMX',NULL,NULL,NULL,NULL,'ventas','pendiente',NULL,NULL,NULL,NULL,'2026-03-23 17:13:46','2026-04-29 05:11:14','QRO',0),(4,4,2,'entregado',NULL,NULL,'formal','media',16030.00,19.7,-98.98,'Av. Álvaro Obregón 88, Col. Roma, CDMX',NULL,NULL,NULL,NULL,'ventas','pendiente',NULL,NULL,NULL,NULL,'2026-03-23 17:14:14','2026-04-29 05:11:14','QRO',0),(5,5,2,'entregado',NULL,NULL,'formal','media',4410.00,20.2690825,-97.5251407,'Ojo de agua',NULL,NULL,NULL,NULL,'ventas','pendiente',NULL,NULL,NULL,NULL,'2026-03-23 17:21:07','2026-04-20 11:55:01','QRO',0),(6,4,2,'entregado',NULL,NULL,'formal','media',5700.00,19.7,-98.98,'Av. Álvaro Obregón 88, Col. Roma, CDMX',NULL,NULL,NULL,NULL,'ventas','pendiente',NULL,NULL,NULL,NULL,'2026-03-23 17:22:21','2026-04-29 05:11:14','QRO',0),(7,5,2,'entregado',NULL,NULL,'formal','media',1250.00,20.2690825,-97.5251407,'Zocalo',NULL,NULL,NULL,NULL,'ventas','pendiente',NULL,NULL,NULL,NULL,'2026-03-23 17:30:33','2026-03-23 17:40:18','QRO',0),(8,4,2,'entregado',NULL,NULL,'formal','media',3730.00,19.7,-98.98,'Av. Álvaro Obregón 88, Col. Roma, CDMX',NULL,NULL,NULL,NULL,'ventas','pendiente',NULL,NULL,NULL,NULL,'2026-03-23 20:43:36','2026-04-29 05:11:14','QRO',0),(9,4,2,'entregado',NULL,NULL,'formal','media',7280.00,19.7,-98.98,'Av. Álvaro Obregón 88, Col. Roma, CDMX',NULL,NULL,NULL,NULL,'ventas','pendiente',NULL,NULL,NULL,NULL,'2026-03-23 22:09:55','2026-04-29 05:11:14','QRO',0),(10,4,2,'aceptado',NULL,NULL,'formal','media',4240.00,19.7,-98.98,'Av. Álvaro Obregón 88, Col. Roma, CDMX',NULL,NULL,NULL,NULL,'embarque','pendiente',NULL,NULL,NULL,NULL,'2026-04-09 09:56:35','2026-05-14 02:30:22','QRO',0),(11,4,2,'aceptado',NULL,NULL,'formal','media',4740.00,19.7,-98.98,'Av. Álvaro Obregón 88, Col. Roma, CDMX',NULL,NULL,NULL,NULL,'embarque','pendiente',NULL,NULL,NULL,NULL,'2026-04-09 09:57:50','2026-05-14 02:30:22','QRO',0),(12,4,2,'aceptado',NULL,NULL,'formal','media',7350.00,19.7,-98.98,'Av. Álvaro Obregón 88, Col. Roma, CDMX',NULL,NULL,NULL,NULL,'embarque','pendiente',NULL,NULL,NULL,NULL,'2026-04-09 18:26:52','2026-05-14 02:30:22','QRO',0),(13,4,2,'aceptado',NULL,NULL,'formal','media',14430.00,19.7,-98.98,'Av. Álvaro Obregón 88, Col. Roma, CDMX',NULL,NULL,NULL,NULL,'embarque','pendiente',NULL,NULL,NULL,NULL,'2026-04-10 03:18:49','2026-05-14 02:30:22','QRO',0),(14,4,2,'aceptado','Jareth','Excelente (Sin danos)','formal','media',4448.00,19.7,-98.98,'Av. Álvaro Obregón 88, Col. Roma, CDMX',NULL,NULL,NULL,NULL,'embarque','pendiente',NULL,NULL,NULL,NULL,'2026-04-10 11:06:12','2026-05-14 02:30:22','QRO',0),(15,4,2,'aceptado',NULL,NULL,'formal','media',13180.00,19.7,-98.98,'entregar en la semana 17',NULL,NULL,NULL,NULL,'embarque','pendiente',NULL,NULL,NULL,NULL,'2026-04-16 10:13:04','2026-05-14 02:30:22','QRO',0),(16,4,2,'cancelado',NULL,NULL,'formal','media',7350.00,19.7,-98.98,'ojo de agua pallide real del cid',NULL,NULL,NULL,NULL,'ventas','pendiente',NULL,NULL,NULL,'Ultima cerrada','2026-04-20 17:49:10','2026-05-12 14:12:16','QRO',0),(17,4,2,'cancelado','jareth','Excelente (Sin danos)','formal','media',20880.00,19.7,-98.98,'Av. Álvaro Obregón 88, Col. Roma, CDMX',NULL,NULL,NULL,NULL,'ventas','pendiente',NULL,NULL,NULL,NULL,'2026-04-20 20:26:30','2026-05-12 14:12:18','QRO',0),(18,4,3,'aceptado',NULL,NULL,'formal','media',7350.00,19.7,-98.98,'Av. Álvaro Obregón 88, Col. Roma, CDMX',NULL,NULL,NULL,NULL,'ventas','pendiente',NULL,NULL,NULL,NULL,'2026-05-12 23:04:44','2026-05-12 23:04:44','QRO',0),(19,4,3,'aceptado',NULL,NULL,'formal','media',2990.00,19.7,-98.98,'Av. Álvaro Obregón 88, Col. Roma, CDMX',NULL,NULL,NULL,NULL,'ventas','pendiente',NULL,NULL,NULL,NULL,'2026-05-13 11:18:54','2026-05-13 11:18:54','QRO',0),(20,4,2,'en_camino',NULL,NULL,'formal','media',7000.00,19.7,-98.98,'Av. Álvaro Obregón 88, Col. Roma, CDMX',NULL,NULL,NULL,NULL,'embarque','pendiente',NULL,NULL,NULL,NULL,'2026-05-13 21:34:38','2026-05-14 02:38:34','QRO',0);
/*!40000 ALTER TABLE `pedidos` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `producto_lotes`
--

DROP TABLE IF EXISTS `producto_lotes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `producto_lotes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `producto_id` int(11) NOT NULL,
  `lote_numero` int(11) NOT NULL,
  `fecha_ingreso` datetime NOT NULL,
  `fecha_caducidad` datetime NOT NULL,
  `unidades` int(11) NOT NULL DEFAULT 0,
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `ux_lote_producto_numero` (`producto_id`,`lote_numero`),
  KEY `idx_lote_producto` (`producto_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `producto_lotes`
--

LOCK TABLES `producto_lotes` WRITE;
/*!40000 ALTER TABLE `producto_lotes` DISABLE KEYS */;
/*!40000 ALTER TABLE `producto_lotes` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `productos`
--

DROP TABLE IF EXISTS `productos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `productos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `codigo` varchar(50) NOT NULL,
  `nombre` varchar(255) NOT NULL,
  `precio` decimal(10,2) NOT NULL DEFAULT 0.00,
  `stock` int(11) DEFAULT 100,
  `unidad_medida` varchar(20) NOT NULL DEFAULT 'piezas',
  `fecha_caducidad` date DEFAULT NULL,
  `fecha_ingreso` datetime DEFAULT NULL,
  `lote_activo_id` int(11) DEFAULT NULL,
  `activo` tinyint(1) DEFAULT 1,
  `imagen` varchar(100) DEFAULT NULL,
  `especie_id` int(11) DEFAULT NULL,
  `subcategoria` varchar(80) DEFAULT NULL,
  `cliente_ajustable` tinyint(1) NOT NULL DEFAULT 1,
  `peso_kg` decimal(10,2) DEFAULT 25.00,
  `es_peligroso` tinyint(1) DEFAULT 0,
  `categoria` varchar(50) DEFAULT 'General',
  PRIMARY KEY (`id`),
  UNIQUE KEY `codigo` (`codigo`),
  KEY `idx_productos_especie` (`especie_id`),
  KEY `idx_productos_subcategoria` (`subcategoria`),
  KEY `idx_productos_lote_activo` (`lote_activo_id`)
) ENGINE=InnoDB AUTO_INCREMENT=30 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `productos`
--

LOCK TABLES `productos` WRITE;
/*!40000 ALTER TABLE `productos` DISABLE KEYS */;
INSERT INTO `productos` VALUES (1,'101190055','AB20 MEXICO PAH5',1250.00,94,'piezas',NULL,NULL,NULL,0,'prod_1.jpg',1,NULL,1,25.00,0,'General'),(2,'173931925','ANIMARETO OMNIPLUS',890.00,97,'piezas',NULL,NULL,NULL,0,'prod_2.jpg',1,NULL,1,25.00,0,'General'),(3,'10010407','AUREO S700 36G/LBX50LB TB ES',2100.00,93,'piezas',NULL,NULL,NULL,1,'prod_3.jpg',1,NULL,1,25.00,0,'General'),(4,'10023673','AUROFAC200 200GA/KGX25KG BG',1750.00,90,'piezas',NULL,NULL,NULL,1,'prod_4.jpg',1,NULL,1,25.00,0,'General'),(5,'10009982','AVATEC 15% 150G/KGX20KG TB MX',1980.00,99,'piezas',NULL,NULL,NULL,1,'prod_5.jpg',1,NULL,1,25.00,0,'General'),(6,'260221','AVIAX 5% 25 KG MEXICO',3200.00,99,'piezas',NULL,NULL,NULL,1,'prod_6.jpg',1,NULL,1,25.00,0,'General'),(7,'299088','AVIAX PLUS 25KG MEXICO',3450.00,100,'piezas',NULL,NULL,NULL,1,'prod_7.jpg',1,NULL,1,25.00,0,'General'),(8,'299001','AVICARB',560.00,80,'piezas',NULL,NULL,NULL,1,'prod_8.jpg',1,NULL,1,25.00,0,'General'),(9,'173560925','BEEF CATTLE SUPREME PREMIX',2800.00,100,'piezas',NULL,NULL,NULL,1,'prod_9.jpg',1,NULL,1,25.00,0,'General'),(10,'10009727','BMD 11% 110G/KGX25KG TB ES',1650.00,100,'piezas',NULL,NULL,NULL,1,'prod_10.jpg',1,NULL,1,25.00,0,'General'),(11,'4265009','BOVENSIN 20 25 KG BAG',2200.00,100,'piezas',NULL,NULL,NULL,1,'prod_11.jpg',1,NULL,1,25.00,0,'General'),(12,'10010469','BVTC 15% 150G/KGX25KG TB ES',1820.00,100,'piezas',NULL,NULL,NULL,1,'prod_12.jpg',1,NULL,1,25.00,0,'General'),(13,'80063400','CALCIUM IODATE 63.5% I',740.00,100,'piezas',NULL,NULL,NULL,1,'prod_13.jpg',1,NULL,1,25.00,0,'General'),(14,'173463925','CCF GAQSA PMX',1100.00,100,'piezas',NULL,NULL,NULL,1,'prod_14.jpg',1,NULL,1,25.00,0,'General'),(15,'137106055','CELLERATE CULT CLASSC PLUS MEX',4500.00,100,'piezas',NULL,NULL,NULL,1,'prod_15.jpg',1,NULL,1,25.00,0,'General'),(16,'4260603','CERDIMIX 15 25 KG BAG',1350.00,100,'piezas',NULL,NULL,NULL,1,'prod_16.jpg',1,NULL,1,25.00,0,'General'),(17,'80551433','COBALT CARB 46% 15KG',920.00,100,'piezas',NULL,NULL,NULL,1,'prod_17.jpg',1,NULL,1,25.00,0,'General'),(18,'4260608','COXISTAC 12% 25 KG BAG',2750.00,100,'piezas',NULL,NULL,NULL,1,'prod_18.jpg',1,NULL,1,25.00,0,'General'),(19,'8511037','EPHICAX 110 25KG BAG',2100.00,100,'piezas',NULL,NULL,NULL,1,'prod_19.jpg',1,NULL,1,25.00,0,'General'),(20,'4260300','ESKALIN 25 25 KG BAG',1480.00,100,'piezas',NULL,NULL,NULL,1,'prod_20.jpg',1,NULL,1,25.00,0,'General'),(21,'10014064','DECCOX 6% 60G/KGX25KG TB ES',1900.00,100,'piezas',NULL,NULL,NULL,1,'prod_21.jpg',1,NULL,1,25.00,0,'General'),(22,'4260412','STAFAC 40 25 KG BAG',1620.00,100,'piezas',NULL,NULL,NULL,1,'prod_22.jpg',1,NULL,1,25.00,0,'General'),(23,'6185000','STAFAC 500 25 KG BOX',3100.00,100,'piezas',NULL,NULL,NULL,1,'prod_23.jpg',1,NULL,1,25.00,0,'General'),(24,'8319023','PAQ-PROTEX TM 25 KG BAG',1780.00,100,'piezas',NULL,NULL,NULL,1,'prod_24.jpg',1,NULL,1,25.00,0,'General'),(25,'77167925','VISTORE CU 580 MEXICO',2350.00,100,'piezas',NULL,NULL,NULL,1,'prod_25.jpg',1,NULL,1,25.00,0,'General'),(26,'10-804547','TABIC IB VAR 5000 DS',5800.00,100,'piezas',NULL,NULL,NULL,1,'prod_26.jpg',1,NULL,1,25.00,0,'General'),(27,'10-806541','TABIC IBVAR206 5000 DS',6200.00,100,'piezas',NULL,NULL,NULL,1,'prod_27.jpg',1,NULL,1,25.00,0,'General'),(28,'1111','prueba',212.00,12,'piezas',NULL,NULL,NULL,1,'prod_28.webp',1,NULL,1,25.00,0,'General'),(29,'222','prueba2',1112.00,100,'piezas',NULL,NULL,NULL,1,'prod_29.webp',1,NULL,1,25.00,0,'General');
/*!40000 ALTER TABLE `productos` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `subcategorias_catalogo`
--

DROP TABLE IF EXISTS `subcategorias_catalogo`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `subcategorias_catalogo` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `especie_id` int(11) NOT NULL,
  `nombre` varchar(80) NOT NULL,
  `slug` varchar(80) NOT NULL,
  `descripcion` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `ux_subcat_especie_slug` (`especie_id`,`slug`),
  KEY `idx_subcat_especie` (`especie_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `subcategorias_catalogo`
--

LOCK TABLES `subcategorias_catalogo` WRITE;
/*!40000 ALTER TABLE `subcategorias_catalogo` DISABLE KEYS */;
/*!40000 ALTER TABLE `subcategorias_catalogo` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tracking`
--

DROP TABLE IF EXISTS `tracking`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tracking` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `pedido_id` int(11) NOT NULL,
  `operador_id` int(11) NOT NULL,
  `lat` double NOT NULL,
  `lng` double NOT NULL,
  `ts` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `pedido_id` (`pedido_id`),
  KEY `operador_id` (`operador_id`),
  CONSTRAINT `tracking_ibfk_1` FOREIGN KEY (`pedido_id`) REFERENCES `pedidos` (`id`),
  CONSTRAINT `tracking_ibfk_2` FOREIGN KEY (`operador_id`) REFERENCES `usuarios` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1369 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tracking`
--

LOCK TABLES `tracking` WRITE;
/*!40000 ALTER TABLE `tracking` DISABLE KEYS */;
INSERT INTO `tracking` VALUES (1,12,2,19.6828064,-98.8908519,'2026-04-09 18:28:30'),(2,12,2,19.6828064,-98.8908519,'2026-04-09 18:28:30'),(3,12,2,19.682715,-98.8908801,'2026-04-09 18:28:39'),(4,12,2,19.682715,-98.8908801,'2026-04-09 18:28:39'),(5,12,2,19.6826982,-98.8908652,'2026-04-09 18:28:47'),(6,12,2,19.6826982,-98.8908652,'2026-04-09 18:28:47'),(7,12,2,19.6827124,-98.8908577,'2026-04-09 18:28:57'),(8,12,2,19.6827124,-98.8908577,'2026-04-09 18:28:57'),(9,12,2,19.6827124,-98.8908577,'2026-04-09 18:28:57'),(10,12,2,19.6827951,-98.89084,'2026-04-09 18:29:32'),(11,12,2,19.6827951,-98.89084,'2026-04-09 18:29:32'),(12,12,2,19.6827303,-98.890861,'2026-04-09 18:29:41'),(13,12,2,19.6827303,-98.890861,'2026-04-09 18:29:41'),(14,12,2,19.6827303,-98.890861,'2026-04-09 18:29:41'),(15,12,2,19.6827303,-98.890861,'2026-04-09 18:29:49'),(16,12,2,19.6827303,-98.890861,'2026-04-09 18:29:49'),(17,12,2,19.6826867,-98.890894,'2026-04-09 18:30:19'),(18,12,2,19.6826867,-98.890894,'2026-04-09 18:30:19'),(19,11,2,19.6827568,-98.8908708,'2026-04-09 18:30:23'),(20,11,2,19.6827544,-98.8908641,'2026-04-09 18:30:32'),(21,11,2,19.6827544,-98.8908641,'2026-04-09 18:30:32'),(22,11,2,19.6827515,-98.8908749,'2026-04-09 18:30:42'),(23,11,2,19.6827515,-98.8908749,'2026-04-09 18:30:42'),(24,11,2,19.6827515,-98.8908749,'2026-04-09 18:30:42'),(25,11,2,19.6827705,-98.8908782,'2026-04-09 18:30:53'),(26,11,2,19.6827705,-98.8908782,'2026-04-09 18:30:53'),(27,11,2,19.6827705,-98.8908782,'2026-04-09 18:30:53'),(28,11,2,19.6828008,-98.8907967,'2026-04-09 18:31:01'),(29,11,2,19.682803,-98.8908382,'2026-04-10 00:35:17'),(30,11,2,19.682803,-98.8908382,'2026-04-10 00:35:17'),(31,11,2,19.6827698,-98.8908324,'2026-04-10 00:35:25'),(32,11,2,19.6827698,-98.8908324,'2026-04-10 00:35:25'),(33,9,2,19.6827673,-98.8908313,'2026-04-10 00:35:34'),(34,9,2,19.6827673,-98.8908313,'2026-04-10 00:35:34'),(35,5,2,19.6827103,-98.8908632,'2026-04-10 00:36:26'),(36,5,2,19.6827103,-98.8908632,'2026-04-10 00:36:26'),(37,5,2,19.6827057,-98.8908763,'2026-04-10 00:36:35'),(38,5,2,19.6827057,-98.8908763,'2026-04-10 00:36:35'),(39,5,2,19.6828171,-98.8908137,'2026-04-10 00:36:44'),(40,5,2,19.6828171,-98.8908137,'2026-04-10 00:36:44'),(41,5,2,19.6828163,-98.8908432,'2026-04-10 00:36:53'),(42,5,2,19.6828163,-98.8908432,'2026-04-10 00:36:53'),(43,5,2,19.6828163,-98.8908432,'2026-04-10 00:36:59'),(44,5,2,19.6828163,-98.8908432,'2026-04-10 00:36:59'),(45,5,2,19.6827726,-98.8908496,'2026-04-10 00:37:23'),(46,5,2,19.6827726,-98.8908496,'2026-04-10 00:37:24'),(47,5,2,19.682453,-98.890915,'2026-04-10 03:19:19'),(48,5,2,19.682453,-98.890915,'2026-04-10 03:19:19'),(49,5,2,19.682453,-98.890915,'2026-04-10 03:19:22'),(50,5,2,19.682453,-98.890915,'2026-04-10 03:19:26'),(51,5,2,19.682453,-98.890915,'2026-04-10 03:19:30'),(52,5,2,19.682453,-98.890915,'2026-04-10 03:19:35'),(53,5,2,19.682453,-98.890915,'2026-04-10 03:19:40'),(54,5,2,19.682453,-98.890915,'2026-04-10 03:19:43'),(55,5,2,19.682453,-98.890915,'2026-04-10 03:19:47'),(56,5,2,19.682453,-98.890915,'2026-04-10 03:19:51'),(57,5,2,19.682453,-98.890915,'2026-04-10 03:19:55'),(58,5,2,19.682453,-98.890915,'2026-04-10 03:19:59'),(59,5,2,19.682453,-98.890915,'2026-04-10 03:20:03'),(60,5,2,19.682451,-98.890953,'2026-04-10 03:20:07'),(61,5,2,19.682451,-98.890953,'2026-04-10 03:20:11'),(62,5,2,19.682451,-98.890953,'2026-04-10 03:20:15'),(63,5,2,19.682451,-98.890953,'2026-04-10 03:20:19'),(64,5,2,19.682451,-98.890953,'2026-04-10 03:20:23'),(65,5,2,19.682451,-98.890953,'2026-04-10 03:20:26'),(66,5,2,19.682451,-98.890953,'2026-04-10 03:20:30'),(67,5,2,19.682451,-98.890953,'2026-04-10 03:20:34'),(68,5,2,19.682451,-98.890953,'2026-04-10 03:20:38'),(69,5,2,19.682451,-98.890953,'2026-04-10 03:20:42'),(70,11,2,19.682451,-98.890953,'2026-04-10 03:20:45'),(71,9,2,19.682451,-98.890953,'2026-04-10 03:20:46'),(72,11,2,19.682451,-98.890953,'2026-04-10 03:20:49'),(73,5,2,19.682451,-98.890953,'2026-04-10 03:20:51'),(74,5,2,19.682451,-98.890953,'2026-04-10 03:20:55'),(75,5,2,19.682451,-98.890953,'2026-04-10 03:20:59'),(76,5,2,19.682451,-98.890953,'2026-04-10 03:21:03'),(77,5,2,19.682451,-98.890953,'2026-04-10 03:21:08'),(78,5,2,19.682451,-98.890953,'2026-04-10 03:21:11'),(79,5,2,19.682451,-98.890953,'2026-04-10 03:21:15'),(80,5,2,19.682451,-98.890953,'2026-04-10 03:21:19'),(81,5,2,19.682451,-98.890953,'2026-04-10 03:21:23'),(82,11,2,19.682451,-98.890953,'2026-04-10 03:21:27'),(83,11,2,19.682451,-98.890953,'2026-04-10 03:21:31'),(84,11,2,19.682451,-98.890953,'2026-04-10 03:21:35'),(85,11,2,19.682451,-98.890953,'2026-04-10 03:21:39'),(86,11,2,19.682451,-98.890953,'2026-04-10 03:21:43'),(87,9,2,19.682451,-98.890953,'2026-04-10 03:21:47'),(88,9,2,19.682451,-98.890953,'2026-04-10 03:21:51'),(89,9,2,19.682451,-98.890953,'2026-04-10 03:21:55'),(90,9,2,19.682451,-98.890953,'2026-04-10 03:21:59'),(91,5,2,19.682451,-98.890953,'2026-04-10 03:22:01'),(92,5,2,19.682451,-98.890953,'2026-04-10 03:22:05'),(93,5,2,19.682457,-98.89093,'2026-04-10 03:22:09'),(94,5,2,19.6828028,-98.8907988,'2026-04-10 04:08:17'),(95,5,2,19.6827894,-98.8908039,'2026-04-10 04:08:26'),(96,5,2,19.6827894,-98.8908039,'2026-04-10 04:08:26'),(97,5,2,19.6827825,-98.8908023,'2026-04-10 04:08:30'),(98,5,2,19.6827825,-98.8908023,'2026-04-10 04:08:30'),(99,5,2,19.6828046,-98.8907925,'2026-04-10 04:08:38'),(100,5,2,19.6828046,-98.8907925,'2026-04-10 04:08:38'),(101,5,2,19.6828154,-98.8908247,'2026-04-10 04:08:47'),(102,5,2,19.6828154,-98.8908247,'2026-04-10 04:08:47'),(103,5,2,19.682449,-98.890938,'2026-04-10 11:08:07'),(104,5,2,19.682449,-98.890938,'2026-04-10 11:08:11'),(105,5,2,19.682449,-98.890938,'2026-04-10 11:08:15'),(106,5,2,19.682449,-98.890938,'2026-04-10 11:08:19'),(107,5,2,19.682449,-98.890938,'2026-04-10 11:08:23'),(108,5,2,19.682449,-98.890938,'2026-04-10 11:08:27'),(109,5,2,19.682449,-98.890938,'2026-04-10 11:08:31'),(110,5,2,19.682449,-98.890938,'2026-04-10 11:08:35'),(111,5,2,19.682449,-98.890938,'2026-04-10 11:08:39'),(112,5,2,19.682450624371,-98.890939053536,'2026-04-10 11:08:43'),(113,5,2,19.682450624371,-98.890939053536,'2026-04-10 11:08:47'),(114,5,2,19.682450624371,-98.890939053536,'2026-04-10 11:08:51'),(115,5,2,19.682450624371,-98.890939053536,'2026-04-10 11:08:55'),(116,5,2,19.682450624371,-98.890939053536,'2026-04-10 11:08:59'),(117,5,2,19.682450624371,-98.890939053536,'2026-04-10 11:09:03'),(118,5,2,19.682450624371,-98.890939053536,'2026-04-10 11:09:07'),(119,5,2,19.682450624371,-98.890939053536,'2026-04-10 11:09:11'),(120,5,2,19.682450624371,-98.890939053536,'2026-04-10 11:09:15'),(121,5,2,19.682450624371,-98.890939053536,'2026-04-10 11:09:19'),(122,5,2,19.682450624371,-98.890939053536,'2026-04-10 11:09:23'),(123,5,2,19.682450624371,-98.890939053536,'2026-04-10 11:09:27'),(124,5,2,19.682450152316,-98.890939262882,'2026-04-10 11:09:31'),(125,5,2,19.682450152316,-98.890939262882,'2026-04-10 11:09:35'),(126,5,2,19.682450152316,-98.890939262882,'2026-04-10 11:09:39'),(127,5,2,19.682450291541,-98.890939201138,'2026-04-10 11:09:43'),(128,5,2,19.682450291541,-98.890939201138,'2026-04-10 11:09:47'),(129,5,2,19.682450291541,-98.890939201138,'2026-04-10 11:09:51'),(130,5,2,19.682450291541,-98.890939201138,'2026-04-10 11:09:55'),(131,5,2,19.682450291541,-98.890939201138,'2026-04-10 11:09:59'),(132,5,2,19.682450291541,-98.890939201138,'2026-04-10 11:10:03'),(133,5,2,19.682450291541,-98.890939201138,'2026-04-10 11:10:07'),(134,5,2,19.682450291541,-98.890939201138,'2026-04-10 11:10:11'),(135,5,2,19.682450291541,-98.890939201138,'2026-04-10 11:10:15'),(136,5,2,19.682450291541,-98.890939201138,'2026-04-10 11:10:19'),(137,5,2,19.682450291541,-98.890939201138,'2026-04-10 11:10:23'),(138,5,2,19.682450291541,-98.890939201138,'2026-04-10 11:10:27'),(139,5,2,19.682450333225,-98.890939711802,'2026-04-10 11:10:31'),(140,5,2,19.682450333225,-98.890939711802,'2026-04-10 11:10:35'),(141,5,2,19.682450333225,-98.890939711802,'2026-04-10 11:10:39'),(142,5,2,19.682450333225,-98.890939711802,'2026-04-10 11:10:43'),(143,5,2,19.682450333225,-98.890939711802,'2026-04-10 11:10:47'),(144,5,2,19.682450333225,-98.890939711802,'2026-04-10 11:10:51'),(145,14,2,19.682450333225,-98.890939711802,'2026-04-10 11:11:01'),(146,14,2,19.682450333225,-98.890939711802,'2026-04-10 11:11:05'),(147,14,2,19.682450333225,-98.890939711802,'2026-04-10 11:11:09'),(148,14,2,19.682450291541,-98.890939201138,'2026-04-10 11:11:13'),(149,14,2,19.682450291541,-98.890939201138,'2026-04-10 11:11:17'),(150,14,2,19.682450291541,-98.890939201138,'2026-04-10 11:11:21'),(151,14,2,19.682450267979,-98.890939211587,'2026-04-10 11:11:25'),(152,14,2,19.682450267979,-98.890939211587,'2026-04-10 11:11:29'),(153,14,2,19.682450267979,-98.890939211587,'2026-04-10 11:11:33'),(154,14,2,19.682450267979,-98.890939211587,'2026-04-10 11:11:37'),(155,14,2,19.682450267979,-98.890939211587,'2026-04-10 11:11:41'),(156,14,2,19.682450267979,-98.890939211587,'2026-04-10 11:11:45'),(157,14,2,19.682450267979,-98.890939211587,'2026-04-10 11:11:49'),(158,14,2,19.682449617372,-98.890942992347,'2026-04-10 11:12:58'),(159,14,2,19.682449617372,-98.890942992347,'2026-04-10 11:13:02'),(160,5,2,19.682449617372,-98.890942992347,'2026-04-10 11:13:09'),(161,5,2,19.682449617372,-98.890942992347,'2026-04-10 11:13:13'),(162,15,2,19.682438,-98.891014,'2026-04-16 18:38:18'),(163,15,2,19.682438,-98.891014,'2026-04-16 18:38:22'),(164,15,2,19.682438,-98.891014,'2026-04-16 18:38:26'),(165,15,2,19.682438,-98.891014,'2026-04-16 18:38:30'),(166,15,2,19.682438,-98.891014,'2026-04-16 18:38:34'),(167,15,2,19.682438,-98.891014,'2026-04-16 18:38:38'),(168,15,2,19.682739,-98.890843,'2026-04-16 18:38:42'),(169,15,2,19.682739,-98.890843,'2026-04-16 18:38:46'),(170,15,2,19.682739,-98.890843,'2026-04-16 18:38:50'),(171,15,2,19.682739,-98.890843,'2026-04-16 18:38:54'),(172,15,2,19.682739,-98.890843,'2026-04-16 18:38:58'),(173,15,2,19.682739,-98.890843,'2026-04-16 18:39:02'),(174,15,2,19.682739,-98.890843,'2026-04-16 18:39:06'),(175,15,2,19.682739,-98.890843,'2026-04-16 18:39:10'),(176,15,2,19.682739,-98.890843,'2026-04-16 18:39:14'),(177,15,2,19.682739,-98.890843,'2026-04-16 18:39:18'),(178,15,2,19.682739,-98.890843,'2026-04-16 18:39:22'),(179,15,2,19.682739,-98.890843,'2026-04-16 18:39:26'),(180,15,2,19.682739,-98.890843,'2026-04-16 18:39:30'),(181,15,2,19.682739,-98.890843,'2026-04-16 18:39:34'),(182,15,2,19.682739,-98.890843,'2026-04-16 18:39:38'),(183,15,2,19.682739,-98.890843,'2026-04-16 18:39:42'),(184,15,2,19.682739,-98.890843,'2026-04-16 18:39:46'),(185,15,2,19.682739,-98.890843,'2026-04-16 18:39:50'),(186,15,2,19.682739,-98.890843,'2026-04-16 18:39:54'),(187,15,2,19.682739,-98.890843,'2026-04-16 18:39:58'),(188,15,2,19.682739,-98.890843,'2026-04-16 18:40:02'),(189,15,2,19.682739,-98.890843,'2026-04-16 18:40:06'),(190,15,2,19.682739,-98.890843,'2026-04-16 18:40:10'),(191,15,2,19.682739,-98.890843,'2026-04-16 18:40:14'),(192,15,2,19.682739,-98.890843,'2026-04-16 18:40:18'),(193,15,2,19.682739,-98.890843,'2026-04-16 18:40:22'),(194,15,2,19.682739,-98.890843,'2026-04-16 18:40:26'),(195,15,2,19.682739,-98.890843,'2026-04-16 18:40:30'),(196,15,2,19.682739,-98.890843,'2026-04-16 18:40:34'),(197,15,2,19.682739,-98.890843,'2026-04-16 18:40:38'),(198,15,2,19.682739,-98.890843,'2026-04-16 18:40:42'),(199,15,2,19.682739,-98.890843,'2026-04-16 18:40:46'),(200,15,2,19.682739,-98.890843,'2026-04-16 18:40:50'),(201,15,2,19.682739,-98.890843,'2026-04-16 18:40:54'),(202,15,2,19.682739,-98.890843,'2026-04-16 18:40:58'),(203,15,2,19.682739,-98.890843,'2026-04-16 18:41:02'),(204,15,2,19.682739,-98.890843,'2026-04-16 18:41:06'),(205,15,2,19.682739,-98.890843,'2026-04-16 18:41:10'),(206,15,2,19.682814,-98.890808,'2026-04-17 19:15:59'),(207,15,2,19.682814,-98.890808,'2026-04-17 19:16:03'),(208,15,2,19.682814,-98.890808,'2026-04-17 19:16:07'),(209,15,2,19.682814,-98.890808,'2026-04-17 19:16:11'),(210,15,2,19.682814,-98.890808,'2026-04-17 19:16:13'),(211,15,2,19.682814,-98.890808,'2026-04-17 19:16:17'),(212,15,2,19.682814,-98.890808,'2026-04-17 19:16:21'),(213,15,2,19.682814,-98.890808,'2026-04-17 19:16:25'),(214,15,2,19.682814,-98.890808,'2026-04-17 19:16:29'),(215,15,2,19.682814,-98.890808,'2026-04-17 19:16:33'),(216,15,2,19.682814,-98.890808,'2026-04-17 19:16:37'),(217,15,2,19.682814,-98.890808,'2026-04-17 19:16:41'),(218,15,2,19.682813,-98.890807,'2026-04-17 19:16:45'),(219,15,2,19.682813,-98.890807,'2026-04-17 19:16:49'),(220,15,2,19.682813,-98.890807,'2026-04-17 19:16:53'),(221,15,2,19.682813,-98.890807,'2026-04-17 19:16:57'),(222,15,2,19.682813,-98.890807,'2026-04-17 19:17:01'),(223,15,2,19.682813,-98.890807,'2026-04-17 19:17:05'),(224,15,2,19.682813,-98.890807,'2026-04-17 19:17:09'),(225,15,2,19.682813,-98.890807,'2026-04-17 19:17:13'),(226,15,2,19.682813,-98.890807,'2026-04-17 19:17:17'),(227,15,2,19.682824,-98.890817,'2026-04-17 19:17:21'),(228,15,2,19.682824,-98.890817,'2026-04-17 19:17:25'),(229,15,2,19.682824,-98.890817,'2026-04-17 19:17:29'),(230,15,2,19.682824,-98.890817,'2026-04-17 19:17:33'),(231,15,2,19.682824,-98.890817,'2026-04-17 19:17:37'),(232,15,2,19.682824,-98.890817,'2026-04-17 19:17:41'),(233,15,2,19.682824,-98.890817,'2026-04-17 19:17:45'),(234,15,2,19.682824,-98.890817,'2026-04-17 19:17:49'),(235,15,2,19.682824,-98.890817,'2026-04-17 19:17:53'),(236,15,2,19.682459,-98.89093,'2026-04-17 19:17:57'),(237,15,2,19.682459,-98.89093,'2026-04-17 19:18:01'),(238,15,2,19.682459,-98.89093,'2026-04-17 19:18:05'),(239,15,2,19.682459,-98.89093,'2026-04-17 19:18:09'),(240,15,2,19.682459,-98.89093,'2026-04-17 19:18:13'),(241,15,2,19.682459,-98.89093,'2026-04-17 19:18:17'),(242,15,2,19.682459,-98.89093,'2026-04-17 19:18:21'),(243,15,2,19.682459,-98.89093,'2026-04-17 19:18:25'),(244,15,2,19.682459,-98.89093,'2026-04-17 19:18:29'),(245,15,2,19.682459,-98.89093,'2026-04-17 19:18:33'),(246,15,2,19.682459,-98.89093,'2026-04-17 19:18:37'),(247,15,2,19.682459,-98.89093,'2026-04-17 19:18:41'),(248,15,2,19.682459,-98.89093,'2026-04-17 19:18:45'),(249,15,2,19.682459,-98.89093,'2026-04-17 19:18:49'),(250,15,2,19.682459,-98.89093,'2026-04-17 19:18:53'),(251,15,2,19.682461,-98.890923,'2026-04-17 19:18:57'),(252,15,2,19.682461,-98.890923,'2026-04-17 19:19:01'),(253,15,2,19.682461,-98.890923,'2026-04-17 19:19:05'),(254,15,2,19.682459,-98.89093,'2026-04-17 19:19:09'),(255,15,2,19.682459,-98.89093,'2026-04-17 19:19:13'),(256,15,2,19.682459,-98.89093,'2026-04-17 19:19:17'),(257,15,2,19.682459,-98.89093,'2026-04-17 19:19:21'),(258,15,2,19.682459,-98.89093,'2026-04-17 19:19:25'),(259,15,2,19.682459,-98.89093,'2026-04-17 19:19:29'),(260,15,2,19.682459,-98.89093,'2026-04-17 19:19:33'),(261,15,2,19.682459,-98.89093,'2026-04-17 19:19:36'),(262,15,2,19.682459,-98.89093,'2026-04-17 19:19:38'),(263,15,2,19.682459,-98.89093,'2026-04-17 19:19:39'),(264,15,2,19.682459,-98.89093,'2026-04-17 19:19:40'),(265,15,2,19.682459,-98.89093,'2026-04-17 19:19:41'),(266,15,2,19.682459,-98.89093,'2026-04-17 19:19:41'),(267,15,2,19.682459,-98.89093,'2026-04-17 19:19:43'),(268,15,2,19.682459,-98.89093,'2026-04-17 19:19:44'),(269,15,2,19.682459,-98.89093,'2026-04-17 19:19:45'),(270,15,2,19.682459,-98.89093,'2026-04-17 19:19:46'),(271,15,2,19.682459,-98.89093,'2026-04-17 19:19:46'),(272,15,2,19.682459,-98.89093,'2026-04-17 19:19:47'),(273,15,2,19.682459,-98.89093,'2026-04-17 19:19:48'),(274,15,2,19.682459,-98.89093,'2026-04-17 19:19:48'),(275,15,2,19.682459,-98.89093,'2026-04-17 19:19:49'),(276,15,2,19.682459,-98.89093,'2026-04-17 19:19:49'),(277,15,2,19.682459,-98.89093,'2026-04-17 19:19:50'),(278,15,2,19.682459,-98.89093,'2026-04-17 19:19:50'),(279,15,2,19.682459,-98.89093,'2026-04-17 19:19:51'),(280,15,2,19.682459,-98.89093,'2026-04-17 19:19:55'),(281,15,2,19.682459,-98.89093,'2026-04-17 19:19:59'),(282,15,2,19.682459,-98.89093,'2026-04-17 19:20:03'),(283,15,2,19.682461,-98.890915,'2026-04-17 19:20:07'),(284,15,2,19.682461,-98.890915,'2026-04-17 19:20:11'),(285,15,2,19.682461,-98.890915,'2026-04-17 19:20:15'),(286,15,2,19.682461,-98.890915,'2026-04-17 19:20:19'),(287,15,2,19.682461,-98.890915,'2026-04-17 19:20:23'),(288,15,2,19.682461,-98.890915,'2026-04-17 19:20:27'),(289,15,2,19.682461,-98.890915,'2026-04-17 19:20:28'),(290,15,2,19.682815,-98.890809,'2026-04-17 19:20:29'),(291,15,2,19.682815,-98.890809,'2026-04-17 19:20:30'),(292,15,2,19.682815,-98.890809,'2026-04-17 19:20:31'),(293,15,2,19.682815,-98.890809,'2026-04-17 19:20:35'),(294,15,2,19.682815,-98.890809,'2026-04-17 19:20:38'),(295,16,2,19.3621,-99.2405,'2026-04-20 14:55:00'),(296,16,2,19.3621,-99.2405,'2026-04-20 14:55:24'),(297,16,2,19.3621,-99.2405,'2026-04-20 14:55:33'),(298,16,2,19.3621,-99.2405,'2026-04-20 14:55:36'),(299,16,2,19.3621,-99.2405,'2026-04-20 14:55:40'),(300,16,2,19.3621,-99.2405,'2026-04-20 14:56:15'),(301,16,2,19.3621,-99.2405,'2026-04-20 14:56:19'),(302,16,2,19.3621,-99.2405,'2026-04-20 14:56:23'),(303,16,2,19.3621,-99.2405,'2026-04-20 14:56:27'),(304,16,2,19.3621,-99.2405,'2026-04-20 14:56:31'),(305,16,2,19.3621,-99.2405,'2026-04-20 14:56:35'),(306,16,2,19.3621,-99.2405,'2026-04-20 14:56:39'),(307,16,2,19.3621,-99.2405,'2026-04-20 14:56:43'),(308,16,2,19.3621,-99.2405,'2026-04-20 14:56:47'),(309,16,2,19.3621,-99.2405,'2026-04-20 14:56:51'),(310,16,2,19.3621,-99.2405,'2026-04-20 14:56:55'),(311,16,2,19.3621,-99.2405,'2026-04-20 14:56:59'),(312,16,2,19.3621,-99.2405,'2026-04-20 14:57:03'),(313,16,2,19.3621,-99.2405,'2026-04-20 14:57:07'),(314,16,2,19.3621,-99.2405,'2026-04-20 14:57:11'),(315,16,2,19.3621,-99.2405,'2026-04-20 14:57:15'),(316,16,2,19.3621,-99.2405,'2026-04-20 14:57:19'),(317,16,2,19.3621,-99.2405,'2026-04-20 14:57:23'),(318,16,2,19.3621,-99.2405,'2026-04-20 14:57:27'),(319,16,2,19.3621,-99.2405,'2026-04-20 14:57:31'),(320,16,2,19.3621,-99.2405,'2026-04-20 14:57:35'),(321,16,2,19.3621,-99.2405,'2026-04-20 14:57:39'),(322,16,2,19.3621,-99.2405,'2026-04-20 14:57:43'),(323,16,2,19.3621,-99.2405,'2026-04-20 14:57:47'),(324,16,2,19.3621,-99.2405,'2026-04-20 14:57:51'),(325,16,2,19.3621,-99.2405,'2026-04-20 14:57:55'),(326,16,2,19.3621,-99.2405,'2026-04-20 14:57:59'),(327,16,2,19.3621,-99.2405,'2026-04-20 14:58:03'),(328,16,2,19.3621,-99.2405,'2026-04-20 14:58:07'),(329,16,2,19.3621,-99.2405,'2026-04-20 14:58:11'),(330,16,2,19.3621,-99.2405,'2026-04-20 14:58:15'),(331,16,2,19.3621,-99.2405,'2026-04-20 14:58:19'),(332,16,2,19.3621,-99.2405,'2026-04-20 14:58:23'),(333,16,2,19.3621,-99.2405,'2026-04-20 14:58:27'),(334,16,2,19.3621,-99.2405,'2026-04-20 14:58:31'),(335,16,2,19.3621,-99.2405,'2026-04-20 14:58:35'),(336,16,2,19.3621,-99.2405,'2026-04-20 14:58:39'),(337,16,2,19.3621,-99.2405,'2026-04-20 14:58:43'),(338,16,2,19.3621,-99.2405,'2026-04-20 14:58:47'),(339,16,2,19.3621,-99.2405,'2026-04-20 14:58:51'),(340,16,2,19.3621,-99.2405,'2026-04-20 14:58:55'),(341,16,2,19.3621,-99.2405,'2026-04-20 14:58:59'),(342,16,2,19.3621,-99.2405,'2026-04-20 14:59:03'),(343,16,2,19.3621,-99.2405,'2026-04-20 14:59:07'),(344,16,2,19.3621,-99.2405,'2026-04-20 14:59:11'),(345,16,2,19.3621,-99.2405,'2026-04-20 14:59:15'),(346,16,2,19.3621,-99.2405,'2026-04-20 14:59:19'),(347,16,2,19.3621,-99.2405,'2026-04-20 14:59:23'),(348,16,2,19.3621,-99.2405,'2026-04-20 14:59:27'),(349,16,2,19.3621,-99.2405,'2026-04-20 14:59:31'),(350,16,2,19.3621,-99.2405,'2026-04-20 14:59:35'),(351,16,2,19.3621,-99.2405,'2026-04-20 14:59:39'),(352,16,2,19.3621,-99.2405,'2026-04-20 14:59:43'),(353,16,2,19.3621,-99.2405,'2026-04-20 14:59:47'),(354,16,2,19.3621,-99.2405,'2026-04-20 14:59:51'),(355,16,2,19.3621,-99.2405,'2026-04-20 14:59:55'),(356,16,2,19.3621,-99.2405,'2026-04-20 14:59:59'),(357,16,2,19.3621,-99.2405,'2026-04-20 15:00:04'),(358,16,2,19.3621,-99.2405,'2026-04-20 15:00:07'),(359,16,2,19.3621,-99.2405,'2026-04-20 15:00:11'),(360,16,2,19.3621,-99.2405,'2026-04-20 15:00:15'),(361,16,2,19.3621,-99.2405,'2026-04-20 15:00:19'),(362,16,2,19.3621,-99.2405,'2026-04-20 15:00:23'),(363,16,2,19.3621,-99.2405,'2026-04-20 15:00:27'),(364,16,2,19.3621,-99.2405,'2026-04-20 15:00:31'),(365,16,2,19.3621,-99.2405,'2026-04-20 15:00:35'),(366,16,2,19.3621,-99.2405,'2026-04-20 15:00:39'),(367,16,2,19.3621,-99.2405,'2026-04-20 15:00:43'),(368,16,2,19.3621,-99.2405,'2026-04-20 15:00:47'),(369,16,2,19.3621,-99.2405,'2026-04-20 15:00:51'),(370,16,2,19.3621,-99.2405,'2026-04-20 15:00:55'),(371,16,2,19.3621,-99.2405,'2026-04-20 15:00:59'),(372,16,2,19.3621,-99.2405,'2026-04-20 15:01:03'),(373,16,2,19.3621,-99.2405,'2026-04-20 15:01:07'),(374,16,2,19.3621,-99.2405,'2026-04-20 15:01:11'),(375,16,2,19.3621,-99.2405,'2026-04-20 15:01:15'),(376,16,2,19.3621,-99.2405,'2026-04-20 15:01:19'),(377,16,2,19.3621,-99.2405,'2026-04-20 15:01:23'),(378,16,2,19.3621,-99.2405,'2026-04-20 15:01:27'),(379,16,2,19.3621,-99.2405,'2026-04-20 15:01:31'),(380,16,2,19.3621,-99.2405,'2026-04-20 15:01:35'),(381,16,2,19.3621,-99.2405,'2026-04-20 15:01:39'),(382,16,2,19.3621,-99.2405,'2026-04-20 15:01:43'),(383,16,2,19.3621,-99.2405,'2026-04-20 15:01:47'),(384,16,2,19.3621,-99.2405,'2026-04-20 15:01:51'),(385,16,2,19.3621,-99.2405,'2026-04-20 15:01:55'),(386,16,2,19.3621,-99.2405,'2026-04-20 15:01:59'),(387,16,2,19.3621,-99.2405,'2026-04-20 15:02:03'),(388,16,2,19.3621,-99.2405,'2026-04-20 15:02:07'),(389,16,2,19.3621,-99.2405,'2026-04-20 15:02:11'),(390,16,2,19.3621,-99.2405,'2026-04-20 15:02:15'),(391,16,2,19.3621,-99.2405,'2026-04-20 15:02:28'),(392,16,2,19.3621,-99.2405,'2026-04-20 15:02:32'),(393,16,2,19.3621,-99.2405,'2026-04-20 15:02:36'),(394,16,2,19.3621,-99.2405,'2026-04-20 15:02:40'),(395,16,2,19.3621,-99.2405,'2026-04-20 15:03:47'),(396,16,2,19.3621,-99.2405,'2026-04-20 15:03:51'),(397,16,2,19.3621,-99.2405,'2026-04-20 15:03:55'),(398,17,2,19.682449,-98.89093,'2026-04-21 23:30:43'),(399,17,2,19.682446,-98.890976,'2026-04-21 23:30:47'),(400,17,2,19.682446,-98.890976,'2026-04-21 23:30:51'),(401,17,2,19.682446,-98.890976,'2026-04-21 23:30:55'),(402,17,2,19.682438,-98.891014,'2026-04-21 23:31:00'),(403,17,2,19.682438,-98.891014,'2026-04-21 23:31:03'),(404,17,2,19.682438,-98.891014,'2026-04-21 23:31:07'),(405,17,2,19.682438,-98.891014,'2026-04-21 23:31:11'),(406,17,2,19.682438,-98.891014,'2026-04-21 23:31:15'),(407,17,2,19.682438,-98.891014,'2026-04-21 23:31:19'),(408,17,2,19.682438,-98.891014,'2026-04-21 23:31:23'),(409,17,2,19.682438,-98.891014,'2026-04-21 23:31:27'),(410,17,2,19.682438,-98.891014,'2026-04-21 23:31:31'),(411,17,2,19.682447,-98.890938,'2026-04-21 23:31:36'),(412,17,2,19.682447,-98.890938,'2026-04-21 23:31:39'),(413,17,2,19.682447,-98.890938,'2026-04-21 23:31:43'),(414,17,2,19.682451,-98.89093,'2026-04-21 23:31:47'),(415,17,2,19.682451,-98.89093,'2026-04-21 23:31:51'),(416,17,2,19.682451,-98.89093,'2026-04-21 23:31:55'),(417,17,2,19.682451,-98.89093,'2026-04-21 23:32:00'),(418,17,2,19.682451,-98.89093,'2026-04-21 23:32:03'),(419,17,2,19.682451,-98.89093,'2026-04-21 23:32:07'),(420,17,2,19.682451,-98.89093,'2026-04-21 23:32:11'),(421,17,2,19.682451,-98.89093,'2026-04-21 23:32:15'),(422,17,2,19.682451,-98.89093,'2026-04-21 23:32:19'),(423,17,2,19.682451,-98.89093,'2026-04-21 23:32:24'),(424,17,2,19.682451,-98.89093,'2026-04-21 23:32:27'),(425,17,2,19.682451,-98.89093,'2026-04-21 23:32:31'),(426,17,2,19.682451,-98.89093,'2026-04-21 23:32:36'),(427,17,2,19.682446,-98.890976,'2026-04-21 23:32:50'),(428,17,2,19.682451,-98.89093,'2026-04-21 23:33:50'),(429,17,2,19.682451,-98.89093,'2026-04-21 23:34:25'),(430,17,2,19.682451,-98.89093,'2026-04-21 23:34:27'),(431,14,2,19.682446,-98.890976,'2026-04-21 23:47:08'),(432,14,2,19.682446,-98.890976,'2026-04-21 23:47:12'),(433,14,2,19.682446,-98.890976,'2026-04-21 23:47:17'),(434,14,2,19.682446,-98.890976,'2026-04-21 23:47:21'),(435,14,2,19.682446,-98.890976,'2026-04-21 23:47:25'),(436,14,2,19.682446,-98.890976,'2026-04-21 23:47:29'),(437,14,2,19.682455,-98.89093,'2026-04-21 23:47:33'),(438,14,2,19.682455,-98.89093,'2026-04-21 23:47:36'),(439,14,2,19.682455,-98.89093,'2026-04-21 23:47:40'),(440,14,2,19.682451,-98.890938,'2026-04-21 23:47:45'),(441,14,2,19.682451,-98.890938,'2026-04-21 23:47:49'),(442,14,2,19.682451,-98.890938,'2026-04-21 23:47:53'),(443,14,2,19.682451,-98.890938,'2026-04-21 23:47:57'),(444,14,2,19.682451,-98.890938,'2026-04-21 23:48:01'),(445,14,2,19.682451,-98.890938,'2026-04-21 23:48:04'),(446,14,2,19.682451,-98.890938,'2026-04-21 23:48:09'),(447,14,2,19.682451,-98.890938,'2026-04-21 23:48:13'),(448,14,2,19.682451,-98.890938,'2026-04-21 23:48:17'),(449,14,2,19.682451,-98.890938,'2026-04-21 23:48:20'),(450,14,2,19.682451,-98.890938,'2026-04-21 23:48:25'),(451,14,2,19.682451,-98.890938,'2026-04-21 23:48:29'),(452,14,2,19.682451,-98.890938,'2026-04-21 23:48:32'),(453,14,2,19.682451,-98.890938,'2026-04-21 23:48:36'),(454,14,2,19.682451,-98.890938,'2026-04-21 23:48:41'),(455,14,2,19.682438,-98.891014,'2026-04-21 23:48:45'),(456,14,2,19.682438,-98.891014,'2026-04-21 23:48:49'),(457,14,2,19.682438,-98.891014,'2026-04-21 23:48:53'),(458,14,2,19.682438,-98.891014,'2026-04-21 23:48:56'),(459,14,2,19.682438,-98.891014,'2026-04-21 23:49:01'),(460,14,2,19.682438,-98.891014,'2026-04-21 23:49:05'),(461,14,2,19.682438,-98.891014,'2026-04-21 23:49:08'),(462,14,2,19.682438,-98.891014,'2026-04-21 23:49:12'),(463,14,2,19.682438,-98.891014,'2026-04-21 23:49:17'),(464,14,2,19.682438,-98.891014,'2026-04-21 23:49:21'),(465,14,2,19.682438,-98.891014,'2026-04-21 23:49:25'),(466,14,2,19.682438,-98.891014,'2026-04-21 23:49:29'),(467,14,2,19.682449,-98.890938,'2026-04-21 23:49:33'),(468,14,2,19.682449,-98.890938,'2026-04-21 23:49:36'),(469,14,2,19.682449,-98.890938,'2026-04-21 23:49:41'),(470,14,2,19.682449,-98.890938,'2026-04-21 23:49:44'),(471,14,2,19.682449,-98.890938,'2026-04-21 23:49:48'),(472,14,2,19.682449,-98.890938,'2026-04-21 23:49:53'),(473,14,2,19.682449,-98.890938,'2026-04-21 23:49:57'),(474,14,2,19.682449,-98.890938,'2026-04-21 23:50:00'),(475,14,2,19.682449,-98.890938,'2026-04-21 23:50:05'),(476,14,2,19.682449,-98.890938,'2026-04-21 23:50:09'),(477,14,2,19.682449,-98.890938,'2026-04-21 23:50:12'),(478,14,2,19.682449,-98.890938,'2026-04-21 23:50:17'),(479,14,2,19.682449,-98.890938,'2026-04-21 23:50:21'),(480,14,2,19.682449,-98.890938,'2026-04-21 23:50:24'),(481,14,2,19.682449,-98.890938,'2026-04-21 23:50:29'),(482,14,2,19.682449,-98.890938,'2026-04-21 23:50:32'),(483,14,2,19.682449,-98.890938,'2026-04-21 23:50:37'),(484,14,2,19.682449,-98.890938,'2026-04-21 23:50:41'),(485,14,2,19.682438,-98.891014,'2026-04-21 23:50:44'),(486,14,2,19.682438,-98.891014,'2026-04-21 23:50:48'),(487,14,2,19.682438,-98.891014,'2026-04-21 23:50:53'),(488,14,2,19.682438,-98.891014,'2026-04-21 23:50:57'),(489,14,2,19.682438,-98.891014,'2026-04-21 23:51:00'),(490,14,2,19.682438,-98.891014,'2026-04-21 23:51:05'),(491,14,2,19.682438,-98.891014,'2026-04-21 23:51:08'),(492,14,2,19.682438,-98.891014,'2026-04-21 23:51:13'),(493,14,2,19.682438,-98.891014,'2026-04-21 23:51:17'),(494,14,2,19.682438,-98.891014,'2026-04-21 23:51:20'),(495,14,2,19.682438,-98.891014,'2026-04-21 23:51:24'),(496,14,2,19.682438,-98.891014,'2026-04-21 23:51:29'),(497,14,2,19.682451,-98.890923,'2026-04-21 23:51:33'),(498,14,2,19.682451,-98.890923,'2026-04-21 23:51:36'),(499,14,2,19.682451,-98.890923,'2026-04-21 23:51:41'),(500,14,2,19.682451,-98.890923,'2026-04-21 23:51:45'),(501,14,2,19.682451,-98.890923,'2026-04-21 23:51:48'),(502,14,2,19.682451,-98.890923,'2026-04-21 23:51:53'),(503,14,2,19.682451,-98.890923,'2026-04-21 23:51:56'),(504,17,2,19.682446,-98.890976,'2026-04-21 23:53:13'),(505,17,2,19.682446,-98.890976,'2026-04-21 23:53:17'),(506,17,2,19.682446,-98.890976,'2026-04-21 23:53:21'),(507,17,2,19.682446,-98.890976,'2026-04-21 23:53:25'),(508,17,2,19.682446,-98.890976,'2026-04-21 23:53:29'),(509,17,2,19.682446,-98.890976,'2026-04-21 23:53:33'),(510,16,2,19.682438,-98.891014,'2026-04-21 23:53:40'),(511,16,2,19.682438,-98.891014,'2026-04-21 23:53:43'),(512,16,2,19.682438,-98.891014,'2026-04-21 23:53:47'),(513,16,2,19.682438,-98.891014,'2026-04-21 23:53:51'),(514,16,2,19.682438,-98.891014,'2026-04-21 23:53:55'),(515,16,2,19.682438,-98.891014,'2026-04-21 23:53:59'),(516,16,2,19.682438,-98.891014,'2026-04-21 23:54:03'),(517,16,2,19.682438,-98.891014,'2026-04-21 23:54:07'),(518,16,2,19.682438,-98.891014,'2026-04-21 23:54:11'),(519,16,2,19.682438,-98.891014,'2026-04-21 23:54:16'),(520,16,2,19.682438,-98.891014,'2026-04-21 23:54:19'),(521,16,2,19.682438,-98.891014,'2026-04-21 23:54:23'),(522,16,2,19.682438,-98.891014,'2026-04-21 23:54:27'),(523,16,2,19.682438,-98.891014,'2026-04-21 23:54:31'),(524,16,2,19.682438,-98.891014,'2026-04-21 23:54:35'),(525,16,2,19.682438,-98.891014,'2026-04-21 23:54:39'),(526,16,2,19.682438,-98.891014,'2026-04-21 23:54:44'),(527,16,2,19.682438,-98.891014,'2026-04-21 23:54:48'),(528,16,2,19.682438,-98.891014,'2026-04-21 23:54:53'),(529,16,2,19.682438,-98.891014,'2026-04-21 23:54:56'),(530,16,2,19.682438,-98.891014,'2026-04-21 23:55:00'),(531,16,2,19.682438,-98.891014,'2026-04-21 23:55:05'),(532,16,2,19.682438,-98.891014,'2026-04-21 23:55:08'),(533,16,2,19.682438,-98.891014,'2026-04-21 23:55:12'),(534,16,2,19.682438,-98.891014,'2026-04-21 23:55:16'),(535,16,2,19.682438,-98.891014,'2026-04-21 23:55:20'),(536,16,2,19.682438,-98.891014,'2026-04-21 23:55:24'),(537,16,2,19.682438,-98.891014,'2026-04-21 23:55:29'),(538,16,2,19.682438,-98.891014,'2026-04-21 23:55:32'),(539,16,2,19.682438,-98.891014,'2026-04-21 23:55:36'),(540,16,2,19.682438,-98.891014,'2026-04-21 23:55:40'),(541,16,2,19.682438,-98.891014,'2026-04-21 23:55:44'),(542,16,2,19.682438,-98.891014,'2026-04-21 23:55:48'),(543,16,2,19.682438,-98.891014,'2026-04-21 23:55:52'),(544,16,2,19.682438,-98.891014,'2026-04-21 23:55:56'),(545,16,2,19.682438,-98.891014,'2026-04-21 23:56:00'),(546,16,2,19.682438,-98.891014,'2026-04-21 23:56:05'),(547,16,2,19.682438,-98.891014,'2026-04-21 23:56:08'),(548,16,2,19.682438,-98.891014,'2026-04-21 23:56:12'),(549,16,2,19.682438,-98.891014,'2026-04-21 23:56:16'),(550,16,2,19.682438,-98.891014,'2026-04-21 23:56:20'),(551,16,2,19.682438,-98.891014,'2026-04-21 23:56:24'),(552,16,2,19.682438,-98.891014,'2026-04-21 23:56:29'),(553,16,2,19.682438,-98.891014,'2026-04-21 23:56:32'),(554,16,2,19.682438,-98.891014,'2026-04-21 23:56:36'),(555,16,2,19.682438,-98.891014,'2026-04-21 23:56:41'),(556,16,2,19.682438,-98.891014,'2026-04-21 23:56:44'),(557,16,2,19.682438,-98.891014,'2026-04-21 23:56:48'),(558,16,2,19.682438,-98.891014,'2026-04-21 23:56:53'),(559,16,2,19.682438,-98.891014,'2026-04-21 23:56:56'),(560,16,2,19.682438,-98.891014,'2026-04-21 23:57:00'),(561,16,2,19.682438,-98.891014,'2026-04-21 23:57:04'),(562,16,2,19.682438,-98.891014,'2026-04-21 23:57:08'),(563,16,2,19.682438,-98.891014,'2026-04-21 23:57:12'),(564,16,2,19.682446,-98.890976,'2026-04-21 23:57:16'),(565,16,2,19.682446,-98.890976,'2026-04-21 23:57:20'),(566,16,2,19.682446,-98.890976,'2026-04-21 23:57:24'),(567,16,2,19.682446,-98.890976,'2026-04-21 23:57:28'),(568,16,2,19.682446,-98.890976,'2026-04-21 23:57:31'),(569,16,2,19.682446,-98.890976,'2026-04-21 23:57:35'),(570,16,2,19.682446,-98.890976,'2026-04-21 23:57:40'),(571,16,2,19.682446,-98.890976,'2026-04-21 23:57:43'),(572,16,2,19.682446,-98.890976,'2026-04-21 23:57:47'),(573,16,2,19.682446,-98.890976,'2026-04-21 23:57:53'),(574,16,2,19.682446,-98.890976,'2026-04-21 23:57:56'),(575,16,2,19.682446,-98.890976,'2026-04-21 23:58:00'),(576,16,2,19.682446,-98.890976,'2026-04-21 23:58:04'),(577,16,2,19.682446,-98.890976,'2026-04-21 23:58:08'),(578,16,2,19.682446,-98.890976,'2026-04-21 23:58:12'),(579,16,2,19.682446,-98.890976,'2026-04-21 23:58:17'),(580,16,2,19.682446,-98.890976,'2026-04-21 23:58:21'),(581,16,2,19.682446,-98.890976,'2026-04-21 23:58:24'),(582,16,2,19.682438,-98.891014,'2026-04-21 23:58:29'),(583,16,2,19.682438,-98.891014,'2026-04-21 23:58:32'),(584,16,2,19.682438,-98.891014,'2026-04-21 23:58:36'),(585,16,2,19.682438,-98.891014,'2026-04-21 23:58:40'),(586,16,2,19.682438,-98.891014,'2026-04-21 23:58:44'),(587,16,2,19.682438,-98.891014,'2026-04-21 23:58:48'),(588,16,2,19.682438,-98.891014,'2026-04-21 23:58:52'),(589,16,2,19.682438,-98.891014,'2026-04-21 23:58:56'),(590,16,2,19.682438,-98.891014,'2026-04-21 23:59:00'),(591,16,2,19.682438,-98.891014,'2026-04-21 23:59:05'),(592,16,2,19.682438,-98.891014,'2026-04-21 23:59:08'),(593,16,2,19.682438,-98.891014,'2026-04-21 23:59:12'),(594,16,2,19.682446,-98.890976,'2026-04-21 23:59:17'),(595,16,2,19.682446,-98.890976,'2026-04-21 23:59:20'),(596,16,2,19.682446,-98.890976,'2026-04-21 23:59:24'),(597,16,2,19.682446,-98.890976,'2026-04-21 23:59:28'),(598,16,2,19.682446,-98.890976,'2026-04-21 23:59:32'),(599,16,2,19.682446,-98.890976,'2026-04-21 23:59:36'),(600,16,2,19.682446,-98.890976,'2026-04-21 23:59:41'),(601,16,2,19.682446,-98.890976,'2026-04-21 23:59:44'),(602,16,2,19.682446,-98.890976,'2026-04-21 23:59:48'),(603,16,2,19.682446,-98.890976,'2026-04-21 23:59:52'),(604,16,2,19.682446,-98.890976,'2026-04-21 23:59:56'),(605,16,2,19.682446,-98.890976,'2026-04-22 00:00:00'),(606,16,2,19.682446,-98.890976,'2026-04-22 00:00:04'),(607,16,2,19.682446,-98.890976,'2026-04-22 00:00:08'),(608,16,2,19.682446,-98.890976,'2026-04-22 00:00:12'),(609,16,2,19.682446,-98.890976,'2026-04-22 00:00:17'),(610,16,2,19.682446,-98.890976,'2026-04-22 00:00:19'),(611,16,2,19.682446,-98.890976,'2026-04-22 00:00:24'),(612,16,2,19.682438,-98.891014,'2026-04-22 00:00:28'),(613,16,2,19.682438,-98.891014,'2026-04-22 00:00:32'),(614,16,2,19.682438,-98.891014,'2026-04-22 00:00:36'),(615,16,2,19.682438,-98.891014,'2026-04-22 00:00:41'),(616,16,2,19.682438,-98.891014,'2026-04-22 00:00:44'),(617,16,2,19.682438,-98.891014,'2026-04-22 00:00:48'),(618,16,2,19.682438,-98.891014,'2026-04-22 00:00:53'),(619,16,2,19.682438,-98.891014,'2026-04-22 00:00:56'),(620,16,2,19.682438,-98.891014,'2026-04-22 00:01:00'),(621,16,2,19.682438,-98.891014,'2026-04-22 00:01:04'),(622,16,2,19.682438,-98.891014,'2026-04-22 00:01:08'),(623,16,2,19.682438,-98.891014,'2026-04-22 00:01:12'),(624,16,2,19.682438,-98.891014,'2026-04-22 00:01:16'),(625,16,2,19.682438,-98.891014,'2026-04-22 00:01:20'),(626,16,2,19.682438,-98.891014,'2026-04-22 00:01:24'),(627,16,2,19.682438,-98.891014,'2026-04-22 00:01:29'),(628,16,2,19.682438,-98.891014,'2026-04-22 00:01:32'),(629,16,2,19.682438,-98.891014,'2026-04-22 00:01:35'),(630,16,2,19.682449,-98.890938,'2026-04-22 00:01:41'),(631,16,2,19.682449,-98.890938,'2026-04-22 00:01:44'),(632,16,2,19.682449,-98.890938,'2026-04-22 00:01:48'),(633,16,2,19.682449,-98.890938,'2026-04-22 00:01:52'),(634,16,2,19.682449,-98.890938,'2026-04-22 00:01:56'),(635,16,2,19.682449,-98.890938,'2026-04-22 00:02:00'),(636,16,2,19.682449,-98.890938,'2026-04-22 00:02:05'),(637,16,2,19.682449,-98.890938,'2026-04-22 00:02:08'),(638,16,2,19.682449,-98.890938,'2026-04-22 00:02:12'),(639,16,2,19.682449,-98.890938,'2026-04-22 00:02:16'),(640,16,2,19.682449,-98.890938,'2026-04-22 00:02:20'),(641,16,2,19.682449,-98.890938,'2026-04-22 00:02:24'),(642,16,2,19.682449,-98.890938,'2026-04-22 00:02:28'),(643,16,2,19.682449,-98.890938,'2026-04-22 00:02:32'),(644,16,2,19.682449,-98.890938,'2026-04-22 00:02:36'),(645,16,2,19.682449,-98.890938,'2026-04-22 00:02:41'),(646,16,2,19.682449,-98.890938,'2026-04-22 00:02:44'),(647,16,2,19.682449,-98.890938,'2026-04-22 00:02:48'),(648,16,2,19.682438,-98.891014,'2026-04-22 00:02:52'),(649,16,2,19.682438,-98.891014,'2026-04-22 00:02:56'),(650,16,2,19.682438,-98.891014,'2026-04-22 00:03:00'),(651,16,2,19.682438,-98.891014,'2026-04-22 00:03:04'),(652,16,2,19.682438,-98.891014,'2026-04-22 00:03:08'),(653,16,2,19.682438,-98.891014,'2026-04-22 00:03:12'),(654,16,2,19.682438,-98.891014,'2026-04-22 00:03:16'),(655,16,2,19.682438,-98.891014,'2026-04-22 00:03:20'),(656,16,2,19.682438,-98.891014,'2026-04-22 00:03:24'),(657,16,2,19.682438,-98.891014,'2026-04-22 00:03:29'),(658,16,2,19.682438,-98.891014,'2026-04-22 00:03:32'),(659,16,2,19.682438,-98.891014,'2026-04-22 00:03:36'),(660,16,2,19.682451,-98.890938,'2026-04-22 00:03:40'),(661,16,2,19.682451,-98.890938,'2026-04-22 00:03:44'),(662,16,2,19.682451,-98.890938,'2026-04-22 00:03:48'),(663,16,2,19.682451,-98.890938,'2026-04-22 00:03:53'),(664,16,2,19.682451,-98.890938,'2026-04-22 00:03:56'),(665,16,2,19.682451,-98.890938,'2026-04-22 00:04:00'),(666,16,2,19.682451,-98.890938,'2026-04-22 00:04:04'),(667,16,2,19.682451,-98.890938,'2026-04-22 00:04:08'),(668,16,2,19.682451,-98.890938,'2026-04-22 00:04:12'),(669,16,2,19.682451,-98.890938,'2026-04-22 00:04:16'),(670,16,2,19.682451,-98.890938,'2026-04-22 00:04:20'),(671,16,2,19.682451,-98.890938,'2026-04-22 00:04:24'),(672,16,2,19.682451,-98.890938,'2026-04-22 00:04:29'),(673,16,2,19.682451,-98.890938,'2026-04-22 00:04:32'),(674,16,2,19.682451,-98.890938,'2026-04-22 00:04:36'),(675,16,2,19.682451,-98.890938,'2026-04-22 00:04:40'),(676,16,2,19.682451,-98.890938,'2026-04-22 00:04:44'),(677,16,2,19.682451,-98.890938,'2026-04-22 00:04:48'),(678,16,2,19.682438,-98.891014,'2026-04-22 00:04:52'),(679,16,2,19.682438,-98.891014,'2026-04-22 00:04:56'),(680,16,2,19.682438,-98.891014,'2026-04-22 00:05:00'),(681,16,2,19.682438,-98.891014,'2026-04-22 00:05:05'),(682,16,2,19.682438,-98.891014,'2026-04-22 00:05:08'),(683,16,2,19.682438,-98.891014,'2026-04-22 00:05:12'),(684,16,2,19.682438,-98.891014,'2026-04-22 00:05:16'),(685,16,2,19.682438,-98.891014,'2026-04-22 00:05:20'),(686,16,2,19.682438,-98.891014,'2026-04-22 00:05:24'),(687,16,2,19.682438,-98.891014,'2026-04-22 00:05:28'),(688,16,2,19.682438,-98.891014,'2026-04-22 00:05:32'),(689,16,2,19.682438,-98.891014,'2026-04-22 00:05:36'),(690,16,2,19.682451,-98.890945,'2026-04-22 00:05:41'),(691,16,2,19.682451,-98.890945,'2026-04-22 00:05:44'),(692,16,2,19.682451,-98.890945,'2026-04-22 00:05:48'),(693,16,2,19.682451,-98.890945,'2026-04-22 00:05:52'),(694,16,2,19.682451,-98.890945,'2026-04-22 00:05:56'),(695,16,2,19.682451,-98.890945,'2026-04-22 00:06:00'),(696,16,2,19.682451,-98.890945,'2026-04-22 00:06:04'),(697,16,2,19.682451,-98.890945,'2026-04-22 00:06:08'),(698,16,2,19.682451,-98.890945,'2026-04-22 00:06:12'),(699,16,2,19.682451,-98.890945,'2026-04-22 00:06:17'),(700,16,2,19.682451,-98.890945,'2026-04-22 00:06:20'),(701,16,2,19.682451,-98.890945,'2026-04-22 00:06:24'),(702,16,2,19.682451,-98.890945,'2026-04-22 00:06:28'),(703,16,2,19.682451,-98.890945,'2026-04-22 00:06:32'),(704,16,2,19.682451,-98.890945,'2026-04-22 00:06:36'),(705,16,2,19.682451,-98.890945,'2026-04-22 00:06:41'),(706,16,2,19.682451,-98.890945,'2026-04-22 00:06:44'),(707,16,2,19.682451,-98.890945,'2026-04-22 00:06:48'),(708,16,2,19.682438,-98.891014,'2026-04-22 00:06:53'),(709,16,2,19.682438,-98.891014,'2026-04-22 00:06:56'),(710,16,2,19.682438,-98.891014,'2026-04-22 00:06:59'),(711,16,2,19.682438,-98.891014,'2026-04-22 00:07:04'),(712,16,2,19.682438,-98.891014,'2026-04-22 00:07:08'),(713,16,2,19.682438,-98.891014,'2026-04-22 00:07:12'),(714,16,2,19.682438,-98.891014,'2026-04-22 00:07:16'),(715,16,2,19.682438,-98.891014,'2026-04-22 00:07:20'),(716,16,2,19.682438,-98.891014,'2026-04-22 00:07:24'),(717,16,2,19.682438,-98.891014,'2026-04-22 00:07:29'),(718,16,2,19.682438,-98.891014,'2026-04-22 00:07:32'),(719,16,2,19.682438,-98.891014,'2026-04-22 00:07:36'),(720,16,2,19.682449,-98.890953,'2026-04-22 00:07:41'),(721,16,2,19.682449,-98.890953,'2026-04-22 00:07:44'),(722,16,2,19.682449,-98.890953,'2026-04-22 00:07:48'),(723,16,2,19.682449,-98.890953,'2026-04-22 00:07:52'),(724,16,2,19.682449,-98.890953,'2026-04-22 00:07:56'),(725,16,2,19.682449,-98.890953,'2026-04-22 00:08:00'),(726,16,2,19.682449,-98.890953,'2026-04-22 00:08:05'),(727,16,2,19.682449,-98.890953,'2026-04-22 00:08:08'),(728,16,2,19.682449,-98.890953,'2026-04-22 00:08:12'),(729,16,2,19.682449,-98.890953,'2026-04-22 00:08:16'),(730,16,2,19.682449,-98.890953,'2026-04-22 00:08:20'),(731,16,2,19.682449,-98.890953,'2026-04-22 00:08:24'),(732,16,2,19.682449,-98.890953,'2026-04-22 00:08:28'),(733,16,2,19.682449,-98.890953,'2026-04-22 00:08:32'),(734,16,2,19.682449,-98.890953,'2026-04-22 00:08:37'),(735,16,2,19.682453,-98.890938,'2026-04-22 00:08:41'),(736,16,2,19.682453,-98.890938,'2026-04-22 00:08:44'),(737,16,2,19.682453,-98.890938,'2026-04-22 00:08:48'),(738,16,2,19.682446,-98.890976,'2026-04-22 00:08:52'),(739,16,2,19.682446,-98.890976,'2026-04-22 00:08:56'),(740,16,2,19.682446,-98.890976,'2026-04-22 00:09:00'),(741,16,2,19.682446,-98.890976,'2026-04-22 00:09:04'),(742,16,2,19.682446,-98.890976,'2026-04-22 00:09:08'),(743,16,2,19.682446,-98.890976,'2026-04-22 00:09:12'),(744,16,2,19.682446,-98.890976,'2026-04-22 00:09:17'),(745,16,2,19.682446,-98.890976,'2026-04-22 00:09:20'),(746,16,2,19.682446,-98.890976,'2026-04-22 00:09:24'),(747,16,2,19.682446,-98.890976,'2026-04-22 00:09:28'),(748,16,2,19.682446,-98.890976,'2026-04-22 00:09:33'),(749,16,2,19.682446,-98.890976,'2026-04-22 00:09:36'),(750,16,2,19.682453,-98.890938,'2026-04-22 00:09:40'),(751,16,2,19.682453,-98.890938,'2026-04-22 00:09:44'),(752,16,2,19.682453,-98.890938,'2026-04-22 00:09:48'),(753,16,2,19.682449,-98.890953,'2026-04-22 00:09:53'),(754,16,2,19.682449,-98.890953,'2026-04-22 00:09:56'),(755,16,2,19.682449,-98.890953,'2026-04-22 00:10:00'),(756,16,2,19.682449,-98.890953,'2026-04-22 00:10:04'),(757,16,2,19.682449,-98.890953,'2026-04-22 00:10:08'),(758,16,2,19.682449,-98.890953,'2026-04-22 00:10:12'),(759,16,2,19.682449,-98.890953,'2026-04-22 00:10:16'),(760,16,2,19.682449,-98.890953,'2026-04-22 00:10:20'),(761,16,2,19.682449,-98.890953,'2026-04-22 00:10:24'),(762,16,2,19.682449,-98.890953,'2026-04-22 00:10:29'),(763,16,2,19.682449,-98.890953,'2026-04-22 00:10:32'),(764,16,2,19.682449,-98.890953,'2026-04-22 00:10:36'),(765,16,2,19.682449,-98.890953,'2026-04-22 00:10:40'),(766,16,2,19.682449,-98.890953,'2026-04-22 00:10:44'),(767,16,2,19.682449,-98.890953,'2026-04-22 00:10:48'),(768,16,2,19.682438,-98.891014,'2026-04-22 00:10:52'),(769,16,2,19.682438,-98.891014,'2026-04-22 00:10:57'),(770,16,2,19.682438,-98.891014,'2026-04-22 00:11:00'),(771,16,2,19.682438,-98.891014,'2026-04-22 00:11:04'),(772,16,2,19.682438,-98.891014,'2026-04-22 00:11:08'),(773,16,2,19.682438,-98.891014,'2026-04-22 00:11:12'),(774,16,2,19.682438,-98.891014,'2026-04-22 00:11:16'),(775,16,2,19.682438,-98.891014,'2026-04-22 00:11:20'),(776,16,2,19.682438,-98.891014,'2026-04-22 00:11:24'),(777,16,2,19.682438,-98.891014,'2026-04-22 00:11:28'),(778,16,2,19.682438,-98.891014,'2026-04-22 00:11:32'),(779,16,2,19.682438,-98.891014,'2026-04-22 00:11:36'),(780,16,2,19.682449,-98.890953,'2026-04-22 00:11:41'),(781,16,2,19.682449,-98.890953,'2026-04-22 00:11:50'),(782,16,2,19.682446,-98.890976,'2026-04-22 00:12:50'),(783,16,2,19.682449,-98.890953,'2026-04-22 00:13:50'),(784,16,2,19.682438,-98.891014,'2026-04-22 00:14:50'),(785,16,2,19.682453,-98.890938,'2026-04-22 00:15:50'),(786,16,2,19.682446,-98.890976,'2026-04-22 00:16:50'),(787,16,2,19.682447,-98.890938,'2026-04-22 00:17:50'),(788,16,2,19.682449,-98.890953,'2026-04-22 00:18:50'),(789,16,2,19.682438,-98.891014,'2026-04-22 00:19:50'),(790,16,2,19.682438,-98.891014,'2026-04-22 00:20:50'),(791,16,2,19.682438,-98.891014,'2026-04-22 00:21:50'),(792,16,2,19.682449,-98.890953,'2026-04-22 00:22:50'),(793,16,2,19.682449,-98.890953,'2026-04-22 00:23:50'),(794,16,2,19.682447,-98.890938,'2026-04-22 00:24:50'),(795,16,2,19.682447,-98.890938,'2026-04-22 00:25:50'),(796,16,2,19.682449,-98.890953,'2026-04-22 00:26:50'),(797,16,2,19.682449,-98.890953,'2026-04-22 00:27:50'),(798,16,2,19.682447,-98.890938,'2026-04-22 00:28:50'),(799,16,2,19.682447,-98.890938,'2026-04-22 00:29:50'),(800,16,2,19.682453,-98.890938,'2026-04-22 00:30:50'),(801,16,2,19.682453,-98.890938,'2026-04-22 00:31:50'),(802,16,2,19.682451,-98.890923,'2026-04-22 00:32:50'),(803,16,2,19.682449,-98.890938,'2026-04-22 00:33:50'),(804,16,2,19.682447,-98.890938,'2026-04-22 00:34:50'),(805,16,2,19.682447,-98.890938,'2026-04-22 00:35:50'),(806,16,2,19.682438,-98.891014,'2026-04-22 00:36:50'),(807,16,2,19.682438,-98.891014,'2026-04-22 00:37:51'),(808,16,2,19.682438,-98.891014,'2026-04-22 00:38:50'),(809,16,2,19.682438,-98.891014,'2026-04-22 00:39:50'),(810,16,2,19.682446,-98.890976,'2026-04-22 00:40:50'),(811,16,2,19.682438,-98.891014,'2026-04-22 00:41:50'),(812,16,2,19.682438,-98.891014,'2026-04-22 00:42:50'),(813,16,2,19.682446,-98.890976,'2026-04-22 00:43:50'),(814,16,2,19.682446,-98.890976,'2026-04-22 00:44:50'),(815,16,2,19.682438,-98.891014,'2026-04-22 00:45:50'),(816,16,2,19.682438,-98.891014,'2026-04-22 00:46:50'),(817,16,2,19.682446,-98.890976,'2026-04-22 00:47:50'),(818,16,2,19.682446,-98.890976,'2026-04-22 00:48:50'),(819,16,2,19.682446,-98.890976,'2026-04-22 00:49:50'),(820,16,2,19.682446,-98.890976,'2026-04-22 00:50:50'),(821,16,2,19.682446,-98.890976,'2026-04-22 00:51:22'),(822,16,2,19.682446,-98.890976,'2026-04-22 00:51:23'),(823,16,2,19.682446,-98.890976,'2026-04-22 00:51:27'),(824,16,2,19.682453,-98.890938,'2026-04-22 00:56:37'),(825,16,2,19.682453,-98.890938,'2026-04-22 00:56:41'),(826,16,2,19.682453,-98.890938,'2026-04-22 00:56:44'),(827,16,2,19.682453,-98.890938,'2026-04-22 00:56:48'),(828,16,2,19.682453,-98.890938,'2026-04-22 00:56:52'),(829,16,2,19.682453,-98.890938,'2026-04-22 00:56:54'),(830,16,2,19.682453,-98.890938,'2026-04-22 00:56:57'),(831,16,2,19.682446,-98.890976,'2026-04-22 00:57:00'),(832,16,2,19.682446,-98.890976,'2026-04-22 00:57:05'),(833,16,2,19.682446,-98.890976,'2026-04-22 00:57:09'),(834,16,2,19.682446,-98.890976,'2026-04-22 00:57:13'),(835,16,2,19.682446,-98.890976,'2026-04-22 00:57:17'),(836,16,2,19.682446,-98.890976,'2026-04-22 00:57:21'),(837,16,2,19.682446,-98.890976,'2026-04-22 00:57:25'),(838,16,2,19.682446,-98.890976,'2026-04-22 00:57:29'),(839,16,2,19.682446,-98.890976,'2026-04-22 00:57:34'),(840,16,2,19.682446,-98.890976,'2026-04-22 00:57:37'),(841,16,2,19.682446,-98.890976,'2026-04-22 00:57:41'),(842,16,2,19.682446,-98.890976,'2026-04-22 00:57:45'),(843,16,2,19.682453,-98.890938,'2026-04-22 00:57:49'),(844,16,2,19.682453,-98.890938,'2026-04-22 00:57:53'),(845,16,2,19.682453,-98.890938,'2026-04-22 00:57:57'),(846,16,2,19.682453,-98.890938,'2026-04-22 00:58:01'),(847,16,2,19.682453,-98.890938,'2026-04-22 00:58:05'),(848,16,2,19.682453,-98.890938,'2026-04-22 00:58:10'),(849,16,2,19.682453,-98.890938,'2026-04-22 00:58:13'),(850,16,2,19.682453,-98.890938,'2026-04-22 00:58:17'),(851,16,2,19.682453,-98.890938,'2026-04-22 00:58:21'),(852,16,2,19.682453,-98.890938,'2026-04-22 00:58:25'),(853,16,2,19.682453,-98.890938,'2026-04-22 00:58:29'),(854,16,2,19.682453,-98.890938,'2026-04-22 00:58:33'),(855,16,2,19.682453,-98.890938,'2026-04-22 00:58:37'),(856,16,2,19.682453,-98.890938,'2026-04-22 00:58:41'),(857,16,2,19.682453,-98.890938,'2026-04-22 00:58:46'),(858,16,2,19.682453,-98.890938,'2026-04-22 00:58:49'),(859,16,2,19.682453,-98.890938,'2026-04-22 00:58:53'),(860,16,2,19.682453,-98.890938,'2026-04-22 00:58:57'),(861,16,2,19.682438,-98.891014,'2026-04-22 00:59:01'),(862,16,2,19.682438,-98.891014,'2026-04-22 00:59:05'),(863,16,2,19.682438,-98.891014,'2026-04-22 00:59:09'),(864,16,2,19.682438,-98.891014,'2026-04-22 00:59:13'),(865,16,2,19.682438,-98.891014,'2026-04-22 00:59:17'),(866,16,2,19.682438,-98.891014,'2026-04-22 00:59:22'),(867,16,2,19.682438,-98.891014,'2026-04-22 00:59:25'),(868,16,2,19.682438,-98.891014,'2026-04-22 00:59:29'),(869,16,2,19.682438,-98.891014,'2026-04-22 00:59:33'),(870,16,2,19.682446,-98.890976,'2026-04-22 00:59:37'),(871,16,2,19.682446,-98.890976,'2026-04-22 00:59:41'),(872,16,2,19.682446,-98.890976,'2026-04-22 00:59:46'),(873,16,2,19.682453,-98.890938,'2026-04-22 00:59:49'),(874,16,2,19.682453,-98.890938,'2026-04-22 00:59:53'),(875,16,2,19.682453,-98.890938,'2026-04-22 00:59:58'),(876,16,2,19.682453,-98.890938,'2026-04-22 01:00:01'),(877,16,2,19.682453,-98.890938,'2026-04-22 01:00:05'),(878,16,2,19.682453,-98.890938,'2026-04-22 01:00:09'),(879,16,2,19.682453,-98.890938,'2026-04-22 01:00:13'),(880,16,2,19.682453,-98.890938,'2026-04-22 01:00:17'),(881,16,2,19.682453,-98.890938,'2026-04-22 01:00:21'),(882,16,2,19.682453,-98.890938,'2026-04-22 01:00:25'),(883,16,2,19.682453,-98.890938,'2026-04-22 01:00:29'),(884,16,2,19.682453,-98.890938,'2026-04-22 01:00:34'),(885,16,2,19.682453,-98.890938,'2026-04-22 01:00:37'),(886,16,2,19.682453,-98.890938,'2026-04-22 01:00:41'),(887,16,2,19.682453,-98.890938,'2026-04-22 01:00:45'),(888,16,2,19.682453,-98.890938,'2026-04-22 01:00:49'),(889,16,2,19.682453,-98.890938,'2026-04-22 01:00:53'),(890,16,2,19.682453,-98.890938,'2026-04-22 01:00:57'),(891,16,2,19.682446,-98.890976,'2026-04-22 01:01:01'),(892,16,2,19.682446,-98.890976,'2026-04-22 01:01:05'),(893,16,2,19.682446,-98.890976,'2026-04-22 01:01:10'),(894,16,2,19.682446,-98.890976,'2026-04-22 01:01:13'),(895,16,2,19.682446,-98.890976,'2026-04-22 01:01:17'),(896,16,2,19.682446,-98.890976,'2026-04-22 01:01:21'),(897,16,2,19.682446,-98.890976,'2026-04-22 01:01:25'),(898,16,2,19.682446,-98.890976,'2026-04-22 01:01:29'),(899,16,2,19.682446,-98.890976,'2026-04-22 01:01:33'),(900,16,2,19.682446,-98.890976,'2026-04-22 01:01:37'),(901,16,2,19.682446,-98.890976,'2026-04-22 01:01:41'),(902,16,2,19.682446,-98.890976,'2026-04-22 01:01:46'),(903,16,2,19.682453,-98.89093,'2026-04-22 01:01:49'),(904,16,2,19.682453,-98.89093,'2026-04-22 01:01:53'),(905,16,2,19.682453,-98.89093,'2026-04-22 01:01:57'),(906,16,2,19.682453,-98.89093,'2026-04-22 01:02:01'),(907,16,2,19.682451,-98.890945,'2026-04-22 01:02:50'),(908,16,2,19.682453,-98.890938,'2026-04-22 01:03:50'),(909,16,2,19.682453,-98.890938,'2026-04-22 01:04:50'),(910,16,2,19.682446,-98.890976,'2026-04-22 01:05:28'),(911,16,2,19.682446,-98.890976,'2026-04-22 01:05:28'),(912,16,2,19.682446,-98.890976,'2026-04-22 01:05:33'),(913,16,2,19.682446,-98.890976,'2026-04-22 01:05:37'),(914,16,2,19.682446,-98.890976,'2026-04-22 01:05:41'),(915,16,2,19.682446,-98.890976,'2026-04-22 01:05:45'),(916,16,2,19.682453,-98.890938,'2026-04-22 01:05:49'),(917,16,2,19.682453,-98.890938,'2026-04-22 01:05:53'),(918,16,2,19.682453,-98.890938,'2026-04-22 01:05:58'),(919,16,2,19.682453,-98.890938,'2026-04-22 01:06:01'),(920,16,2,19.682453,-98.890938,'2026-04-22 01:06:05'),(921,16,2,19.682453,-98.890938,'2026-04-22 01:06:09'),(922,16,2,19.682453,-98.890938,'2026-04-22 01:06:13'),(923,16,2,19.682453,-98.890938,'2026-04-22 01:06:17'),(924,16,2,19.682453,-98.890938,'2026-04-22 01:06:21'),(925,16,2,19.682453,-98.890938,'2026-04-22 01:06:25'),(926,16,2,19.682453,-98.890938,'2026-04-22 01:06:29'),(927,16,2,19.682453,-98.890938,'2026-04-22 01:06:34'),(928,16,2,19.682453,-98.890938,'2026-04-22 01:06:37'),(929,16,2,19.682453,-98.890938,'2026-04-22 01:06:41'),(930,16,2,19.682453,-98.890938,'2026-04-22 01:06:45'),(931,16,2,19.682453,-98.890938,'2026-04-22 01:06:49'),(932,16,2,19.682453,-98.890938,'2026-04-22 01:06:53'),(933,16,2,19.682453,-98.890938,'2026-04-22 01:06:57'),(934,16,2,19.682438,-98.891014,'2026-04-22 01:07:01'),(935,16,2,19.682438,-98.891014,'2026-04-22 01:07:05'),(936,16,2,19.682438,-98.891014,'2026-04-22 01:07:10'),(937,16,2,19.682438,-98.891014,'2026-04-22 01:07:13'),(938,16,2,19.682438,-98.891014,'2026-04-22 01:07:17'),(939,16,2,19.682438,-98.891014,'2026-04-22 01:07:21'),(940,16,2,19.682438,-98.891014,'2026-04-22 01:07:25'),(941,16,2,19.682438,-98.891014,'2026-04-22 01:07:29'),(942,16,2,19.682438,-98.891014,'2026-04-22 01:07:33'),(943,16,2,19.682438,-98.891014,'2026-04-22 01:07:37'),(944,16,2,19.682438,-98.891014,'2026-04-22 01:07:41'),(945,16,2,19.682438,-98.891014,'2026-04-22 01:07:45'),(946,16,2,19.682451,-98.890938,'2026-04-22 01:07:49'),(947,16,2,19.682451,-98.890938,'2026-04-22 01:07:53'),(948,16,2,19.682451,-98.890938,'2026-04-22 01:07:57'),(949,16,2,19.682451,-98.890938,'2026-04-22 01:08:01'),(950,16,2,19.682451,-98.890938,'2026-04-22 01:08:05'),(951,16,2,19.682451,-98.890938,'2026-04-22 01:08:10'),(952,16,2,19.682451,-98.890938,'2026-04-22 01:08:13'),(953,16,2,19.682451,-98.890938,'2026-04-22 01:08:17'),(954,16,2,19.682451,-98.890938,'2026-04-22 01:08:22'),(955,16,2,19.682451,-98.890938,'2026-04-22 01:08:25'),(956,16,2,19.682451,-98.890938,'2026-04-22 01:08:29'),(957,16,2,19.682451,-98.890938,'2026-04-22 01:08:33'),(958,16,2,19.682451,-98.890938,'2026-04-22 01:08:37'),(959,16,2,19.682451,-98.890938,'2026-04-22 01:08:41'),(960,16,2,19.682451,-98.890938,'2026-04-22 01:08:45'),(961,16,2,19.682451,-98.890938,'2026-04-22 01:08:49'),(962,16,2,19.682451,-98.890938,'2026-04-22 01:08:53'),(963,16,2,19.682451,-98.890938,'2026-04-22 01:08:58'),(964,16,2,19.682438,-98.891014,'2026-04-22 01:09:01'),(965,16,2,19.682438,-98.891014,'2026-04-22 01:09:05'),(966,16,2,19.682438,-98.891014,'2026-04-22 01:09:09'),(967,16,2,19.682438,-98.891014,'2026-04-22 01:09:13'),(968,16,2,19.682438,-98.891014,'2026-04-22 01:09:17'),(969,16,2,19.682438,-98.891014,'2026-04-22 01:09:21'),(970,16,2,19.682438,-98.891014,'2026-04-22 01:09:25'),(971,16,2,19.682438,-98.891014,'2026-04-22 01:09:29'),(972,16,2,19.682438,-98.891014,'2026-04-22 01:09:34'),(973,16,2,19.682438,-98.891014,'2026-04-22 01:09:37'),(974,16,2,19.682438,-98.891014,'2026-04-22 01:09:41'),(975,16,2,19.682438,-98.891014,'2026-04-22 01:09:45'),(976,16,2,19.682738,-98.890881,'2026-04-22 01:09:49'),(977,16,2,19.682738,-98.890881,'2026-04-22 01:09:53'),(978,16,2,19.682738,-98.890881,'2026-04-22 01:09:57'),(979,16,2,19.682738,-98.890881,'2026-04-22 01:10:02'),(980,16,2,19.682738,-98.890881,'2026-04-22 01:10:05'),(981,16,2,19.682738,-98.890881,'2026-04-22 01:10:10'),(982,16,2,19.682738,-98.890881,'2026-04-22 01:10:13'),(983,16,2,19.682738,-98.890881,'2026-04-22 01:10:17'),(984,16,2,19.682738,-98.890881,'2026-04-22 01:10:21'),(985,16,2,19.682738,-98.890881,'2026-04-22 01:10:25'),(986,16,2,19.682738,-98.890881,'2026-04-22 01:10:29'),(987,16,2,19.682738,-98.890881,'2026-04-22 01:10:50'),(988,16,2,19.682438,-98.891014,'2026-04-22 01:11:10'),(989,16,2,19.682438,-98.891014,'2026-04-22 01:11:12'),(990,16,2,19.682438,-98.891014,'2026-04-22 01:11:16'),(991,16,2,19.682438,-98.891014,'2026-04-22 01:11:19'),(992,16,2,19.682438,-98.891014,'2026-04-22 01:11:21'),(993,16,2,19.682438,-98.891014,'2026-04-22 01:11:24'),(994,16,2,19.682438,-98.891014,'2026-04-22 01:11:29'),(995,16,2,19.682438,-98.891014,'2026-04-22 01:11:32'),(996,16,2,19.682438,-98.891014,'2026-04-22 01:11:36'),(997,16,2,19.682438,-98.891014,'2026-04-22 01:11:40'),(998,16,2,19.682455,-98.890953,'2026-04-22 01:11:44'),(999,16,2,19.682455,-98.890953,'2026-04-22 01:11:48'),(1000,16,2,19.682455,-98.890953,'2026-04-22 01:11:52'),(1001,16,2,19.682738,-98.890881,'2026-04-22 01:11:56'),(1002,16,2,19.682738,-98.890881,'2026-04-22 01:12:00'),(1003,16,2,19.682738,-98.890881,'2026-04-22 01:12:04'),(1004,16,2,19.682738,-98.890881,'2026-04-22 01:12:08'),(1005,16,2,19.682738,-98.890881,'2026-04-22 01:12:12'),(1006,16,2,19.682738,-98.890881,'2026-04-22 01:12:16'),(1007,16,2,19.682738,-98.890881,'2026-04-22 01:12:20'),(1008,16,2,19.682738,-98.890881,'2026-04-22 01:12:24'),(1009,16,2,19.682738,-98.890881,'2026-04-22 01:12:28'),(1010,16,2,19.682738,-98.890881,'2026-04-22 01:12:32'),(1011,16,2,19.682738,-98.890881,'2026-04-22 01:12:36'),(1012,16,2,19.682738,-98.890881,'2026-04-22 01:12:40'),(1013,16,2,19.682738,-98.890881,'2026-04-22 01:12:44'),(1014,16,2,19.682738,-98.890881,'2026-04-22 01:12:48'),(1015,16,2,19.682738,-98.890881,'2026-04-22 01:12:52'),(1016,16,2,19.682738,-98.890881,'2026-04-22 01:12:56'),(1017,16,2,19.682738,-98.890881,'2026-04-22 01:13:00'),(1018,16,2,19.682738,-98.890881,'2026-04-22 01:13:04'),(1019,16,2,19.682438,-98.891014,'2026-04-22 01:13:08'),(1020,16,2,19.682438,-98.891014,'2026-04-22 01:13:12'),(1021,16,2,19.682438,-98.891014,'2026-04-22 01:13:16'),(1022,16,2,19.682438,-98.891014,'2026-04-22 01:13:20'),(1023,16,2,19.682438,-98.891014,'2026-04-22 01:13:24'),(1024,16,2,19.682438,-98.891014,'2026-04-22 01:13:28'),(1025,16,2,19.682438,-98.891014,'2026-04-22 01:13:32'),(1026,16,2,19.682438,-98.891014,'2026-04-22 01:13:36'),(1027,16,2,19.682438,-98.891014,'2026-04-22 01:13:40'),(1028,16,2,19.682455,-98.890953,'2026-04-22 01:13:44'),(1029,16,2,19.682455,-98.890953,'2026-04-22 01:13:48'),(1030,16,2,19.682455,-98.890953,'2026-04-22 01:13:53'),(1031,16,2,19.682629,-98.890819,'2026-04-22 01:13:56'),(1032,16,2,19.682629,-98.890819,'2026-04-22 01:14:00'),(1033,16,2,19.682629,-98.890819,'2026-04-22 01:14:04'),(1034,16,2,19.682629,-98.890819,'2026-04-22 01:14:08'),(1035,16,2,19.682629,-98.890819,'2026-04-22 01:14:12'),(1036,16,2,19.682629,-98.890819,'2026-04-22 01:14:17'),(1037,16,2,19.682629,-98.890819,'2026-04-22 01:14:20'),(1038,16,2,19.682629,-98.890819,'2026-04-22 01:14:24'),(1039,16,2,19.682629,-98.890819,'2026-04-22 01:14:29'),(1040,16,2,19.682629,-98.890819,'2026-04-22 01:14:32'),(1041,16,2,19.682629,-98.890819,'2026-04-22 01:14:50'),(1042,16,2,19.682438,-98.891014,'2026-04-22 01:15:50'),(1043,16,2,19.682446,-98.890976,'2026-04-22 01:16:50'),(1044,16,2,19.682446,-98.890976,'2026-04-22 01:17:50'),(1045,16,2,19.682438,-98.891014,'2026-04-22 01:18:50'),(1046,16,2,19.682438,-98.891014,'2026-04-22 01:18:54'),(1047,16,2,19.682438,-98.891014,'2026-04-22 01:18:56'),(1048,16,2,19.682438,-98.891014,'2026-04-22 01:18:57'),(1049,16,2,19.682438,-98.891014,'2026-04-22 01:19:04'),(1050,16,2,19.682438,-98.891014,'2026-04-22 01:19:07'),(1051,16,2,19.682438,-98.891014,'2026-04-22 01:19:11'),(1052,16,2,19.682438,-98.891014,'2026-04-22 01:19:15'),(1053,16,2,19.682438,-98.891014,'2026-04-22 01:19:19'),(1054,16,2,19.682438,-98.891014,'2026-04-22 01:19:23'),(1055,16,2,19.682438,-98.891014,'2026-04-22 01:19:27'),(1056,16,2,19.682438,-98.891014,'2026-04-22 01:19:31'),(1057,16,2,19.682438,-98.891014,'2026-04-22 01:19:35'),(1058,16,2,19.682438,-98.891014,'2026-04-22 01:19:40'),(1059,16,2,19.682438,-98.891014,'2026-04-22 01:19:43'),(1060,16,2,19.682438,-98.891014,'2026-04-22 01:19:47'),(1061,16,2,19.682438,-98.891014,'2026-04-22 01:19:51'),(1062,16,2,19.682438,-98.891014,'2026-04-22 01:19:55'),(1063,16,2,19.682438,-98.891014,'2026-04-22 01:19:59'),(1064,16,2,19.682438,-98.891014,'2026-04-22 01:20:03'),(1065,16,2,19.682438,-98.891014,'2026-04-22 01:20:07'),(1066,16,2,19.682438,-98.891014,'2026-04-22 01:20:11'),(1067,16,2,19.682438,-98.891014,'2026-04-22 01:20:15'),(1068,16,2,19.682438,-98.891014,'2026-04-22 01:20:19'),(1069,16,2,19.682438,-98.891014,'2026-04-22 01:20:23'),(1070,16,2,19.682438,-98.891014,'2026-04-22 01:20:27'),(1071,16,2,19.682438,-98.891014,'2026-04-22 01:20:31'),(1072,16,2,19.682438,-98.891014,'2026-04-22 01:20:35'),(1073,16,2,19.682438,-98.891014,'2026-04-22 01:20:39'),(1074,16,2,19.682438,-98.891014,'2026-04-22 01:20:43'),(1075,16,2,19.682438,-98.891014,'2026-04-22 01:20:47'),(1076,16,2,19.682438,-98.891014,'2026-04-22 01:20:52'),(1077,16,2,19.682438,-98.891014,'2026-04-22 01:20:55'),(1078,16,2,19.682438,-98.891014,'2026-04-22 01:20:59'),(1079,16,2,19.682438,-98.891014,'2026-04-22 01:21:03'),(1080,16,2,19.682438,-98.891014,'2026-04-22 01:21:07'),(1081,16,2,19.682438,-98.891014,'2026-04-22 01:21:11'),(1082,16,2,19.682438,-98.891014,'2026-04-22 01:21:15'),(1083,16,2,19.682438,-98.891014,'2026-04-22 01:21:19'),(1084,16,2,19.682438,-98.891014,'2026-04-22 01:21:23'),(1085,16,2,19.682438,-98.891014,'2026-04-22 01:21:28'),(1086,16,2,19.682438,-98.891014,'2026-04-22 01:21:31'),(1087,16,2,19.682438,-98.891014,'2026-04-22 01:21:35'),(1088,16,2,19.682438,-98.891014,'2026-04-22 01:21:39'),(1089,16,2,19.682438,-98.891014,'2026-04-22 01:21:43'),(1090,16,2,19.682438,-98.891014,'2026-04-22 01:21:47'),(1091,16,2,19.682438,-98.891014,'2026-04-22 01:21:52'),(1092,16,2,19.682438,-98.891014,'2026-04-22 01:21:55'),(1093,16,2,19.682438,-98.891014,'2026-04-22 01:21:59'),(1094,16,2,19.682438,-98.891014,'2026-04-22 01:22:03'),(1095,16,2,19.682438,-98.891014,'2026-04-22 01:22:07'),(1096,16,2,19.682438,-98.891014,'2026-04-22 01:22:11'),(1097,16,2,19.682438,-98.891014,'2026-04-22 01:22:15'),(1098,16,2,19.682438,-98.891014,'2026-04-22 01:22:19'),(1099,16,2,19.682438,-98.891014,'2026-04-22 01:22:23'),(1100,16,2,19.682438,-98.891014,'2026-04-22 01:22:27'),(1101,16,2,19.682438,-98.891014,'2026-04-22 01:22:31'),(1102,16,2,19.682438,-98.891014,'2026-04-22 01:22:35'),(1103,16,2,19.682438,-98.891014,'2026-04-22 01:22:40'),(1104,16,2,19.682438,-98.891014,'2026-04-22 01:22:43'),(1105,16,2,19.682438,-98.891014,'2026-04-22 01:22:47'),(1106,16,2,19.682438,-98.891014,'2026-04-22 01:22:51'),(1107,16,2,19.682438,-98.891014,'2026-04-22 01:22:55'),(1108,16,2,19.682463,-98.890907,'2026-04-22 01:22:59'),(1109,16,2,19.682463,-98.890907,'2026-04-22 01:23:03'),(1110,16,2,19.682463,-98.890907,'2026-04-22 01:23:07'),(1111,16,2,19.682463,-98.890907,'2026-04-22 01:23:11'),(1112,16,2,19.682463,-98.890907,'2026-04-22 01:23:16'),(1113,16,2,19.682463,-98.890907,'2026-04-22 01:23:19'),(1114,16,2,19.682463,-98.890907,'2026-04-22 01:23:23'),(1115,16,2,19.682463,-98.890907,'2026-04-22 01:23:27'),(1116,16,2,19.682463,-98.890907,'2026-04-22 01:23:31'),(1117,16,2,19.682463,-98.890907,'2026-04-22 01:23:35'),(1118,16,2,19.682463,-98.890907,'2026-04-22 01:23:39'),(1119,16,2,19.682463,-98.890907,'2026-04-22 01:23:43'),(1120,16,2,19.682463,-98.890907,'2026-04-22 01:23:47'),(1121,16,2,19.682463,-98.890907,'2026-04-22 01:23:52'),(1122,16,2,19.682463,-98.890907,'2026-04-22 01:23:55'),(1123,16,2,19.682463,-98.890907,'2026-04-22 01:23:59'),(1124,16,2,19.682463,-98.890907,'2026-04-22 01:24:03'),(1125,16,2,19.682463,-98.890907,'2026-04-22 01:24:07'),(1126,16,2,19.682438,-98.891014,'2026-04-22 01:24:11'),(1127,16,2,19.682438,-98.891014,'2026-04-22 01:24:15'),(1128,16,2,19.682438,-98.891014,'2026-04-22 01:24:16'),(1129,16,2,19.682438,-98.891014,'2026-04-22 01:24:19'),(1130,16,2,19.682438,-98.891014,'2026-04-22 01:24:23'),(1131,16,2,19.682438,-98.891014,'2026-04-22 01:24:32'),(1132,16,2,19.682438,-98.891014,'2026-04-22 01:24:32'),(1133,16,2,19.682438,-98.891014,'2026-04-22 01:24:36'),(1134,16,2,19.682438,-98.891014,'2026-04-22 01:24:40'),(1135,16,2,19.682438,-98.891014,'2026-04-22 01:24:44'),(1136,16,2,19.682438,-98.891014,'2026-04-22 01:24:48'),(1137,16,2,19.682438,-98.891014,'2026-04-22 01:24:53'),(1138,16,2,19.682438,-98.891014,'2026-04-22 01:24:56'),(1139,16,2,19.682738,-98.890881,'2026-04-22 01:25:00'),(1140,16,2,19.682738,-98.890881,'2026-04-22 01:25:04'),(1141,16,2,19.682738,-98.890881,'2026-04-22 01:25:08'),(1142,16,2,19.682738,-98.890881,'2026-04-22 01:25:12'),(1143,16,2,19.682738,-98.890881,'2026-04-22 01:25:16'),(1144,16,2,19.682738,-98.890881,'2026-04-22 01:25:20'),(1145,16,2,19.682738,-98.890881,'2026-04-22 01:25:24'),(1146,16,2,19.682738,-98.890881,'2026-04-22 01:25:50'),(1147,16,2,19.682738,-98.890881,'2026-04-22 01:25:52'),(1148,16,2,19.682738,-98.890881,'2026-04-22 01:25:54'),(1149,16,2,19.682738,-98.890881,'2026-04-22 01:25:58'),(1150,16,2,19.682738,-98.890881,'2026-04-22 01:26:02'),(1151,16,2,19.682738,-98.890881,'2026-04-22 01:26:06'),(1152,16,2,19.682738,-98.890881,'2026-04-22 01:26:10'),(1153,16,2,19.682446,-98.890976,'2026-04-22 01:26:14'),(1154,16,2,19.682446,-98.890976,'2026-04-22 01:26:18'),(1155,16,2,19.682446,-98.890976,'2026-04-22 01:26:22'),(1156,16,2,19.682446,-98.890976,'2026-04-22 01:26:26'),(1157,16,2,19.682446,-98.890976,'2026-04-22 01:26:33'),(1158,16,2,19.682446,-98.890976,'2026-04-22 01:26:36'),(1159,16,2,19.682446,-98.890976,'2026-04-22 01:26:40'),(1160,16,2,19.682446,-98.890976,'2026-04-22 01:26:44'),(1161,16,2,19.682446,-98.890976,'2026-04-22 01:26:48'),(1162,16,2,19.682446,-98.890976,'2026-04-22 01:26:52'),(1163,16,2,19.682446,-98.890976,'2026-04-22 01:26:56'),(1164,16,2,19.682738,-98.890881,'2026-04-22 01:27:01'),(1165,16,2,19.682738,-98.890881,'2026-04-22 01:27:04'),(1166,16,2,19.682738,-98.890881,'2026-04-22 01:27:09'),(1167,16,2,19.682738,-98.890881,'2026-04-22 01:27:12'),(1168,16,2,19.682738,-98.890881,'2026-04-22 01:27:16'),(1169,16,2,19.682738,-98.890881,'2026-04-22 01:27:20'),(1170,16,2,19.682738,-98.890881,'2026-04-22 01:27:24'),(1171,16,2,19.682738,-98.890881,'2026-04-22 01:27:28'),(1172,16,2,19.682738,-98.890881,'2026-04-22 01:27:32'),(1173,16,2,19.682738,-98.890881,'2026-04-22 01:27:36'),(1174,16,2,19.682738,-98.890881,'2026-04-22 01:27:40'),(1175,16,2,19.682738,-98.890881,'2026-04-22 01:27:45'),(1176,16,2,19.682738,-98.890881,'2026-04-22 01:27:48'),(1177,16,2,19.682738,-98.890881,'2026-04-22 01:27:52'),(1178,16,2,19.682738,-98.890881,'2026-04-22 01:27:56'),(1179,16,2,19.682738,-98.890881,'2026-04-22 01:27:58'),(1180,16,2,19.682738,-98.890881,'2026-04-22 01:28:01'),(1181,16,2,19.682738,-98.890881,'2026-04-22 01:28:05'),(1182,16,2,19.682738,-98.890881,'2026-04-22 01:28:09'),(1183,16,2,19.682438,-98.891014,'2026-04-22 01:28:13'),(1184,16,2,19.682438,-98.891014,'2026-04-22 01:28:17'),(1185,16,2,19.682438,-98.891014,'2026-04-22 01:28:22'),(1186,16,2,19.682438,-98.891014,'2026-04-22 01:28:25'),(1187,16,2,19.682438,-98.891014,'2026-04-22 01:28:29'),(1188,16,2,19.682438,-98.891014,'2026-04-22 01:28:33'),(1189,16,2,19.682438,-98.891014,'2026-04-22 01:28:37'),(1190,16,2,19.682438,-98.891014,'2026-04-22 01:28:41'),(1191,16,2,19.682438,-98.891014,'2026-04-22 01:28:45'),(1192,16,2,19.682453,-98.890968,'2026-04-22 01:28:49'),(1193,16,2,19.682453,-98.890968,'2026-04-22 01:28:53'),(1194,16,2,19.682453,-98.890968,'2026-04-22 01:28:57'),(1195,16,2,19.682738,-98.890881,'2026-04-22 01:29:01'),(1196,16,2,19.682738,-98.890881,'2026-04-22 01:29:05'),(1197,16,2,19.682738,-98.890881,'2026-04-22 01:29:10'),(1198,16,2,19.682738,-98.890881,'2026-04-22 01:29:13'),(1199,16,2,19.682738,-98.890881,'2026-04-22 01:29:17'),(1200,16,2,19.682738,-98.890881,'2026-04-22 01:29:22'),(1201,16,2,19.682738,-98.890881,'2026-04-22 01:29:25'),(1202,16,2,19.682738,-98.890881,'2026-04-22 01:29:29'),(1203,16,2,19.682738,-98.890881,'2026-04-22 01:29:33'),(1204,16,2,19.682738,-98.890881,'2026-04-22 01:29:37'),(1205,16,2,19.682738,-98.890881,'2026-04-22 01:29:41'),(1206,16,2,19.682738,-98.890881,'2026-04-22 01:29:46'),(1207,16,2,19.682738,-98.890881,'2026-04-22 01:29:49'),(1208,16,2,19.682738,-98.890881,'2026-04-22 01:29:53'),(1209,16,2,19.682738,-98.890881,'2026-04-22 01:29:57'),(1210,16,2,19.682738,-98.890881,'2026-04-22 01:30:01'),(1211,16,2,19.682738,-98.890881,'2026-04-22 01:30:05'),(1212,16,2,19.682738,-98.890881,'2026-04-22 01:30:09'),(1213,16,2,19.682438,-98.891014,'2026-04-22 01:30:13'),(1214,16,2,19.682438,-98.891014,'2026-04-22 01:30:17'),(1215,16,2,19.682438,-98.891014,'2026-04-22 01:30:21'),(1216,16,2,19.682438,-98.891014,'2026-04-22 01:30:25'),(1217,16,2,19.682438,-98.891014,'2026-04-22 01:30:29'),(1218,16,2,19.682438,-98.891014,'2026-04-22 01:30:33'),(1219,16,2,19.682446,-98.890976,'2026-04-22 01:30:37'),(1220,16,2,19.682446,-98.890976,'2026-04-22 01:30:41'),(1221,16,2,19.682446,-98.890976,'2026-04-22 01:30:46'),(1222,16,2,19.682457,-98.890945,'2026-04-22 01:30:49'),(1223,16,2,19.682457,-98.890945,'2026-04-22 01:30:53'),(1224,16,2,19.682457,-98.890945,'2026-04-22 01:30:57'),(1225,16,2,19.682738,-98.890881,'2026-04-22 01:31:01'),(1226,16,2,19.682738,-98.890881,'2026-04-22 01:31:04'),(1227,16,2,19.682738,-98.890881,'2026-04-22 01:31:09'),(1228,16,2,19.682738,-98.890881,'2026-04-22 01:31:13'),(1229,16,2,19.682738,-98.890881,'2026-04-22 01:31:16'),(1230,16,2,19.682738,-98.890881,'2026-04-22 01:31:20'),(1231,16,2,19.682738,-98.890881,'2026-04-22 01:31:25'),(1232,16,2,19.682738,-98.890881,'2026-04-22 01:31:29'),(1233,16,2,19.682738,-98.890881,'2026-04-22 01:31:32'),(1234,16,2,19.682738,-98.890881,'2026-04-22 01:31:37'),(1235,16,2,19.682738,-98.890881,'2026-04-22 01:31:41'),(1236,16,2,19.682738,-98.890881,'2026-04-22 01:31:44'),(1237,16,2,19.682738,-98.890881,'2026-04-22 01:31:49'),(1238,16,2,19.682738,-98.890881,'2026-04-22 01:31:53'),(1239,16,2,19.682738,-98.890881,'2026-04-22 01:31:57'),(1240,16,2,19.682738,-98.890881,'2026-04-22 01:32:01'),(1241,16,2,19.682738,-98.890881,'2026-04-22 01:32:05'),(1242,16,2,19.682738,-98.890881,'2026-04-22 01:32:09'),(1243,16,2,19.682438,-98.891014,'2026-04-22 01:32:14'),(1244,16,2,19.682438,-98.891014,'2026-04-22 01:32:17'),(1245,16,2,19.682438,-98.891014,'2026-04-22 01:32:21'),(1246,16,2,19.682438,-98.891014,'2026-04-22 01:32:26'),(1247,16,2,19.682438,-98.891014,'2026-04-22 01:32:29'),(1248,16,2,19.682438,-98.891014,'2026-04-22 01:32:33'),(1249,16,2,19.682438,-98.891014,'2026-04-22 01:32:37'),(1250,16,2,19.682438,-98.891014,'2026-04-22 01:32:41'),(1251,16,2,19.682438,-98.891014,'2026-04-22 01:32:45'),(1252,16,2,19.682438,-98.891014,'2026-04-22 01:32:50'),(1253,16,2,19.682438,-98.891014,'2026-04-22 01:32:59'),(1254,16,2,19.682738,-98.890881,'2026-04-22 01:33:01'),(1255,16,2,19.682738,-98.890881,'2026-04-22 01:33:04'),(1256,16,2,19.682738,-98.890881,'2026-04-22 01:33:09'),(1257,16,2,19.682738,-98.890881,'2026-04-22 01:33:13'),(1258,16,2,19.682738,-98.890881,'2026-04-22 01:33:17'),(1259,16,2,19.682738,-98.890881,'2026-04-22 01:33:21'),(1260,16,2,19.682738,-98.890881,'2026-04-22 01:33:26'),(1261,16,2,19.682738,-98.890881,'2026-04-22 01:33:29'),(1262,16,2,19.682738,-98.890881,'2026-04-22 01:33:33'),(1263,16,2,19.682738,-98.890881,'2026-04-22 01:33:37'),(1264,16,2,19.682738,-98.890881,'2026-04-22 01:33:41'),(1265,16,2,19.682738,-98.890881,'2026-04-22 01:33:45'),(1266,16,2,19.682738,-98.890881,'2026-04-22 01:33:50'),(1267,16,2,19.682738,-98.890881,'2026-04-22 01:33:53'),(1268,16,2,19.682738,-98.890881,'2026-04-22 01:33:57'),(1269,16,2,19.682738,-98.890881,'2026-04-22 01:34:01'),(1270,16,2,19.682738,-98.890881,'2026-04-22 01:34:05'),(1271,16,2,19.682446,-98.890976,'2026-04-22 01:34:50'),(1272,16,2,19.682446,-98.890976,'2026-04-22 01:35:13'),(1273,16,2,19.682446,-98.890976,'2026-04-22 01:35:13'),(1274,16,2,19.682446,-98.890976,'2026-04-22 01:35:15'),(1275,16,2,19.682446,-98.890976,'2026-04-22 01:35:19'),(1276,16,2,19.682446,-98.890976,'2026-04-22 01:35:22'),(1277,16,2,19.682446,-98.890976,'2026-04-22 01:35:27'),(1278,16,2,19.682446,-98.890976,'2026-04-22 01:35:30'),(1279,16,2,19.682446,-98.890976,'2026-04-22 01:35:32'),(1280,16,2,19.682446,-98.890976,'2026-04-22 01:35:34'),(1281,16,2,19.682446,-98.890976,'2026-04-22 01:35:37'),(1282,16,2,19.682446,-98.890976,'2026-04-22 01:35:41'),(1283,16,2,19.682446,-98.890976,'2026-04-22 01:35:46'),(1284,16,2,19.682446,-98.890976,'2026-04-22 01:35:49'),(1285,16,2,19.682446,-98.890976,'2026-04-22 01:35:53'),(1286,16,2,19.682446,-98.890976,'2026-04-22 01:35:58'),(1287,16,2,19.682446,-98.890976,'2026-04-22 01:36:02'),(1288,16,2,19.682446,-98.890976,'2026-04-22 01:36:06'),(1289,16,2,19.682446,-98.890976,'2026-04-22 01:36:11'),(1290,16,2,19.682446,-98.890976,'2026-04-22 01:36:14'),(1291,16,2,19.682446,-98.890976,'2026-04-22 01:36:18'),(1292,16,2,19.682446,-98.890976,'2026-04-22 01:36:22'),(1293,16,2,19.682446,-98.890976,'2026-04-22 01:36:26'),(1294,16,2,19.682446,-98.890976,'2026-04-22 01:36:31'),(1295,16,2,19.682446,-98.890976,'2026-04-22 01:36:34'),(1296,16,2,19.682446,-98.890976,'2026-04-22 01:36:38'),(1297,16,2,19.682446,-98.890976,'2026-04-22 01:36:42'),(1298,16,2,19.682446,-98.890976,'2026-04-22 01:36:47'),(1299,16,2,19.682446,-98.890976,'2026-04-22 01:36:50'),(1300,16,2,19.682446,-98.890976,'2026-04-22 01:36:54'),(1301,16,2,19.682446,-98.890976,'2026-04-22 01:36:59'),(1302,16,2,19.682446,-98.890976,'2026-04-22 01:37:02'),(1303,16,2,19.682446,-98.890976,'2026-04-22 01:37:06'),(1304,16,2,19.682446,-98.890976,'2026-04-22 01:37:10'),(1305,16,2,19.682446,-98.890976,'2026-04-22 01:37:14'),(1306,16,2,19.682446,-98.890976,'2026-04-22 01:37:18'),(1307,16,2,19.682446,-98.890976,'2026-04-22 01:37:22'),(1308,16,2,19.682446,-98.890976,'2026-04-22 01:37:26'),(1309,16,2,19.682446,-98.890976,'2026-04-22 01:37:30'),(1310,16,2,19.682446,-98.890976,'2026-04-22 01:37:34'),(1311,16,2,19.682446,-98.890976,'2026-04-22 01:37:38'),(1312,16,2,19.682446,-98.890976,'2026-04-22 01:37:42'),(1313,16,2,19.682446,-98.890976,'2026-04-22 01:37:46'),(1314,16,2,19.682446,-98.890976,'2026-04-22 01:37:50'),(1315,16,2,19.682446,-98.890976,'2026-04-22 01:37:54'),(1316,16,2,19.682446,-98.890976,'2026-04-22 01:37:58'),(1317,16,2,19.682446,-98.890976,'2026-04-22 01:38:02'),(1318,16,2,19.682446,-98.890976,'2026-04-22 01:38:06'),(1319,16,2,19.682446,-98.890976,'2026-04-22 01:38:10'),(1320,16,2,19.682446,-98.890976,'2026-04-22 01:38:14'),(1321,16,2,19.682446,-98.890976,'2026-04-22 01:38:18'),(1322,16,2,19.682438,-98.891014,'2026-04-22 01:38:23'),(1323,16,2,19.682438,-98.891014,'2026-04-22 01:38:26'),(1324,16,2,19.682438,-98.891014,'2026-04-22 01:38:30'),(1325,16,2,19.682438,-98.891014,'2026-04-22 01:38:34'),(1326,16,2,19.682438,-98.891014,'2026-04-22 01:38:38'),(1327,16,2,19.682438,-98.891014,'2026-04-22 01:38:42'),(1328,16,2,19.682438,-98.891014,'2026-04-22 01:38:46'),(1329,16,2,19.682438,-98.891014,'2026-04-22 01:38:50'),(1330,16,2,19.682438,-98.891014,'2026-04-22 01:38:54'),(1331,16,2,19.682438,-98.891014,'2026-04-22 01:38:58'),(1332,16,2,19.682438,-98.891014,'2026-04-22 01:39:02'),(1333,16,2,19.682438,-98.891014,'2026-04-22 01:39:06'),(1334,16,2,19.682438,-98.891014,'2026-04-22 01:39:11'),(1335,16,2,19.682438,-98.891014,'2026-04-22 01:39:14'),(1336,16,2,19.682438,-98.891014,'2026-04-22 01:39:18'),(1337,16,2,19.682438,-98.891014,'2026-04-22 01:39:22'),(1338,16,2,19.682438,-98.891014,'2026-04-22 01:39:26'),(1339,16,2,19.682438,-98.891014,'2026-04-22 01:39:30'),(1340,16,2,19.682438,-98.891014,'2026-04-22 01:39:34'),(1341,16,2,19.682438,-98.891014,'2026-04-22 01:39:38'),(1342,16,2,19.682438,-98.891014,'2026-04-22 01:39:42'),(1343,16,2,19.682438,-98.891014,'2026-04-22 01:39:47'),(1344,16,2,19.682438,-98.891014,'2026-04-22 01:39:50'),(1345,16,2,19.682438,-98.891014,'2026-04-22 01:39:54'),(1346,16,2,19.682438,-98.891014,'2026-04-22 01:39:58'),(1347,16,2,19.682438,-98.891014,'2026-04-22 01:40:02'),(1348,16,2,19.682438,-98.891014,'2026-04-22 01:40:06'),(1349,16,2,19.682438,-98.891014,'2026-04-22 01:40:10'),(1350,16,2,19.682438,-98.891014,'2026-04-22 01:40:15'),(1351,16,2,19.682438,-98.891014,'2026-04-22 01:40:18'),(1352,16,2,19.682438,-98.891014,'2026-04-22 01:40:23'),(1353,16,2,19.682438,-98.891014,'2026-04-22 01:40:26'),(1354,16,2,19.682438,-98.891014,'2026-04-22 01:40:30'),(1355,16,2,19.682438,-98.891014,'2026-04-22 01:40:34'),(1356,16,2,19.682438,-98.891014,'2026-04-22 01:40:38'),(1357,16,2,19.682438,-98.891014,'2026-04-22 01:40:42'),(1358,16,2,19.682438,-98.891014,'2026-04-22 01:40:46'),(1359,16,2,19.682438,-98.891014,'2026-04-22 01:40:50'),(1360,16,2,19.682438,-98.891014,'2026-04-22 01:40:54'),(1361,16,2,19.682438,-98.891014,'2026-04-22 01:40:59'),(1362,16,2,19.682438,-98.891014,'2026-04-22 01:41:50'),(1363,16,2,19.682438,-98.891014,'2026-04-22 01:42:50'),(1364,16,2,19.682446,-98.890976,'2026-04-22 01:43:50'),(1365,16,2,19.682446,-98.890976,'2026-04-22 01:44:50'),(1366,16,2,19.682446,-98.890976,'2026-04-22 01:45:50'),(1367,16,2,19.682738,-98.890881,'2026-04-22 01:46:50'),(1368,16,2,19.682446,-98.890976,'2026-04-29 05:11:54');
/*!40000 ALTER TABLE `tracking` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tracking_shares`
--

DROP TABLE IF EXISTS `tracking_shares`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tracking_shares` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `pedido_id` int(11) NOT NULL,
  `created_by` int(11) NOT NULL,
  `token_hash` char(64) NOT NULL,
  `expires_at` datetime NOT NULL,
  `revoked_at` datetime DEFAULT NULL,
  `last_access_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `token_hash` (`token_hash`),
  KEY `idx_tracking_shares_pedido_active` (`pedido_id`,`revoked_at`,`expires_at`),
  KEY `created_by` (`created_by`),
  CONSTRAINT `tracking_shares_ibfk_1` FOREIGN KEY (`pedido_id`) REFERENCES `pedidos` (`id`),
  CONSTRAINT `tracking_shares_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `usuarios` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tracking_shares`
--

LOCK TABLES `tracking_shares` WRITE;
/*!40000 ALTER TABLE `tracking_shares` DISABLE KEYS */;
INSERT INTO `tracking_shares` VALUES (1,9,4,'9fd0e1e014d1220b9e2f7d5e63b4d4db82bc3841c4350de714bf94f3911f1693','2026-04-01 09:13:51','2026-03-31 19:14:11',NULL,'2026-03-31 19:13:51'),(2,9,4,'1605f1c9b15417cfc0774d139ee9d8e3b88011545993188630c6b0a7ec904e05','2026-04-01 09:14:15','2026-03-31 19:14:23',NULL,'2026-03-31 19:14:15'),(3,9,4,'693bd039a5c484bb7b35cd1c8b51c57ffcb525852200a6a700a99eca8a227fb1','2026-04-01 09:14:23','2026-03-31 19:15:02','2026-03-31 19:14:56','2026-03-31 19:14:23'),(4,9,4,'b1d504da6f2363e1d65a176cc69f2f2334f1dfad1b013d164cf49b1314a5b289','2026-04-01 09:24:15','2026-03-31 19:25:01','2026-03-31 19:24:59','2026-03-31 19:24:15'),(5,11,4,'9b8f103f250a909fb705be5fd70248dc635d7d71939432e78ba423e2f8f30bfe','2026-04-09 21:59:45','2026-04-09 10:00:30','2026-04-09 10:00:25','2026-04-09 09:59:45'),(6,11,4,'af64c2d22efe83904a647bfb636c348fb66db64507caae324170d591caf5ce75','2026-04-10 23:06:50',NULL,NULL,'2026-04-10 11:06:50'),(7,14,1,'ac35876e82f307c9bb4d5af8d23ece9e94712c3cef34c8f9d5a40ef83d0ebc53','2026-04-20 05:22:42',NULL,NULL,'2026-04-19 17:22:42'),(8,15,1,'caf0fd17996d65576b3eec19ea37ca692626c690d30917b483aba8abf6eac0ad','2026-04-20 05:46:45',NULL,'2026-04-19 17:46:45','2026-04-19 17:46:45'),(9,15,1,'f2c19f6db28a44bca86b683fc588ed8c6fae331dfaf3cf6feeb194094b582803','2026-04-20 23:54:09',NULL,'2026-04-20 11:54:09','2026-04-20 11:54:09'),(10,16,1,'44a92c11aafe384211d0565bf856380f83bac63cdd0f77f704c61a341fea2476','2026-04-20 23:54:12',NULL,'2026-04-20 11:54:13','2026-04-20 11:54:12'),(11,17,1,'58c0f910f2c23b234086343fa7c6963bd61792d638f615acc8f84ad878a5e90f','2026-04-21 02:33:48',NULL,'2026-04-20 15:12:31','2026-04-20 14:33:48');
/*!40000 ALTER TABLE `tracking_shares` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `transporte_lineas`
--

DROP TABLE IF EXISTS `transporte_lineas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `transporte_lineas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(120) NOT NULL,
  `capacidad_kg` int(11) DEFAULT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `nombre` (`nombre`)
) ENGINE=InnoDB AUTO_INCREMENT=631 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `transporte_lineas`
--

LOCK TABLES `transporte_lineas` WRITE;
/*!40000 ALTER TABLE `transporte_lineas` DISABLE KEYS */;
INSERT INTO `transporte_lineas` VALUES (1,'Linea Norte',12000,1,'2026-04-16 17:56:06'),(2,'Linea Centro',10000,1,'2026-04-16 17:56:06'),(3,'Linea Golfo',8000,1,'2026-04-16 17:56:06');
/*!40000 ALTER TABLE `transporte_lineas` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `usuario_temas`
--

DROP TABLE IF EXISTS `usuario_temas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `usuario_temas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `tema` varchar(50) NOT NULL DEFAULT 'dark',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_id` (`user_id`),
  CONSTRAINT `fk_ut_user` FOREIGN KEY (`user_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=64 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `usuario_temas`
--

LOCK TABLES `usuario_temas` WRITE;
/*!40000 ALTER TABLE `usuario_temas` DISABLE KEYS */;
INSERT INTO `usuario_temas` VALUES (1,1,'palette','2026-04-29 05:46:51','2026-04-29 07:20:18'),(24,4,'palette','2026-04-29 05:58:51','2026-04-29 05:58:51'),(25,6,'palette','2026-04-29 05:59:30','2026-04-29 05:59:30'),(26,2,'dark','2026-04-29 06:14:09','2026-04-29 07:08:29');
/*!40000 ALTER TABLE `usuario_temas` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `usuarios`
--

DROP TABLE IF EXISTS `usuarios`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `usuarios` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(120) NOT NULL,
  `email` varchar(180) NOT NULL,
  `password` varchar(255) NOT NULL,
  `rol` enum('administrador','inventario','distribucion','operador','cliente') NOT NULL,
  `domicilio` varchar(255) DEFAULT NULL,
  `edad` tinyint(3) unsigned DEFAULT NULL,
  `telefono` varchar(20) DEFAULT NULL,
  `lat` double DEFAULT NULL,
  `lng` double DEFAULT NULL,
  `zona_radio` double DEFAULT 50,
  `activo` tinyint(1) DEFAULT 1,
  `ultimo_acceso` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `unidad_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `usuarios`
--

LOCK TABLES `usuarios` WRITE;
/*!40000 ALTER TABLE `usuarios` DISABLE KEYS */;
INSERT INTO `usuarios` VALUES (1,'Admin Principal','admin@demo.com','$2y$10$8UL9LNio3aTeb8NHeajUo.Z6VIB1YhuEU6HWA2jK4mIRFsVqHBOf.','administrador','Av. Insurgentes Sur 123, CDMX',35,'5511223344',19.4326,-99.1332,9999,1,'2026-05-14 01:59:32','2026-03-23 16:44:38',NULL),(2,'Carlos López','operador@demo.com','$2y$10$SY3tQ1cUOwl4qbvXGSq5e.hwuSzaM5YMzRPbLkpHmCdlIIMIjAcfe','operador','Calle Sonora 45, Col. Roma, CDMX',28,'5571075067',19.682446,-98.890976,15,1,'2026-05-14 02:38:33','2026-03-23 16:44:38',NULL),(3,'Ana Martínez','operador2@demo.com','$2y$10$E/EUkp93cIXY4JGSsbTgAujmfeywHRwgjZaBvokH/6oE.1dPFlmxW','operador','Blvd. Manuel Ávila Camacho 32, GDL',31,'3312345678',19.7,-98.98,15,1,NULL,'2026-03-23 16:44:38',NULL),(4,'Juan Cliente','cliente@demo.com','$2y$10$x8b2dV/yIiBKQCugACLqx.weL7.4Fhd2TPMJnWze4/hMsn3pknWfa','cliente','Av. Álvaro Obregón 88, Col. Roma, CDMX',25,'5522334455',19.7,-98.98,0,1,'2026-05-14 01:52:33','2026-03-23 16:44:38',NULL),(5,'Artega Muñoz','arteaga@gmail.co','$2y$10$uvMcsrkBjAg50gm7CcuKMuRtbN3pkhI.HHV/xEGXASVNb6Vc6jrAG','cliente','Ojo de agua',25,'55 2343 1726',20.2690825,-97.5251407,50,1,'2026-03-23 17:40:36','2026-03-23 17:20:42',NULL),(6,'Coordinacion Inventario','inventario@demo.com','$2y$10$q5ioETEISog2RcmMFU1hi.V78ii8fyU8kmvgSFJCvTv8Wuj9towS2','inventario','Centro logistico NexusPanel',30,'5500000000',19.4326,-99.1332,9999,1,'2026-05-14 01:55:23','2026-04-17 18:07:38',NULL);
/*!40000 ALTER TABLE `usuarios` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-05-14  3:00:24
