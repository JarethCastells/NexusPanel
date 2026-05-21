-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Servidor: localhost:3306
-- Tiempo de generación: 27-04-2026 a las 16:39:28
-- Versión del servidor: 10.6.24-MariaDB-cll-lve-log
-- Versión de PHP: 8.4.20

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de datos: `teotekco_nexuspanel`
--

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `auditoria_eventos`
--

CREATE TABLE `auditoria_eventos` (
  `id` int(11) NOT NULL,
  `usuario_id` int(11) DEFAULT NULL,
  `rol` varchar(40) DEFAULT NULL,
  `modulo` varchar(60) NOT NULL,
  `accion` varchar(80) NOT NULL,
  `referencia_tipo` varchar(40) DEFAULT NULL,
  `referencia_id` int(11) DEFAULT NULL,
  `detalles` text DEFAULT NULL,
  `ip_origen` varchar(64) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `auditoria_eventos`
--

INSERT INTO `auditoria_eventos` (`id`, `usuario_id`, `rol`, `modulo`, `accion`, `referencia_tipo`, `referencia_id`, `detalles`, `ip_origen`, `created_at`) VALUES
(1, 1, 'administrador', 'pedidos', 'asignar_operador_manual', 'pedido', 13, 'Asignado a operador #2', '189.226.235.35', '2026-04-19 17:21:05'),
(2, 1, 'administrador', 'pedidos', 'cancelar', 'pedido', 13, 'Cancelado por coordinacion desde resumen de pedido', '189.226.235.35', '2026-04-19 17:21:13'),
(3, 1, 'administrador', 'pedidos', 'cambiar_estado', 'pedido', 13, 'Estado -> cancelado', '189.226.235.35', '2026-04-19 17:21:20'),
(4, 1, 'administrador', 'pedidos', 'cambiar_estado', 'pedido', 11, 'Estado -> en_camino', '189.226.235.35', '2026-04-19 17:21:25'),
(5, 1, 'administrador', 'pedidos', 'cambiar_estado', 'pedido', 14, 'Estado -> pendiente', '189.226.235.35', '2026-04-19 17:22:08'),
(6, 1, 'administrador', 'pedidos', 'asignar_operador_manual', 'pedido', 14, 'Asignado a operador #2', '189.226.235.35', '2026-04-19 17:23:47'),
(7, 1, 'administrador', 'pedidos', 'cambiar_estado', 'pedido', 15, 'Estado -> cancelado', '189.226.235.35', '2026-04-19 17:23:58'),
(8, 4, 'cliente', 'pedidos', 'crear_pedido', 'pedido', 16, 'Pedido creado con 2 productos', '189.226.235.35', '2026-04-20 11:49:10'),
(9, 1, 'administrador', 'pedidos', 'asignar_operador_manual', 'pedido', 16, 'Asignado a operador #2', '189.226.235.35', '2026-04-20 11:49:47'),
(10, 1, 'administrador', 'pedidos', 'cambiar_estado', 'pedido', 15, 'Estado -> entregado', '189.226.235.35', '2026-04-20 11:54:39'),
(11, 1, 'administrador', 'pedidos', 'cambiar_estado', 'pedido', 13, 'Estado -> entregado', '189.226.235.35', '2026-04-20 11:54:44'),
(12, 1, 'administrador', 'pedidos', 'cambiar_estado', 'pedido', 11, 'Estado -> entregado', '189.226.235.35', '2026-04-20 11:54:50'),
(13, 1, 'administrador', 'pedidos', 'cambiar_estado', 'pedido', 9, 'Estado -> entregado', '189.226.235.35', '2026-04-20 11:54:55'),
(14, 1, 'administrador', 'pedidos', 'cambiar_estado', 'pedido', 5, 'Estado -> entregado', '189.226.235.35', '2026-04-20 11:55:01'),
(15, 1, 'administrador', 'pedidos', 'cambiar_estado', 'pedido', 16, 'Estado -> en_camino', '189.226.235.35', '2026-04-20 11:55:08'),
(16, 4, 'cliente', 'pedidos', 'crear_pedido', 'pedido', 17, 'Pedido creado con 4 productos', '187.188.14.232', '2026-04-20 14:26:30'),
(17, 1, 'administrador', 'pedidos', 'asignar_operador_manual', 'pedido', 17, 'Asignado a operador #2', '187.188.14.232', '2026-04-20 14:45:55'),
(18, 4, 'cliente', 'pedidos', 'crear_pedido', 'pedido', 18, 'Pedido creado con 2 productos', '187.188.14.232', '2026-04-22 19:24:35'),
(19, 4, 'cliente', 'pedidos', 'crear_pedido', 'pedido', 19, 'Pedido creado con 1 productos', '189.226.235.35', '2026-04-23 12:58:43'),
(20, 4, 'cliente', 'pedidos', 'crear_pedido', 'pedido', 20, 'Pedido creado con 1 productos', '189.226.235.35', '2026-04-23 13:43:02'),
(21, 4, 'cliente', 'pedidos', 'crear_pedido', 'pedido', 21, 'Pedido creado con 1 productos', '189.226.235.35', '2026-04-23 14:10:27'),
(22, 6, 'inventario', 'pedidos', 'cambiar_estado', 'pedido', 20, 'Estado -> pendiente', '187.190.105.223', '2026-04-23 19:20:54'),
(23, 6, 'inventario', 'pedidos', 'asignar_operador_manual', 'pedido', 21, 'Asignado a operador #3', '187.190.105.223', '2026-04-25 11:38:45');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `cfdi_eventos`
--

CREATE TABLE `cfdi_eventos` (
  `id` int(11) NOT NULL,
  `pedido_id` int(11) NOT NULL,
  `estado` enum('pendiente','timbrado','error') NOT NULL DEFAULT 'pendiente',
  `mensaje` varchar(255) DEFAULT NULL,
  `payload_json` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `chat`
--

CREATE TABLE `chat` (
  `id` int(11) NOT NULL,
  `pedido_id` int(11) NOT NULL,
  `usuario_id` int(11) NOT NULL,
  `mensaje` text NOT NULL,
  `ts` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `chat`
--

INSERT INTO `chat` (`id`, `pedido_id`, `usuario_id`, `mensaje`, `ts`) VALUES
(1, 1, 2, '✅ He aceptado tu pedido. En breve estaré en camino.', '2026-03-23 16:55:35'),
(2, 1, 2, '🚛 ¡Ya voy en camino! Puedes ver mi ubicación en tiempo real en el mapa.', '2026-03-23 16:55:43'),
(3, 1, 2, 'Hola', '2026-03-23 16:55:46'),
(4, 1, 4, 'Como estas', '2026-03-23 16:56:17'),
(5, 2, 2, '✅ He aceptado tu pedido. En breve estaré en camino.', '2026-03-23 17:08:29'),
(6, 1, 2, 'Ya voy de camino', '2026-03-23 17:12:41'),
(7, 2, 2, '🚛 ¡Ya voy en camino! Puedes ver mi ubicación en tiempo real en el mapa.', '2026-03-23 17:12:46'),
(8, 2, 2, 'Ya voy para alla', '2026-03-23 17:12:52'),
(9, 3, 2, '✅ He aceptado tu pedido. En breve estaré en camino.', '2026-03-23 17:15:42'),
(10, 4, 2, '✅ He aceptado tu pedido. En breve estaré en camino.', '2026-03-23 17:15:48'),
(11, 3, 2, '🚛 ¡Ya voy en camino! Puedes ver mi ubicación en tiempo real en el mapa.', '2026-03-23 17:16:09'),
(12, 3, 2, '📦 ¡Pedido entregado! Gracias por tu compra.', '2026-03-23 17:16:12'),
(13, 1, 2, '📦 ¡Pedido entregado! Gracias por tu compra.', '2026-03-23 17:16:22'),
(14, 4, 2, '📦 ¡Pedido entregado! Gracias por tu compra.', '2026-03-23 17:19:32'),
(15, 2, 2, '📦 ¡Pedido entregado! Gracias por tu compra.', '2026-03-23 17:19:37'),
(16, 7, 2, '✅ He aceptado tu pedido. En breve estaré en camino.', '2026-03-23 17:35:10'),
(17, 6, 2, '✅ He aceptado tu pedido. En breve estaré en camino.', '2026-03-23 17:35:19'),
(18, 6, 2, '🚛 ¡Ya voy en camino! Puedes ver mi ubicación en tiempo real en el mapa.', '2026-03-23 17:35:24'),
(19, 7, 2, '🚛 ¡Ya voy en camino! Puedes ver mi ubicación en tiempo real en el mapa.', '2026-03-23 17:35:27'),
(20, 6, 2, 'hola ya voy de camino', '2026-03-23 17:35:40'),
(21, 7, 2, 'tardare en llegar', '2026-03-23 17:35:51'),
(22, 7, 5, 'ok', '2026-03-23 17:36:26'),
(23, 6, 4, 'okay', '2026-03-23 17:36:58'),
(24, 6, 2, 'ya llegue', '2026-03-23 17:37:27'),
(25, 6, 2, '📦 ¡Pedido entregado! Gracias por tu compra.', '2026-03-23 17:37:32'),
(26, 7, 5, 'donde estas', '2026-03-23 17:38:26'),
(27, 7, 2, 'ya llegue', '2026-03-23 17:40:13'),
(28, 7, 2, '📦 ¡Pedido entregado! Gracias por tu compra.', '2026-03-23 17:40:18'),
(29, 5, 2, '✅ He aceptado tu pedido. En breve estaré en camino.', '2026-03-23 20:37:52'),
(30, 5, 2, 'Hola', '2026-03-23 20:37:57'),
(31, 8, 2, '✅ He aceptado tu pedido. En breve estaré en camino.', '2026-03-23 20:43:45'),
(32, 8, 2, 'Perfecto', '2026-03-23 20:43:58'),
(33, 8, 4, 'Okay', '2026-03-23 20:45:01'),
(34, 9, 2, '✅ He aceptado tu pedido. En breve estaré en camino.', '2026-03-23 22:10:13'),
(35, 8, 2, 'ok', '2026-03-23 22:46:02'),
(36, 5, 2, 'hola', '2026-03-24 12:36:58'),
(37, 8, 2, '🚛 ¡Ya voy en camino! Puedes ver mi ubicación en tiempo real en el mapa.', '2026-03-24 13:17:28'),
(38, 8, 2, '📦 ¡Pedido entregado! Gracias por tu compra.', '2026-03-24 13:17:35'),
(39, 9, 4, 'ok', '2026-03-24 14:03:50'),
(40, 9, 2, 'ok', '2026-03-24 14:04:30'),
(41, 9, 2, '🚛 ¡Ya voy en camino! Puedes ver mi ubicación en tiempo real en el mapa.', '2026-03-25 12:44:07'),
(42, 9, 2, 'hola', '2026-03-25 13:06:27'),
(43, 10, 2, 'He aceptado tu pedido. En breve estare en camino.', '2026-04-09 09:56:59'),
(44, 10, 2, 'Pedido entregado. Gracias por tu compra.', '2026-04-09 09:57:14'),
(45, 11, 2, 'He aceptado tu pedido. En breve estare en camino.', '2026-04-09 09:58:05'),
(46, 11, 2, 'Ya voy en camino. Puedes ver mi ubicacion en tiempo real en el mapa.', '2026-04-09 09:58:08'),
(47, 11, 2, 'Hola ya vo de camino', '2026-04-09 09:58:18'),
(48, 12, 2, 'He aceptado tu pedido. En breve estare en camino.', '2026-04-09 18:28:11'),
(49, 12, 2, 'Ya voy en camino. Puedes ver mi ubicacion en tiempo real en el mapa.', '2026-04-09 18:28:24'),
(50, 12, 2, 'Pedido entregado. Gracias por tu compra.', '2026-04-09 18:30:19'),
(51, 5, 2, 'Ya voy en camino. Puedes ver mi ubicacion en tiempo real en el mapa.', '2026-04-10 00:36:22'),
(52, 5, 2, 'Hola', '2026-04-10 00:36:41'),
(53, 14, 2, 'He aceptado tu pedido. En breve estare en camino.', '2026-04-10 11:10:51'),
(54, 14, 2, 'Ya voy en camino. Puedes ver mi ubicacion en tiempo real en el mapa.', '2026-04-10 11:11:01'),
(55, 14, 2, 'Hola, ya va tu pedido', '2026-04-10 11:11:29'),
(56, 14, 4, 'muy bien', '2026-04-10 11:12:29'),
(57, 14, 2, 'Pedido entregado. Gracias por tu compra.', '2026-04-10 11:13:08'),
(58, 15, 2, 'He aceptado tu pedido. En breve estare en camino.', '2026-04-16 10:13:40'),
(59, 15, 2, 'Ya voy en camino. Puedes ver mi ubicacion en tiempo real en el mapa.', '2026-04-16 10:13:51'),
(60, 13, 2, 'Pedido asignado por coordinacion. Confirmo recepcion.', '2026-04-19 17:21:05'),
(61, 14, 2, 'Pedido asignado por coordinacion. Confirmo recepcion.', '2026-04-19 17:23:47'),
(62, 16, 2, 'Pedido asignado por coordinacion. Confirmo recepcion.', '2026-04-20 11:49:47'),
(63, 17, 2, 'Pedido asignado por coordinacion. Confirmo recepcion.', '2026-04-20 14:45:55'),
(64, 16, 2, 'hola', '2026-04-21 14:48:47'),
(65, 18, 2, 'He aceptado tu pedido. En breve estare en camino.', '2026-04-22 19:24:35'),
(66, 16, 2, 'hola', '2026-04-24 16:38:49'),
(67, 21, 3, 'Pedido asignado por coordinacion. Confirmo recepcion.', '2026-04-25 11:38:45');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `chat_ayuda_operador`
--

CREATE TABLE `chat_ayuda_operador` (
  `id` int(11) NOT NULL,
  `operador_id` int(11) NOT NULL,
  `admin_id` int(11) NOT NULL,
  `remitente_id` int(11) NOT NULL,
  `mensaje` varchar(1000) NOT NULL,
  `ts` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `cliente_producto_historial`
--

CREATE TABLE `cliente_producto_historial` (
  `id` int(11) NOT NULL,
  `cliente_id` int(11) NOT NULL,
  `producto_id` int(11) NOT NULL,
  `cantidad_total` int(11) NOT NULL DEFAULT 0,
  `veces_pedido` int(11) NOT NULL DEFAULT 0,
  `ultima_fecha` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `cliente_producto_historial`
--

INSERT INTO `cliente_producto_historial` (`id`, `cliente_id`, `producto_id`, `cantidad_total`, `veces_pedido`, `ultima_fecha`, `created_at`, `updated_at`) VALUES
(176, 4, 1, 16, 7, '2026-04-20 20:26:30', '2026-04-23 14:10:27', '2026-04-23 14:10:27'),
(177, 4, 2, 20, 7, '2026-04-23 20:10:27', '2026-04-23 14:10:27', '2026-04-23 14:10:27'),
(178, 4, 3, 10, 7, '2026-04-23 18:58:43', '2026-04-23 14:10:27', '2026-04-23 14:10:27'),
(179, 4, 4, 8, 4, '2026-04-20 17:49:10', '2026-04-23 14:10:27', '2026-04-23 14:10:27'),
(180, 4, 5, 8, 6, '2026-04-23 19:43:02', '2026-04-23 14:10:27', '2026-04-23 14:10:27'),
(181, 4, 6, 3, 3, '2026-04-20 20:26:30', '2026-04-23 14:10:27', '2026-04-23 14:10:27'),
(182, 4, 8, 20, 1, '2026-04-16 10:13:04', '2026-04-23 14:10:27', '2026-04-23 14:10:27'),
(183, 4, 9, 2, 2, '2026-03-23 17:22:21', '2026-04-23 14:10:27', '2026-04-23 14:10:27'),
(184, 4, 10, 2, 2, '2026-03-23 17:22:21', '2026-04-23 14:10:27', '2026-04-23 14:10:27'),
(185, 4, 11, 1, 1, '2026-03-23 17:14:14', '2026-04-23 14:10:27', '2026-04-23 14:10:27'),
(186, 4, 12, 2, 2, '2026-03-23 17:14:14', '2026-04-23 14:10:27', '2026-04-23 14:10:27'),
(187, 4, 15, 1, 1, '2026-03-23 17:14:14', '2026-04-23 14:10:27', '2026-04-23 14:10:27'),
(188, 4, 16, 1, 1, '2026-04-23 01:24:35', '2026-04-23 14:10:27', '2026-04-23 14:10:27'),
(189, 4, 17, 1, 1, '2026-03-23 17:14:14', '2026-04-23 14:10:27', '2026-04-23 14:10:27'),
(190, 4, 18, 1, 1, '2026-03-23 17:13:46', '2026-04-23 14:10:27', '2026-04-23 14:10:27'),
(191, 4, 20, 1, 1, '2026-03-23 17:13:46', '2026-04-23 14:10:27', '2026-04-23 14:10:27'),
(192, 4, 22, 1, 1, '2026-03-23 17:08:16', '2026-04-23 14:10:27', '2026-04-23 14:10:27'),
(193, 4, 25, 1, 1, '2026-03-23 17:13:46', '2026-04-23 14:10:27', '2026-04-23 14:10:27'),
(194, 4, 26, 1, 1, '2026-03-23 17:13:46', '2026-04-23 14:10:27', '2026-04-23 14:10:27'),
(195, 4, 27, 1, 1, '2026-03-23 17:08:16', '2026-04-23 14:10:27', '2026-04-23 14:10:27'),
(196, 4, 29, 4, 1, '2026-04-10 11:06:12', '2026-04-23 14:10:27', '2026-04-23 14:10:27');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `especies`
--

CREATE TABLE `especies` (
  `id` int(11) NOT NULL,
  `nombre` varchar(80) NOT NULL,
  `slug` varchar(80) NOT NULL,
  `descripcion` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `especies`
--

INSERT INTO `especies` (`id`, `nombre`, `slug`, `descripcion`, `created_at`) VALUES
(1, 'Pollos', 'pollos', NULL, '2026-04-13 15:55:24'),
(2, 'Vacas', 'vacas', NULL, '2026-04-13 15:55:24'),
(3, 'Cerdos', 'cerdos', NULL, '2026-04-13 15:55:24'),
(4, 'Acuacultura', 'acuacultura', NULL, '2026-04-13 15:55:24'),
(2471, 'Alimento', 'alimento', 'Categoria base de insumos y nutricion', '2026-04-19 16:15:29'),
(2472, 'Comida', 'comida', 'Categoria base de alimento terminado', '2026-04-19 16:15:29'),
(2629, 'Pollo', 'pollo', 'Categoria base', '2026-04-19 17:10:51'),
(2630, 'Vaca', 'vaca', 'Categoria base', '2026-04-19 17:10:51'),
(2631, 'Cerdo', 'cerdo', 'Categoria base', '2026-04-19 17:10:51');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `inventario_movimientos`
--

CREATE TABLE `inventario_movimientos` (
  `id` int(11) NOT NULL,
  `producto_id` int(11) NOT NULL,
  `tipo` enum('entrada','salida','ajuste','reserva','liberacion') NOT NULL,
  `cantidad` int(11) NOT NULL,
  `stock_anterior` int(11) NOT NULL,
  `stock_nuevo` int(11) NOT NULL,
  `referencia_tipo` varchar(40) DEFAULT NULL,
  `referencia_id` int(11) DEFAULT NULL,
  `nota` varchar(255) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `inventario_movimientos`
--

INSERT INTO `inventario_movimientos` (`id`, `producto_id`, `tipo`, `cantidad`, `stock_anterior`, `stock_nuevo`, `referencia_tipo`, `referencia_id`, `nota`, `created_by`, `created_at`) VALUES
(1, 3, 'salida', 1, 100, 99, 'pedido', 16, 'Descuento por creacion de pedido', 4, '2026-04-20 11:49:10'),
(2, 4, 'salida', 3, 100, 97, 'pedido', 16, 'Descuento por creacion de pedido', 4, '2026-04-20 11:49:10'),
(3, 1, 'salida', 6, 100, 94, 'pedido', 17, 'Descuento por creacion de pedido', 4, '2026-04-20 14:26:30'),
(4, 2, 'salida', 2, 100, 98, 'pedido', 17, 'Descuento por creacion de pedido', 4, '2026-04-20 14:26:30'),
(5, 3, 'salida', 4, 99, 95, 'pedido', 17, 'Descuento por creacion de pedido', 4, '2026-04-20 14:26:30'),
(6, 6, 'salida', 1, 100, 99, 'pedido', 17, 'Descuento por creacion de pedido', 4, '2026-04-20 14:26:30'),
(7, 2, 'salida', 7, 98, 91, 'pedido', 18, 'Descuento por creacion de pedido', 4, '2026-04-22 19:24:35'),
(8, 16, 'salida', 1, 100, 99, 'pedido', 18, 'Descuento por creacion de pedido', 4, '2026-04-22 19:24:35'),
(9, 3, 'salida', 1, 95, 94, 'pedido', 19, 'Descuento por creacion de pedido', 4, '2026-04-23 12:58:43'),
(10, 5, 'salida', 3, 99, 96, 'pedido', 20, 'Descuento por creacion de pedido', 4, '2026-04-23 13:43:02'),
(11, 2, 'salida', 1, 91, 90, 'pedido', 21, 'Descuento por creacion de pedido', 4, '2026-04-23 14:10:27');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `login_throttles`
--

CREATE TABLE `login_throttles` (
  `bucket` varchar(190) NOT NULL,
  `hits` int(11) NOT NULL DEFAULT 0,
  `window_start` datetime NOT NULL,
  `blocked_until` datetime DEFAULT NULL,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `login_throttles`
--

INSERT INTO `login_throttles` (`bucket`, `hits`, `window_start`, `blocked_until`, `updated_at`) VALUES
('login:email:24d250b246914a29030b3a4540f31b7da0ac717284feb1628efb87d5593dd46f', 2, '2026-04-20 20:17:07', NULL, '2026-04-20 14:17:13');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `notificaciones_eventos`
--

CREATE TABLE `notificaciones_eventos` (
  `id` int(11) NOT NULL,
  `pedido_id` int(11) NOT NULL,
  `cliente_id` int(11) NOT NULL,
  `canal` enum('interno','email','whatsapp','sms','llamada') NOT NULL,
  `evento` varchar(60) NOT NULL,
  `mensaje` text NOT NULL,
  `estado` enum('pendiente','enviado','error') NOT NULL DEFAULT 'pendiente',
  `metadata_json` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `sent_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `notificaciones_eventos`
--

INSERT INTO `notificaciones_eventos` (`id`, `pedido_id`, `cliente_id`, `canal`, `evento`, `mensaje`, `estado`, `metadata_json`, `created_at`, `sent_at`) VALUES
(1, 13, 4, 'interno', 'pedido_asignado_manual', 'Tu pedido fue asignado a operador y esta en proceso.', 'pendiente', NULL, '2026-04-19 17:21:05', NULL),
(2, 13, 4, 'whatsapp', 'pedido_asignado_manual', 'Tu pedido fue asignado a operador y esta en proceso.', 'pendiente', NULL, '2026-04-19 17:21:05', NULL),
(3, 13, 4, 'sms', 'pedido_asignado_manual', 'Tu pedido fue asignado a operador y esta en proceso.', 'pendiente', NULL, '2026-04-19 17:21:05', NULL),
(4, 13, 4, 'email', 'pedido_asignado_manual', 'Tu pedido fue asignado a operador y esta en proceso.', 'pendiente', NULL, '2026-04-19 17:21:05', NULL),
(5, 13, 4, 'interno', 'pedido_cancelado', 'Tu pedido fue cancelado. Contacta a soporte para mas informacion.', 'pendiente', NULL, '2026-04-19 17:21:13', NULL),
(6, 13, 4, 'whatsapp', 'pedido_cancelado', 'Tu pedido fue cancelado. Contacta a soporte para mas informacion.', 'pendiente', NULL, '2026-04-19 17:21:13', NULL),
(7, 13, 4, 'sms', 'pedido_cancelado', 'Tu pedido fue cancelado. Contacta a soporte para mas informacion.', 'pendiente', NULL, '2026-04-19 17:21:13', NULL),
(8, 13, 4, 'email', 'pedido_cancelado', 'Tu pedido fue cancelado. Contacta a soporte para mas informacion.', 'pendiente', NULL, '2026-04-19 17:21:13', NULL),
(9, 14, 4, 'interno', 'pedido_asignado_manual', 'Tu pedido fue asignado a operador y esta en proceso.', 'pendiente', NULL, '2026-04-19 17:23:47', NULL),
(10, 14, 4, 'whatsapp', 'pedido_asignado_manual', 'Tu pedido fue asignado a operador y esta en proceso.', 'pendiente', NULL, '2026-04-19 17:23:47', NULL),
(11, 14, 4, 'sms', 'pedido_asignado_manual', 'Tu pedido fue asignado a operador y esta en proceso.', 'pendiente', NULL, '2026-04-19 17:23:47', NULL),
(12, 14, 4, 'email', 'pedido_asignado_manual', 'Tu pedido fue asignado a operador y esta en proceso.', 'pendiente', NULL, '2026-04-19 17:23:47', NULL),
(13, 16, 4, 'interno', 'pedido_creado', 'Tu pedido fue creado y esta en revision.', 'pendiente', '{\"tipo_pedido\":\"formal\",\"fecha_requerida\":null,\"total\":7350}', '2026-04-20 11:49:10', NULL),
(14, 16, 4, 'whatsapp', 'pedido_creado', 'Tu pedido fue creado y esta en revision.', 'pendiente', '{\"tipo_pedido\":\"formal\",\"fecha_requerida\":null,\"total\":7350}', '2026-04-20 11:49:10', NULL),
(15, 16, 4, 'sms', 'pedido_creado', 'Tu pedido fue creado y esta en revision.', 'pendiente', '{\"tipo_pedido\":\"formal\",\"fecha_requerida\":null,\"total\":7350}', '2026-04-20 11:49:10', NULL),
(16, 16, 4, 'email', 'pedido_creado', 'Tu pedido fue creado y esta en revision.', 'pendiente', '{\"tipo_pedido\":\"formal\",\"fecha_requerida\":null,\"total\":7350}', '2026-04-20 11:49:10', NULL),
(17, 16, 4, 'interno', 'pedido_asignado_manual', 'Tu pedido fue asignado a operador y esta en proceso.', 'pendiente', NULL, '2026-04-20 11:49:47', NULL),
(18, 16, 4, 'whatsapp', 'pedido_asignado_manual', 'Tu pedido fue asignado a operador y esta en proceso.', 'pendiente', NULL, '2026-04-20 11:49:47', NULL),
(19, 16, 4, 'sms', 'pedido_asignado_manual', 'Tu pedido fue asignado a operador y esta en proceso.', 'pendiente', NULL, '2026-04-20 11:49:47', NULL),
(20, 16, 4, 'email', 'pedido_asignado_manual', 'Tu pedido fue asignado a operador y esta en proceso.', 'pendiente', NULL, '2026-04-20 11:49:47', NULL),
(21, 17, 4, 'interno', 'pedido_creado', 'Tu pedido fue creado y esta en revision.', 'pendiente', '{\"tipo_pedido\":\"formal\",\"fecha_requerida\":null,\"total\":20880}', '2026-04-20 14:26:30', NULL),
(22, 17, 4, 'whatsapp', 'pedido_creado', 'Tu pedido fue creado y esta en revision.', 'pendiente', '{\"tipo_pedido\":\"formal\",\"fecha_requerida\":null,\"total\":20880}', '2026-04-20 14:26:30', NULL),
(23, 17, 4, 'sms', 'pedido_creado', 'Tu pedido fue creado y esta en revision.', 'pendiente', '{\"tipo_pedido\":\"formal\",\"fecha_requerida\":null,\"total\":20880}', '2026-04-20 14:26:30', NULL),
(24, 17, 4, 'email', 'pedido_creado', 'Tu pedido fue creado y esta en revision.', 'pendiente', '{\"tipo_pedido\":\"formal\",\"fecha_requerida\":null,\"total\":20880}', '2026-04-20 14:26:30', NULL),
(25, 17, 4, 'interno', 'pedido_asignado_manual', 'Tu pedido fue asignado a operador y esta en proceso.', 'pendiente', NULL, '2026-04-20 14:45:55', NULL),
(26, 17, 4, 'whatsapp', 'pedido_asignado_manual', 'Tu pedido fue asignado a operador y esta en proceso.', 'pendiente', NULL, '2026-04-20 14:45:55', NULL),
(27, 17, 4, 'sms', 'pedido_asignado_manual', 'Tu pedido fue asignado a operador y esta en proceso.', 'pendiente', NULL, '2026-04-20 14:45:55', NULL),
(28, 17, 4, 'email', 'pedido_asignado_manual', 'Tu pedido fue asignado a operador y esta en proceso.', 'pendiente', NULL, '2026-04-20 14:45:55', NULL),
(29, 18, 4, 'interno', 'pedido_creado', 'Tu pedido fue creado y esta en revision.', 'pendiente', '{\"tipo_pedido\":\"formal\",\"fecha_requerida\":null,\"total\":7580}', '2026-04-22 19:24:35', NULL),
(30, 18, 4, 'whatsapp', 'pedido_creado', 'Tu pedido fue creado y esta en revision.', 'pendiente', '{\"tipo_pedido\":\"formal\",\"fecha_requerida\":null,\"total\":7580}', '2026-04-22 19:24:35', NULL),
(31, 18, 4, 'sms', 'pedido_creado', 'Tu pedido fue creado y esta en revision.', 'pendiente', '{\"tipo_pedido\":\"formal\",\"fecha_requerida\":null,\"total\":7580}', '2026-04-22 19:24:35', NULL),
(32, 18, 4, 'email', 'pedido_creado', 'Tu pedido fue creado y esta en revision.', 'pendiente', '{\"tipo_pedido\":\"formal\",\"fecha_requerida\":null,\"total\":7580}', '2026-04-22 19:24:35', NULL),
(33, 19, 4, 'interno', 'pedido_creado', 'Tu pedido fue creado y esta en revision.', 'pendiente', '{\"tipo_pedido\":\"formal\",\"fecha_requerida\":null,\"total\":2100}', '2026-04-23 12:58:43', NULL),
(34, 19, 4, 'whatsapp', 'pedido_creado', 'Tu pedido fue creado y esta en revision.', 'pendiente', '{\"tipo_pedido\":\"formal\",\"fecha_requerida\":null,\"total\":2100}', '2026-04-23 12:58:43', NULL),
(35, 19, 4, 'sms', 'pedido_creado', 'Tu pedido fue creado y esta en revision.', 'pendiente', '{\"tipo_pedido\":\"formal\",\"fecha_requerida\":null,\"total\":2100}', '2026-04-23 12:58:43', NULL),
(36, 19, 4, 'email', 'pedido_creado', 'Tu pedido fue creado y esta en revision.', 'pendiente', '{\"tipo_pedido\":\"formal\",\"fecha_requerida\":null,\"total\":2100}', '2026-04-23 12:58:43', NULL),
(37, 19, 4, 'whatsapp', 'pedido_proceso_asignacion', 'Muchas gracias, tu pedido ya está en proceso de asignar un operador para su entrega.', 'pendiente', '{\"origen\":\"checkout_cliente\"}', '2026-04-23 12:58:43', NULL),
(38, 20, 4, 'interno', 'pedido_creado', 'Tu pedido fue creado y esta en revision.', 'pendiente', '{\"tipo_pedido\":\"formal\",\"fecha_requerida\":null,\"total\":5940}', '2026-04-23 13:43:02', NULL),
(39, 20, 4, 'whatsapp', 'pedido_creado', 'Tu pedido fue creado y esta en revision.', 'pendiente', '{\"tipo_pedido\":\"formal\",\"fecha_requerida\":null,\"total\":5940}', '2026-04-23 13:43:02', NULL),
(40, 20, 4, 'sms', 'pedido_creado', 'Tu pedido fue creado y esta en revision.', 'pendiente', '{\"tipo_pedido\":\"formal\",\"fecha_requerida\":null,\"total\":5940}', '2026-04-23 13:43:02', NULL),
(41, 20, 4, 'email', 'pedido_creado', 'Tu pedido fue creado y esta en revision.', 'pendiente', '{\"tipo_pedido\":\"formal\",\"fecha_requerida\":null,\"total\":5940}', '2026-04-23 13:43:02', NULL),
(42, 20, 4, 'whatsapp', 'pedido_proceso_asignacion', 'Muchas gracias, tu pedido ya está en proceso de asignar un operador para su entrega.', 'pendiente', '{\"origen\":\"checkout_cliente\"}', '2026-04-23 13:43:02', NULL),
(43, 21, 4, 'interno', 'pedido_creado', 'Tu pedido fue creado y esta en revision.', 'pendiente', '{\"tipo_pedido\":\"formal\",\"fecha_requerida\":null,\"total\":890}', '2026-04-23 14:10:27', NULL),
(44, 21, 4, 'whatsapp', 'pedido_creado', 'Tu pedido fue creado y esta en revision.', 'pendiente', '{\"tipo_pedido\":\"formal\",\"fecha_requerida\":null,\"total\":890}', '2026-04-23 14:10:27', NULL),
(45, 21, 4, 'sms', 'pedido_creado', 'Tu pedido fue creado y esta en revision.', 'pendiente', '{\"tipo_pedido\":\"formal\",\"fecha_requerida\":null,\"total\":890}', '2026-04-23 14:10:27', NULL),
(46, 21, 4, 'email', 'pedido_creado', 'Tu pedido fue creado y esta en revision.', 'pendiente', '{\"tipo_pedido\":\"formal\",\"fecha_requerida\":null,\"total\":890}', '2026-04-23 14:10:27', NULL),
(47, 21, 4, 'whatsapp', 'pedido_proceso_asignacion', 'Muchas gracias, tu pedido ya está en proceso de asignar un operador para su entrega.', 'pendiente', '{\"origen\":\"checkout_cliente\"}', '2026-04-23 14:10:27', NULL),
(48, 21, 4, 'interno', 'pedido_asignado_manual', 'Tu pedido fue asignado a operador y esta en proceso.', 'pendiente', NULL, '2026-04-25 11:38:45', NULL),
(49, 21, 4, 'whatsapp', 'pedido_asignado_manual', 'Tu pedido fue asignado a operador y esta en proceso.', 'pendiente', NULL, '2026-04-25 11:38:45', NULL),
(50, 21, 4, 'sms', 'pedido_asignado_manual', 'Tu pedido fue asignado a operador y esta en proceso.', 'pendiente', NULL, '2026-04-25 11:38:45', NULL),
(51, 21, 4, 'email', 'pedido_asignado_manual', 'Tu pedido fue asignado a operador y esta en proceso.', 'pendiente', NULL, '2026-04-25 11:38:45', NULL);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `operador_reabastos`
--

CREATE TABLE `operador_reabastos` (
  `id` int(11) NOT NULL,
  `operador_id` int(11) NOT NULL,
  `entregas_hasta` int(11) NOT NULL DEFAULT 0,
  `nota` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `pedidos`
--

CREATE TABLE `pedidos` (
  `id` int(11) NOT NULL,
  `cliente_id` int(11) NOT NULL,
  `operador_id` int(11) DEFAULT NULL,
  `estado` enum('pendiente','aceptado','en_camino','entregado','cancelado') DEFAULT 'pendiente',
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
  `persona_recibe` varchar(120) DEFAULT NULL,
  `estado_paquete` varchar(80) DEFAULT NULL,
  `comentario_entrega` text DEFAULT NULL,
  `folio_hex` varchar(32) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `pedidos`
--

INSERT INTO `pedidos` (`id`, `cliente_id`, `operador_id`, `estado`, `tipo_pedido`, `prioridad`, `total`, `lat_entrega`, `lng_entrega`, `domicilio_entrega`, `fecha_requerida`, `fecha_programada`, `transporte_linea`, `pl_documento`, `area_flujo`, `cfdi_status`, `cfdi_uuid`, `cfdi_pdf_url`, `cfdi_xml_url`, `notas`, `created_at`, `updated_at`, `persona_recibe`, `estado_paquete`, `comentario_entrega`, `folio_hex`) VALUES
(1, 4, 2, 'entregado', 'formal', 'media', 5050.00, 19.7, -98.98, 'Av. Álvaro Obregón 88, Col. Roma, CDMX', NULL, NULL, NULL, NULL, 'ventas', 'pendiente', NULL, NULL, NULL, NULL, '2026-03-23 16:54:45', '2026-04-24 23:42:49', NULL, NULL, NULL, '1'),
(2, 4, 2, 'entregado', 'formal', 'media', 10320.00, 19.7, -98.98, 'Av. Álvaro Obregón 88, Col. Roma, CDMX', NULL, NULL, NULL, NULL, 'ventas', 'pendiente', NULL, NULL, NULL, NULL, '2026-03-23 17:08:16', '2026-04-24 23:42:49', NULL, NULL, NULL, '2'),
(3, 4, 2, 'entregado', 'formal', 'media', 14360.00, 19.7, -98.98, 'Av. Álvaro Obregón 88, Col. Roma, CDMX', NULL, NULL, NULL, NULL, 'ventas', 'pendiente', NULL, NULL, NULL, NULL, '2026-03-23 17:13:46', '2026-04-24 23:42:49', NULL, NULL, NULL, '3'),
(4, 4, 2, 'entregado', 'formal', 'media', 16030.00, 19.7, -98.98, 'Av. Álvaro Obregón 88, Col. Roma, CDMX', NULL, NULL, NULL, NULL, 'ventas', 'pendiente', NULL, NULL, NULL, NULL, '2026-03-23 17:14:14', '2026-04-24 23:42:49', NULL, NULL, NULL, '4'),
(5, 5, 2, 'entregado', 'formal', 'media', 4410.00, 20.2690825, -97.5251407, 'Ojo de agua', NULL, NULL, NULL, NULL, 'ventas', 'pendiente', NULL, NULL, NULL, NULL, '2026-03-23 17:21:07', '2026-04-24 23:42:49', NULL, NULL, NULL, '5'),
(6, 4, 2, 'entregado', 'formal', 'media', 5700.00, 19.7, -98.98, 'Av. Álvaro Obregón 88, Col. Roma, CDMX', NULL, NULL, NULL, NULL, 'ventas', 'pendiente', NULL, NULL, NULL, NULL, '2026-03-23 17:22:21', '2026-04-24 23:42:49', NULL, NULL, NULL, '6'),
(7, 5, 2, 'entregado', 'formal', 'media', 1250.00, 20.2690825, -97.5251407, 'Zocalo', NULL, NULL, NULL, NULL, 'ventas', 'pendiente', NULL, NULL, NULL, NULL, '2026-03-23 17:30:33', '2026-04-24 23:42:49', NULL, NULL, NULL, '7'),
(8, 4, 2, 'entregado', 'formal', 'media', 3730.00, 19.7, -98.98, 'Av. Álvaro Obregón 88, Col. Roma, CDMX', NULL, NULL, NULL, NULL, 'ventas', 'pendiente', NULL, NULL, NULL, NULL, '2026-03-23 20:43:36', '2026-04-24 23:42:49', NULL, NULL, NULL, '8'),
(9, 4, 2, 'entregado', 'formal', 'media', 7280.00, 19.7, -98.98, 'Av. Álvaro Obregón 88, Col. Roma, CDMX', NULL, NULL, NULL, NULL, 'ventas', 'pendiente', NULL, NULL, NULL, NULL, '2026-03-23 22:09:55', '2026-04-24 23:42:49', NULL, NULL, NULL, '9'),
(10, 4, 2, 'entregado', 'formal', 'media', 4240.00, 19.7, -98.98, 'Av. Álvaro Obregón 88, Col. Roma, CDMX', NULL, NULL, NULL, NULL, 'ventas', 'pendiente', NULL, NULL, NULL, NULL, '2026-04-09 09:56:35', '2026-04-24 23:42:49', NULL, NULL, NULL, 'A'),
(11, 4, 2, 'entregado', 'formal', 'media', 4740.00, 19.7, -98.98, 'Av. Álvaro Obregón 88, Col. Roma, CDMX', NULL, NULL, NULL, NULL, 'ventas', 'pendiente', NULL, NULL, NULL, NULL, '2026-04-09 09:57:50', '2026-04-24 23:42:49', NULL, NULL, NULL, 'B'),
(12, 4, 2, 'entregado', 'formal', 'media', 7350.00, 19.7, -98.98, 'Av. Álvaro Obregón 88, Col. Roma, CDMX', NULL, NULL, NULL, NULL, 'ventas', 'pendiente', NULL, NULL, NULL, NULL, '2026-04-09 18:26:52', '2026-04-24 23:42:49', NULL, NULL, NULL, 'C'),
(13, 4, 2, 'entregado', 'formal', 'media', 14430.00, 19.7, -98.98, 'Av. Álvaro Obregón 88, Col. Roma, CDMX', NULL, NULL, NULL, NULL, 'ventas', 'pendiente', NULL, NULL, NULL, NULL, '2026-04-10 03:18:49', '2026-04-24 23:42:49', NULL, NULL, NULL, 'D'),
(14, 4, 2, 'aceptado', 'formal', 'media', 4448.00, 19.7, -98.98, 'Av. Álvaro Obregón 88, Col. Roma, CDMX', NULL, NULL, NULL, NULL, 'ventas', 'pendiente', NULL, NULL, NULL, NULL, '2026-04-10 11:06:12', '2026-04-24 23:42:49', NULL, NULL, NULL, 'E'),
(15, 4, 2, 'entregado', 'formal', 'media', 13180.00, 19.7, -98.98, 'entregar en la semana 17', NULL, NULL, NULL, NULL, 'ventas', 'pendiente', NULL, NULL, NULL, NULL, '2026-04-16 10:13:04', '2026-04-24 23:42:49', NULL, NULL, NULL, 'F'),
(16, 4, 2, 'en_camino', 'formal', 'media', 7350.00, 19.7, -98.98, 'ojo de agua pallide real del cid', NULL, NULL, NULL, NULL, 'ventas', 'pendiente', NULL, NULL, NULL, 'Ultima cerrada', '2026-04-20 17:49:10', '2026-04-24 23:42:49', NULL, NULL, NULL, '10'),
(17, 4, 2, 'aceptado', 'formal', 'media', 20880.00, 19.7, -98.98, 'Av. Álvaro Obregón 88, Col. Roma, CDMX', NULL, NULL, NULL, NULL, 'ventas', 'pendiente', NULL, NULL, NULL, NULL, '2026-04-20 20:26:30', '2026-04-24 23:42:49', NULL, NULL, NULL, '11'),
(18, 4, 2, 'aceptado', 'formal', 'media', 7580.00, 19.7, -98.98, 'Av. Álvaro Obregón 88, Col. Roma, CDMX', NULL, NULL, NULL, NULL, 'ventas', 'pendiente', NULL, NULL, NULL, NULL, '2026-04-23 01:24:35', '2026-04-24 23:42:49', NULL, NULL, NULL, '12'),
(19, 4, NULL, 'pendiente', 'formal', 'media', 2100.00, 19.7, -98.98, 'Av. Álvaro Obregón 88, Col. Roma, CDMX', NULL, NULL, NULL, NULL, 'ventas', 'pendiente', NULL, NULL, NULL, NULL, '2026-04-23 18:58:43', '2026-04-24 23:42:49', NULL, NULL, NULL, '13'),
(20, 4, NULL, 'pendiente', 'formal', 'media', 5940.00, 19.7, -98.98, 'Av. Álvaro Obregón 88, Col. Roma, CDMX', NULL, NULL, NULL, NULL, 'ventas', 'pendiente', NULL, NULL, NULL, NULL, '2026-04-23 19:43:02', '2026-04-24 23:42:49', NULL, NULL, NULL, '14'),
(21, 4, 3, 'aceptado', 'formal', 'media', 890.00, 19.7, -98.98, 'Av. Álvaro Obregón 88, Col. Roma, CDMX', NULL, NULL, NULL, NULL, 'ventas', 'pendiente', NULL, NULL, NULL, NULL, '2026-04-23 20:10:27', '2026-04-25 11:38:45', NULL, NULL, NULL, '15');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `pedido_evidencias`
--

CREATE TABLE `pedido_evidencias` (
  `id` int(11) NOT NULL,
  `pedido_id` int(11) NOT NULL,
  `foto_url` varchar(255) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `pedido_historial_estados`
--

CREATE TABLE `pedido_historial_estados` (
  `id` int(11) NOT NULL,
  `pedido_id` int(11) NOT NULL,
  `estado` varchar(40) NOT NULL,
  `nota` varchar(255) DEFAULT NULL,
  `usuario_id` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `pedido_historial_estados`
--

INSERT INTO `pedido_historial_estados` (`id`, `pedido_id`, `estado`, `nota`, `usuario_id`, `created_at`) VALUES
(1, 13, 'aceptado', 'Asignado manualmente a operador: Carlos López (distancia: 40.49 km) [fuera de rango autorizado]', 1, '2026-04-19 17:21:05'),
(2, 13, 'cancelado', 'Cancelado por coordinacion desde resumen de pedido', 1, '2026-04-19 17:21:13'),
(3, 13, 'cancelado', 'Cambio de estado desde panel admin', 1, '2026-04-19 17:21:20'),
(4, 11, 'en_camino', 'Cambio de estado desde panel admin', 1, '2026-04-19 17:21:25'),
(5, 14, 'pendiente', 'Cambio de estado desde panel admin', 1, '2026-04-19 17:22:08'),
(6, 14, 'aceptado', 'Asignado manualmente a operador: Carlos López (distancia: 40.49 km) [fuera de rango autorizado]', 1, '2026-04-19 17:23:47'),
(7, 15, 'cancelado', 'Cambio de estado desde panel admin', 1, '2026-04-19 17:23:58'),
(8, 16, 'pendiente', 'Pedido creado por cliente', 4, '2026-04-20 11:49:10'),
(9, 16, 'aceptado', 'Asignado manualmente a operador: Carlos López (distancia: 40.49 km) [fuera de rango autorizado]', 1, '2026-04-20 11:49:47'),
(10, 15, 'entregado', 'Cambio de estado desde panel admin', 1, '2026-04-20 11:54:39'),
(11, 13, 'entregado', 'Cambio de estado desde panel admin', 1, '2026-04-20 11:54:44'),
(12, 11, 'entregado', 'Cambio de estado desde panel admin', 1, '2026-04-20 11:54:50'),
(13, 9, 'entregado', 'Cambio de estado desde panel admin', 1, '2026-04-20 11:54:55'),
(14, 5, 'entregado', 'Cambio de estado desde panel admin', 1, '2026-04-20 11:55:01'),
(15, 16, 'en_camino', 'Cambio de estado desde panel admin', 1, '2026-04-20 11:55:08'),
(16, 17, 'pendiente', 'Pedido creado por cliente', 4, '2026-04-20 14:26:30'),
(17, 17, 'aceptado', 'Asignado manualmente a operador: Carlos López (distancia: 40.49 km) [fuera de rango autorizado]', 1, '2026-04-20 14:45:55'),
(18, 18, 'pendiente', 'Pedido creado por cliente', 4, '2026-04-22 19:24:35'),
(19, 18, 'aceptado', 'Asignacion automatica por cercania en km', 2, '2026-04-22 19:24:35'),
(20, 19, 'pendiente', 'Pedido creado por cliente', 4, '2026-04-23 12:58:43'),
(21, 20, 'pendiente', 'Pedido creado por cliente', 4, '2026-04-23 13:43:02'),
(22, 21, 'pendiente', 'Pedido creado por cliente', 4, '2026-04-23 14:10:27'),
(23, 20, 'pendiente', 'Cambio de estado desde panel admin', 6, '2026-04-23 19:20:54'),
(24, 21, 'aceptado', 'Asignado manualmente a operador: Ana Martínez (distancia: 0.00 km)', 6, '2026-04-25 11:38:45');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `pedido_items`
--

CREATE TABLE `pedido_items` (
  `id` int(11) NOT NULL,
  `pedido_id` int(11) NOT NULL,
  `producto_id` int(11) NOT NULL,
  `cantidad` int(11) NOT NULL DEFAULT 1,
  `precio_unit` decimal(10,2) NOT NULL,
  `ajuste_cliente` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `pedido_items`
--

INSERT INTO `pedido_items` (`id`, `pedido_id`, `producto_id`, `cantidad`, `precio_unit`, `ajuste_cliente`) VALUES
(1, 1, 1, 1, 1250.00, NULL),
(2, 1, 5, 1, 1980.00, NULL),
(3, 1, 12, 1, 1820.00, NULL),
(4, 2, 1, 2, 1250.00, NULL),
(5, 2, 22, 1, 1620.00, NULL),
(6, 2, 27, 1, 6200.00, NULL),
(7, 3, 5, 1, 1980.00, NULL),
(8, 3, 18, 1, 2750.00, NULL),
(9, 3, 20, 1, 1480.00, NULL),
(10, 3, 25, 1, 2350.00, NULL),
(11, 3, 26, 1, 5800.00, NULL),
(12, 4, 1, 1, 1250.00, NULL),
(13, 4, 2, 1, 890.00, NULL),
(14, 4, 9, 1, 2800.00, NULL),
(15, 4, 10, 1, 1650.00, NULL),
(16, 4, 11, 1, 2200.00, NULL),
(17, 4, 12, 1, 1820.00, NULL),
(18, 4, 15, 1, 4500.00, NULL),
(19, 4, 17, 1, 920.00, NULL),
(20, 5, 1, 1, 1250.00, NULL),
(21, 5, 2, 1, 890.00, NULL),
(22, 5, 16, 1, 1350.00, NULL),
(23, 5, 17, 1, 920.00, NULL),
(24, 6, 1, 1, 1250.00, NULL),
(25, 6, 9, 1, 2800.00, NULL),
(26, 6, 10, 1, 1650.00, NULL),
(27, 7, 1, 1, 1250.00, NULL),
(28, 8, 4, 1, 1750.00, NULL),
(29, 8, 5, 1, 1980.00, NULL),
(30, 9, 3, 1, 2100.00, NULL),
(31, 9, 5, 1, 1980.00, NULL),
(32, 9, 6, 1, 3200.00, NULL),
(33, 10, 1, 1, 1250.00, NULL),
(34, 10, 2, 1, 890.00, NULL),
(35, 10, 3, 1, 2100.00, NULL),
(36, 11, 2, 1, 890.00, NULL),
(37, 11, 3, 1, 2100.00, NULL),
(38, 11, 4, 1, 1750.00, NULL),
(39, 12, 3, 1, 2100.00, NULL),
(40, 12, 4, 3, 1750.00, NULL),
(41, 13, 1, 4, 1250.00, NULL),
(42, 13, 2, 7, 890.00, NULL),
(43, 13, 6, 1, 3200.00, NULL),
(44, 14, 29, 4, 1112.00, NULL),
(45, 15, 5, 1, 1980.00, NULL),
(46, 15, 8, 20, 560.00, NULL),
(47, 16, 3, 1, 2100.00, NULL),
(48, 16, 4, 3, 1750.00, NULL),
(49, 17, 1, 6, 1250.00, NULL),
(50, 17, 2, 2, 890.00, NULL),
(51, 17, 3, 4, 2100.00, NULL),
(52, 17, 6, 1, 3200.00, NULL),
(53, 18, 2, 7, 890.00, NULL),
(54, 18, 16, 1, 1350.00, NULL),
(55, 19, 3, 1, 2100.00, NULL),
(56, 20, 5, 3, 1980.00, NULL),
(57, 21, 2, 1, 890.00, NULL);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `productos`
--

CREATE TABLE `productos` (
  `id` int(11) NOT NULL,
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
  `categoria_slug` varchar(20) DEFAULT NULL,
  `subcategoria_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `productos`
--

INSERT INTO `productos` (`id`, `codigo`, `nombre`, `precio`, `stock`, `unidad_medida`, `fecha_caducidad`, `fecha_ingreso`, `lote_activo_id`, `activo`, `imagen`, `especie_id`, `subcategoria`, `cliente_ajustable`, `categoria_slug`, `subcategoria_id`) VALUES
(1, '101190055', 'AB20 MEXICO PAH5', 1250.00, 94, 'piezas', NULL, NULL, 1, 1, NULL, 1, NULL, 1, 'vaca', 2),
(2, '173931925', 'ANIMARETO OMNIPLUS', 890.00, 90, 'piezas', NULL, NULL, 2, 1, 'prod_2.jpg', 1, NULL, 1, 'cerdo', 3),
(3, '10010407', 'AUREO S700 36G/LBX50LB TB ES', 2100.00, 94, 'piezas', NULL, NULL, 3, 1, 'prod_3.jpg', 1, NULL, 1, 'pollo', 4),
(4, '10023673', 'AUROFAC200 200GA/KGX25KG BG', 1750.00, 97, 'piezas', NULL, NULL, 4, 1, 'prod_4.jpg', 1, NULL, 1, 'vaca', 5),
(5, '10009982', 'AVATEC 15% 150G/KGX20KG TB MX', 1980.00, 96, 'piezas', NULL, NULL, 5, 1, 'prod_5.jpg', 1, NULL, 1, 'cerdo', 6),
(6, '260221', 'AVIAX 5% 25 KG MEXICO', 3200.00, 99, 'piezas', NULL, NULL, 6, 1, 'prod_6.jpg', 1, NULL, 1, 'pollo', 1),
(7, '299088', 'AVIAX PLUS 25KG MEXICO', 3450.00, 100, 'piezas', NULL, NULL, 7, 1, 'prod_7.jpg', 1, NULL, 1, 'vaca', 2),
(8, '299001', 'AVICARB', 560.00, 80, 'piezas', NULL, NULL, 8, 1, 'prod_8.jpg', 1, NULL, 1, 'cerdo', 3),
(9, '173560925', 'BEEF CATTLE SUPREME PREMIX', 2800.00, 100, 'piezas', NULL, NULL, 9, 1, 'prod_9.jpg', 1, NULL, 1, 'pollo', 4),
(10, '10009727', 'BMD 11% 110G/KGX25KG TB ES', 1650.00, 100, 'piezas', NULL, NULL, 10, 1, 'prod_10.jpg', 1, NULL, 1, 'vaca', 5),
(11, '4265009', 'BOVENSIN 20 25 KG BAG', 2200.00, 100, 'piezas', NULL, NULL, 11, 1, 'prod_11.jpg', 1, NULL, 1, 'cerdo', 6),
(12, '10010469', 'BVTC 15% 150G/KGX25KG TB ES', 1820.00, 100, 'piezas', NULL, NULL, 12, 1, 'prod_12.jpg', 1, NULL, 1, 'pollo', 1),
(13, '80063400', 'CALCIUM IODATE 63.5% I', 740.00, 100, 'piezas', NULL, NULL, 13, 1, 'prod_13.jpg', 1, NULL, 1, 'vaca', 2),
(14, '173463925', 'CCF GAQSA PMX', 1100.00, 100, 'piezas', NULL, NULL, 14, 1, 'prod_14.jpg', 1, NULL, 1, 'cerdo', 3),
(15, '137106055', 'CELLERATE CULT CLASSC PLUS MEX', 4500.00, 100, 'piezas', NULL, NULL, 15, 1, 'prod_15.jpg', 1, NULL, 1, 'pollo', 4),
(16, '4260603', 'CERDIMIX 15 25 KG BAG', 1350.00, 99, 'piezas', NULL, NULL, 16, 1, 'prod_16.jpg', 1, NULL, 1, 'vaca', 5),
(17, '80551433', 'COBALT CARB 46% 15KG', 920.00, 100, 'piezas', NULL, NULL, 17, 1, 'prod_17.jpg', 1, NULL, 1, 'cerdo', 6),
(18, '4260608', 'COXISTAC 12% 25 KG BAG', 2750.00, 100, 'piezas', NULL, NULL, 18, 1, 'prod_18.jpg', 1, NULL, 1, 'pollo', 1),
(19, '8511037', 'EPHICAX 110 25KG BAG', 2100.00, 100, 'piezas', NULL, NULL, 20, 1, 'prod_19.jpg', 1, NULL, 1, 'vaca', 2),
(20, '4260300', 'ESKALIN 25 25 KG BAG', 1480.00, 100, 'piezas', NULL, NULL, 21, 1, 'prod_20.jpg', 1, NULL, 1, 'cerdo', 3),
(21, '10014064', 'DECCOX 6% 60G/KGX25KG TB ES', 1900.00, 100, 'piezas', NULL, NULL, 19, 1, 'prod_21.jpg', 1, NULL, 1, 'pollo', 4),
(22, '4260412', 'STAFAC 40 25 KG BAG', 1620.00, 100, 'piezas', NULL, NULL, 25, 1, 'prod_22.jpg', 1, NULL, 1, 'vaca', 5),
(23, '6185000', 'STAFAC 500 25 KG BOX', 3100.00, 100, 'piezas', NULL, NULL, 26, 1, 'prod_23.jpg', 1, NULL, 1, 'cerdo', 6),
(24, '8319023', 'PAQ-PROTEX TM 25 KG BAG', 1780.00, 100, 'piezas', NULL, NULL, 22, 1, 'prod_24.jpg', 1, NULL, 1, 'pollo', 1),
(25, '77167925', 'VISTORE CU 580 MEXICO', 2350.00, 100, 'piezas', NULL, NULL, 29, 1, 'prod_25.jpg', 1, NULL, 1, 'vaca', 2),
(26, '10-804547', 'TABIC IB VAR 5000 DS', 5800.00, 100, 'piezas', NULL, NULL, 27, 1, 'prod_26.jpg', 1, NULL, 1, 'cerdo', 3),
(27, '10-806541', 'TABIC IBVAR206 5000 DS', 6200.00, 100, 'piezas', NULL, NULL, 28, 1, 'prod_27.jpg', 1, NULL, 1, 'pollo', 4),
(28, '1111', 'prueba', 212.00, 12, 'piezas', NULL, NULL, 23, 1, 'prod_28.webp', 1, NULL, 1, 'vaca', 5),
(29, '222', 'prueba2', 1112.00, 100, 'piezas', NULL, NULL, 24, 1, 'prod_29.webp', 1, NULL, 1, 'cerdo', 6);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `producto_lotes`
--

CREATE TABLE `producto_lotes` (
  `id` int(11) NOT NULL,
  `producto_id` int(11) NOT NULL,
  `lote_numero` int(11) NOT NULL,
  `fecha_ingreso` datetime NOT NULL,
  `fecha_caducidad` datetime NOT NULL,
  `unidades` int(11) NOT NULL DEFAULT 0,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `producto_lotes`
--

INSERT INTO `producto_lotes` (`id`, `producto_id`, `lote_numero`, `fecha_ingreso`, `fecha_caducidad`, `unidades`, `activo`, `created_by`, `created_at`) VALUES
(1, 1, 1, '2026-04-21 20:06:55', '2027-04-21 20:06:55', 94, 1, NULL, '2026-04-21 14:06:55'),
(2, 2, 1, '2026-04-21 20:06:55', '2027-04-21 20:06:55', 90, 1, NULL, '2026-04-21 14:06:55'),
(3, 3, 1, '2026-04-21 20:06:55', '2027-04-21 20:06:55', 94, 1, NULL, '2026-04-21 14:06:55'),
(4, 4, 1, '2026-04-21 20:06:55', '2027-04-21 20:06:55', 97, 1, NULL, '2026-04-21 14:06:55'),
(5, 5, 1, '2026-04-21 20:06:55', '2027-04-21 20:06:55', 96, 1, NULL, '2026-04-21 14:06:55'),
(6, 6, 1, '2026-04-21 20:06:55', '2027-04-21 20:06:55', 99, 1, NULL, '2026-04-21 14:06:55'),
(7, 7, 1, '2026-04-21 20:06:55', '2027-04-21 20:06:55', 100, 1, NULL, '2026-04-21 14:06:55'),
(8, 8, 1, '2026-04-21 20:06:55', '2027-04-21 20:06:55', 80, 1, NULL, '2026-04-21 14:06:55'),
(9, 9, 1, '2026-04-21 20:06:55', '2027-04-21 20:06:55', 100, 1, NULL, '2026-04-21 14:06:55'),
(10, 10, 1, '2026-04-21 20:06:55', '2027-04-21 20:06:55', 100, 1, NULL, '2026-04-21 14:06:55'),
(11, 11, 1, '2026-04-21 20:06:55', '2027-04-21 20:06:55', 100, 1, NULL, '2026-04-21 14:06:55'),
(12, 12, 1, '2026-04-21 20:06:55', '2027-04-21 20:06:55', 100, 1, NULL, '2026-04-21 14:06:55'),
(13, 13, 1, '2026-04-21 20:06:55', '2027-04-21 20:06:55', 100, 1, NULL, '2026-04-21 14:06:55'),
(14, 14, 1, '2026-04-21 20:06:55', '2027-04-21 20:06:55', 100, 1, NULL, '2026-04-21 14:06:55'),
(15, 15, 1, '2026-04-21 20:06:55', '2027-04-21 20:06:55', 100, 1, NULL, '2026-04-21 14:06:55'),
(16, 16, 1, '2026-04-21 20:06:55', '2027-04-21 20:06:55', 99, 1, NULL, '2026-04-21 14:06:55'),
(17, 17, 1, '2026-04-21 20:06:55', '2027-04-21 20:06:55', 100, 1, NULL, '2026-04-21 14:06:55'),
(18, 18, 1, '2026-04-21 20:06:55', '2027-04-21 20:06:55', 100, 1, NULL, '2026-04-21 14:06:55'),
(19, 21, 1, '2026-04-21 20:06:55', '2027-04-21 20:06:55', 100, 1, NULL, '2026-04-21 14:06:55'),
(20, 19, 1, '2026-04-21 20:06:55', '2027-04-21 20:06:55', 100, 1, NULL, '2026-04-21 14:06:55'),
(21, 20, 1, '2026-04-21 20:06:55', '2027-04-21 20:06:55', 100, 1, NULL, '2026-04-21 14:06:55'),
(22, 24, 1, '2026-04-21 20:06:55', '2027-04-21 20:06:55', 100, 1, NULL, '2026-04-21 14:06:55'),
(23, 28, 1, '2026-04-21 20:06:55', '2027-04-21 20:06:55', 12, 1, NULL, '2026-04-21 14:06:55'),
(24, 29, 1, '2026-04-21 20:06:55', '2027-04-21 20:06:55', 100, 1, NULL, '2026-04-21 14:06:55'),
(25, 22, 1, '2026-04-21 20:06:55', '2027-04-21 20:06:55', 100, 1, NULL, '2026-04-21 14:06:55'),
(26, 23, 1, '2026-04-21 20:06:55', '2027-04-21 20:06:55', 100, 1, NULL, '2026-04-21 14:06:55'),
(27, 26, 1, '2026-04-21 20:06:55', '2027-04-21 20:06:55', 100, 1, NULL, '2026-04-21 14:06:55'),
(28, 27, 1, '2026-04-21 20:06:55', '2027-04-21 20:06:55', 100, 1, NULL, '2026-04-21 14:06:55'),
(29, 25, 1, '2026-04-21 20:06:55', '2027-04-21 20:06:55', 100, 1, NULL, '2026-04-21 14:06:55');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `subcategorias_catalogo`
--

CREATE TABLE `subcategorias_catalogo` (
  `id` int(11) NOT NULL,
  `especie_id` int(11) NOT NULL,
  `nombre` varchar(80) NOT NULL,
  `slug` varchar(80) NOT NULL,
  `descripcion` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `subcategorias_globales`
--

CREATE TABLE `subcategorias_globales` (
  `id` int(11) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `subcategorias_globales`
--

INSERT INTO `subcategorias_globales` (`id`, `nombre`, `activo`, `created_at`) VALUES
(1, 'Alimento', 1, '2026-04-24 23:42:49'),
(2, 'Comida', 1, '2026-04-24 23:42:49'),
(3, 'Proteina', 1, '2026-04-24 23:42:49'),
(4, 'Engorda', 1, '2026-04-24 23:42:49'),
(5, 'Lacteo', 1, '2026-04-24 23:42:49'),
(6, 'Suplemento', 1, '2026-04-24 23:42:49');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `tracking`
--

CREATE TABLE `tracking` (
  `id` int(11) NOT NULL,
  `pedido_id` int(11) NOT NULL,
  `operador_id` int(11) NOT NULL,
  `lat` double NOT NULL,
  `lng` double NOT NULL,
  `ts` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `tracking`
--

INSERT INTO `tracking` (`id`, `pedido_id`, `operador_id`, `lat`, `lng`, `ts`) VALUES
(1, 12, 2, 19.6828064, -98.8908519, '2026-04-09 18:28:30'),
(2, 12, 2, 19.6828064, -98.8908519, '2026-04-09 18:28:30'),
(3, 12, 2, 19.682715, -98.8908801, '2026-04-09 18:28:39'),
(4, 12, 2, 19.682715, -98.8908801, '2026-04-09 18:28:39'),
(5, 12, 2, 19.6826982, -98.8908652, '2026-04-09 18:28:47'),
(6, 12, 2, 19.6826982, -98.8908652, '2026-04-09 18:28:47'),
(7, 12, 2, 19.6827124, -98.8908577, '2026-04-09 18:28:57'),
(8, 12, 2, 19.6827124, -98.8908577, '2026-04-09 18:28:57'),
(9, 12, 2, 19.6827124, -98.8908577, '2026-04-09 18:28:57'),
(10, 12, 2, 19.6827951, -98.89084, '2026-04-09 18:29:32'),
(11, 12, 2, 19.6827951, -98.89084, '2026-04-09 18:29:32'),
(12, 12, 2, 19.6827303, -98.890861, '2026-04-09 18:29:41'),
(13, 12, 2, 19.6827303, -98.890861, '2026-04-09 18:29:41'),
(14, 12, 2, 19.6827303, -98.890861, '2026-04-09 18:29:41'),
(15, 12, 2, 19.6827303, -98.890861, '2026-04-09 18:29:49'),
(16, 12, 2, 19.6827303, -98.890861, '2026-04-09 18:29:49'),
(17, 12, 2, 19.6826867, -98.890894, '2026-04-09 18:30:19'),
(18, 12, 2, 19.6826867, -98.890894, '2026-04-09 18:30:19'),
(19, 11, 2, 19.6827568, -98.8908708, '2026-04-09 18:30:23'),
(20, 11, 2, 19.6827544, -98.8908641, '2026-04-09 18:30:32'),
(21, 11, 2, 19.6827544, -98.8908641, '2026-04-09 18:30:32'),
(22, 11, 2, 19.6827515, -98.8908749, '2026-04-09 18:30:42'),
(23, 11, 2, 19.6827515, -98.8908749, '2026-04-09 18:30:42'),
(24, 11, 2, 19.6827515, -98.8908749, '2026-04-09 18:30:42'),
(25, 11, 2, 19.6827705, -98.8908782, '2026-04-09 18:30:53'),
(26, 11, 2, 19.6827705, -98.8908782, '2026-04-09 18:30:53'),
(27, 11, 2, 19.6827705, -98.8908782, '2026-04-09 18:30:53'),
(28, 11, 2, 19.6828008, -98.8907967, '2026-04-09 18:31:01'),
(29, 11, 2, 19.682803, -98.8908382, '2026-04-10 00:35:17'),
(30, 11, 2, 19.682803, -98.8908382, '2026-04-10 00:35:17'),
(31, 11, 2, 19.6827698, -98.8908324, '2026-04-10 00:35:25'),
(32, 11, 2, 19.6827698, -98.8908324, '2026-04-10 00:35:25'),
(33, 9, 2, 19.6827673, -98.8908313, '2026-04-10 00:35:34'),
(34, 9, 2, 19.6827673, -98.8908313, '2026-04-10 00:35:34'),
(35, 5, 2, 19.6827103, -98.8908632, '2026-04-10 00:36:26'),
(36, 5, 2, 19.6827103, -98.8908632, '2026-04-10 00:36:26'),
(37, 5, 2, 19.6827057, -98.8908763, '2026-04-10 00:36:35'),
(38, 5, 2, 19.6827057, -98.8908763, '2026-04-10 00:36:35'),
(39, 5, 2, 19.6828171, -98.8908137, '2026-04-10 00:36:44'),
(40, 5, 2, 19.6828171, -98.8908137, '2026-04-10 00:36:44'),
(41, 5, 2, 19.6828163, -98.8908432, '2026-04-10 00:36:53'),
(42, 5, 2, 19.6828163, -98.8908432, '2026-04-10 00:36:53'),
(43, 5, 2, 19.6828163, -98.8908432, '2026-04-10 00:36:59'),
(44, 5, 2, 19.6828163, -98.8908432, '2026-04-10 00:36:59'),
(45, 5, 2, 19.6827726, -98.8908496, '2026-04-10 00:37:23'),
(46, 5, 2, 19.6827726, -98.8908496, '2026-04-10 00:37:24'),
(47, 5, 2, 19.682453, -98.890915, '2026-04-10 03:19:19'),
(48, 5, 2, 19.682453, -98.890915, '2026-04-10 03:19:19'),
(49, 5, 2, 19.682453, -98.890915, '2026-04-10 03:19:22'),
(50, 5, 2, 19.682453, -98.890915, '2026-04-10 03:19:26'),
(51, 5, 2, 19.682453, -98.890915, '2026-04-10 03:19:30'),
(52, 5, 2, 19.682453, -98.890915, '2026-04-10 03:19:35'),
(53, 5, 2, 19.682453, -98.890915, '2026-04-10 03:19:40'),
(54, 5, 2, 19.682453, -98.890915, '2026-04-10 03:19:43'),
(55, 5, 2, 19.682453, -98.890915, '2026-04-10 03:19:47'),
(56, 5, 2, 19.682453, -98.890915, '2026-04-10 03:19:51'),
(57, 5, 2, 19.682453, -98.890915, '2026-04-10 03:19:55'),
(58, 5, 2, 19.682453, -98.890915, '2026-04-10 03:19:59'),
(59, 5, 2, 19.682453, -98.890915, '2026-04-10 03:20:03'),
(60, 5, 2, 19.682451, -98.890953, '2026-04-10 03:20:07'),
(61, 5, 2, 19.682451, -98.890953, '2026-04-10 03:20:11'),
(62, 5, 2, 19.682451, -98.890953, '2026-04-10 03:20:15'),
(63, 5, 2, 19.682451, -98.890953, '2026-04-10 03:20:19'),
(64, 5, 2, 19.682451, -98.890953, '2026-04-10 03:20:23'),
(65, 5, 2, 19.682451, -98.890953, '2026-04-10 03:20:26'),
(66, 5, 2, 19.682451, -98.890953, '2026-04-10 03:20:30'),
(67, 5, 2, 19.682451, -98.890953, '2026-04-10 03:20:34'),
(68, 5, 2, 19.682451, -98.890953, '2026-04-10 03:20:38'),
(69, 5, 2, 19.682451, -98.890953, '2026-04-10 03:20:42'),
(70, 11, 2, 19.682451, -98.890953, '2026-04-10 03:20:45'),
(71, 9, 2, 19.682451, -98.890953, '2026-04-10 03:20:46'),
(72, 11, 2, 19.682451, -98.890953, '2026-04-10 03:20:49'),
(73, 5, 2, 19.682451, -98.890953, '2026-04-10 03:20:51'),
(74, 5, 2, 19.682451, -98.890953, '2026-04-10 03:20:55'),
(75, 5, 2, 19.682451, -98.890953, '2026-04-10 03:20:59'),
(76, 5, 2, 19.682451, -98.890953, '2026-04-10 03:21:03'),
(77, 5, 2, 19.682451, -98.890953, '2026-04-10 03:21:08'),
(78, 5, 2, 19.682451, -98.890953, '2026-04-10 03:21:11'),
(79, 5, 2, 19.682451, -98.890953, '2026-04-10 03:21:15'),
(80, 5, 2, 19.682451, -98.890953, '2026-04-10 03:21:19'),
(81, 5, 2, 19.682451, -98.890953, '2026-04-10 03:21:23'),
(82, 11, 2, 19.682451, -98.890953, '2026-04-10 03:21:27'),
(83, 11, 2, 19.682451, -98.890953, '2026-04-10 03:21:31'),
(84, 11, 2, 19.682451, -98.890953, '2026-04-10 03:21:35'),
(85, 11, 2, 19.682451, -98.890953, '2026-04-10 03:21:39'),
(86, 11, 2, 19.682451, -98.890953, '2026-04-10 03:21:43'),
(87, 9, 2, 19.682451, -98.890953, '2026-04-10 03:21:47'),
(88, 9, 2, 19.682451, -98.890953, '2026-04-10 03:21:51'),
(89, 9, 2, 19.682451, -98.890953, '2026-04-10 03:21:55'),
(90, 9, 2, 19.682451, -98.890953, '2026-04-10 03:21:59'),
(91, 5, 2, 19.682451, -98.890953, '2026-04-10 03:22:01'),
(92, 5, 2, 19.682451, -98.890953, '2026-04-10 03:22:05'),
(93, 5, 2, 19.682457, -98.89093, '2026-04-10 03:22:09'),
(94, 5, 2, 19.6828028, -98.8907988, '2026-04-10 04:08:17'),
(95, 5, 2, 19.6827894, -98.8908039, '2026-04-10 04:08:26'),
(96, 5, 2, 19.6827894, -98.8908039, '2026-04-10 04:08:26'),
(97, 5, 2, 19.6827825, -98.8908023, '2026-04-10 04:08:30'),
(98, 5, 2, 19.6827825, -98.8908023, '2026-04-10 04:08:30'),
(99, 5, 2, 19.6828046, -98.8907925, '2026-04-10 04:08:38'),
(100, 5, 2, 19.6828046, -98.8907925, '2026-04-10 04:08:38'),
(101, 5, 2, 19.6828154, -98.8908247, '2026-04-10 04:08:47'),
(102, 5, 2, 19.6828154, -98.8908247, '2026-04-10 04:08:47'),
(103, 5, 2, 19.682449, -98.890938, '2026-04-10 11:08:07'),
(104, 5, 2, 19.682449, -98.890938, '2026-04-10 11:08:11'),
(105, 5, 2, 19.682449, -98.890938, '2026-04-10 11:08:15'),
(106, 5, 2, 19.682449, -98.890938, '2026-04-10 11:08:19'),
(107, 5, 2, 19.682449, -98.890938, '2026-04-10 11:08:23'),
(108, 5, 2, 19.682449, -98.890938, '2026-04-10 11:08:27'),
(109, 5, 2, 19.682449, -98.890938, '2026-04-10 11:08:31'),
(110, 5, 2, 19.682449, -98.890938, '2026-04-10 11:08:35'),
(111, 5, 2, 19.682449, -98.890938, '2026-04-10 11:08:39'),
(112, 5, 2, 19.682450624371, -98.890939053536, '2026-04-10 11:08:43'),
(113, 5, 2, 19.682450624371, -98.890939053536, '2026-04-10 11:08:47'),
(114, 5, 2, 19.682450624371, -98.890939053536, '2026-04-10 11:08:51'),
(115, 5, 2, 19.682450624371, -98.890939053536, '2026-04-10 11:08:55'),
(116, 5, 2, 19.682450624371, -98.890939053536, '2026-04-10 11:08:59'),
(117, 5, 2, 19.682450624371, -98.890939053536, '2026-04-10 11:09:03'),
(118, 5, 2, 19.682450624371, -98.890939053536, '2026-04-10 11:09:07'),
(119, 5, 2, 19.682450624371, -98.890939053536, '2026-04-10 11:09:11'),
(120, 5, 2, 19.682450624371, -98.890939053536, '2026-04-10 11:09:15'),
(121, 5, 2, 19.682450624371, -98.890939053536, '2026-04-10 11:09:19'),
(122, 5, 2, 19.682450624371, -98.890939053536, '2026-04-10 11:09:23'),
(123, 5, 2, 19.682450624371, -98.890939053536, '2026-04-10 11:09:27'),
(124, 5, 2, 19.682450152316, -98.890939262882, '2026-04-10 11:09:31'),
(125, 5, 2, 19.682450152316, -98.890939262882, '2026-04-10 11:09:35'),
(126, 5, 2, 19.682450152316, -98.890939262882, '2026-04-10 11:09:39'),
(127, 5, 2, 19.682450291541, -98.890939201138, '2026-04-10 11:09:43'),
(128, 5, 2, 19.682450291541, -98.890939201138, '2026-04-10 11:09:47'),
(129, 5, 2, 19.682450291541, -98.890939201138, '2026-04-10 11:09:51'),
(130, 5, 2, 19.682450291541, -98.890939201138, '2026-04-10 11:09:55'),
(131, 5, 2, 19.682450291541, -98.890939201138, '2026-04-10 11:09:59'),
(132, 5, 2, 19.682450291541, -98.890939201138, '2026-04-10 11:10:03'),
(133, 5, 2, 19.682450291541, -98.890939201138, '2026-04-10 11:10:07'),
(134, 5, 2, 19.682450291541, -98.890939201138, '2026-04-10 11:10:11'),
(135, 5, 2, 19.682450291541, -98.890939201138, '2026-04-10 11:10:15'),
(136, 5, 2, 19.682450291541, -98.890939201138, '2026-04-10 11:10:19'),
(137, 5, 2, 19.682450291541, -98.890939201138, '2026-04-10 11:10:23'),
(138, 5, 2, 19.682450291541, -98.890939201138, '2026-04-10 11:10:27'),
(139, 5, 2, 19.682450333225, -98.890939711802, '2026-04-10 11:10:31'),
(140, 5, 2, 19.682450333225, -98.890939711802, '2026-04-10 11:10:35'),
(141, 5, 2, 19.682450333225, -98.890939711802, '2026-04-10 11:10:39'),
(142, 5, 2, 19.682450333225, -98.890939711802, '2026-04-10 11:10:43'),
(143, 5, 2, 19.682450333225, -98.890939711802, '2026-04-10 11:10:47'),
(144, 5, 2, 19.682450333225, -98.890939711802, '2026-04-10 11:10:51'),
(145, 14, 2, 19.682450333225, -98.890939711802, '2026-04-10 11:11:01'),
(146, 14, 2, 19.682450333225, -98.890939711802, '2026-04-10 11:11:05'),
(147, 14, 2, 19.682450333225, -98.890939711802, '2026-04-10 11:11:09'),
(148, 14, 2, 19.682450291541, -98.890939201138, '2026-04-10 11:11:13'),
(149, 14, 2, 19.682450291541, -98.890939201138, '2026-04-10 11:11:17'),
(150, 14, 2, 19.682450291541, -98.890939201138, '2026-04-10 11:11:21'),
(151, 14, 2, 19.682450267979, -98.890939211587, '2026-04-10 11:11:25'),
(152, 14, 2, 19.682450267979, -98.890939211587, '2026-04-10 11:11:29'),
(153, 14, 2, 19.682450267979, -98.890939211587, '2026-04-10 11:11:33'),
(154, 14, 2, 19.682450267979, -98.890939211587, '2026-04-10 11:11:37'),
(155, 14, 2, 19.682450267979, -98.890939211587, '2026-04-10 11:11:41'),
(156, 14, 2, 19.682450267979, -98.890939211587, '2026-04-10 11:11:45'),
(157, 14, 2, 19.682450267979, -98.890939211587, '2026-04-10 11:11:49'),
(158, 14, 2, 19.682449617372, -98.890942992347, '2026-04-10 11:12:58'),
(159, 14, 2, 19.682449617372, -98.890942992347, '2026-04-10 11:13:02'),
(160, 5, 2, 19.682449617372, -98.890942992347, '2026-04-10 11:13:09'),
(161, 5, 2, 19.682449617372, -98.890942992347, '2026-04-10 11:13:13'),
(162, 15, 2, 19.682438, -98.891014, '2026-04-16 18:38:18'),
(163, 15, 2, 19.682438, -98.891014, '2026-04-16 18:38:22'),
(164, 15, 2, 19.682438, -98.891014, '2026-04-16 18:38:26'),
(165, 15, 2, 19.682438, -98.891014, '2026-04-16 18:38:30'),
(166, 15, 2, 19.682438, -98.891014, '2026-04-16 18:38:34'),
(167, 15, 2, 19.682438, -98.891014, '2026-04-16 18:38:38'),
(168, 15, 2, 19.682739, -98.890843, '2026-04-16 18:38:42'),
(169, 15, 2, 19.682739, -98.890843, '2026-04-16 18:38:46'),
(170, 15, 2, 19.682739, -98.890843, '2026-04-16 18:38:50'),
(171, 15, 2, 19.682739, -98.890843, '2026-04-16 18:38:54'),
(172, 15, 2, 19.682739, -98.890843, '2026-04-16 18:38:58'),
(173, 15, 2, 19.682739, -98.890843, '2026-04-16 18:39:02'),
(174, 15, 2, 19.682739, -98.890843, '2026-04-16 18:39:06'),
(175, 15, 2, 19.682739, -98.890843, '2026-04-16 18:39:10'),
(176, 15, 2, 19.682739, -98.890843, '2026-04-16 18:39:14'),
(177, 15, 2, 19.682739, -98.890843, '2026-04-16 18:39:18'),
(178, 15, 2, 19.682739, -98.890843, '2026-04-16 18:39:22'),
(179, 15, 2, 19.682739, -98.890843, '2026-04-16 18:39:26'),
(180, 15, 2, 19.682739, -98.890843, '2026-04-16 18:39:30'),
(181, 15, 2, 19.682739, -98.890843, '2026-04-16 18:39:34'),
(182, 15, 2, 19.682739, -98.890843, '2026-04-16 18:39:38'),
(183, 15, 2, 19.682739, -98.890843, '2026-04-16 18:39:42'),
(184, 15, 2, 19.682739, -98.890843, '2026-04-16 18:39:46'),
(185, 15, 2, 19.682739, -98.890843, '2026-04-16 18:39:50'),
(186, 15, 2, 19.682739, -98.890843, '2026-04-16 18:39:54'),
(187, 15, 2, 19.682739, -98.890843, '2026-04-16 18:39:58'),
(188, 15, 2, 19.682739, -98.890843, '2026-04-16 18:40:02'),
(189, 15, 2, 19.682739, -98.890843, '2026-04-16 18:40:06'),
(190, 15, 2, 19.682739, -98.890843, '2026-04-16 18:40:10'),
(191, 15, 2, 19.682739, -98.890843, '2026-04-16 18:40:14'),
(192, 15, 2, 19.682739, -98.890843, '2026-04-16 18:40:18'),
(193, 15, 2, 19.682739, -98.890843, '2026-04-16 18:40:22'),
(194, 15, 2, 19.682739, -98.890843, '2026-04-16 18:40:26'),
(195, 15, 2, 19.682739, -98.890843, '2026-04-16 18:40:30'),
(196, 15, 2, 19.682739, -98.890843, '2026-04-16 18:40:34'),
(197, 15, 2, 19.682739, -98.890843, '2026-04-16 18:40:38'),
(198, 15, 2, 19.682739, -98.890843, '2026-04-16 18:40:42'),
(199, 15, 2, 19.682739, -98.890843, '2026-04-16 18:40:46'),
(200, 15, 2, 19.682739, -98.890843, '2026-04-16 18:40:50'),
(201, 15, 2, 19.682739, -98.890843, '2026-04-16 18:40:54'),
(202, 15, 2, 19.682739, -98.890843, '2026-04-16 18:40:58'),
(203, 15, 2, 19.682739, -98.890843, '2026-04-16 18:41:02'),
(204, 15, 2, 19.682739, -98.890843, '2026-04-16 18:41:06'),
(205, 15, 2, 19.682739, -98.890843, '2026-04-16 18:41:10'),
(206, 15, 2, 19.682814, -98.890808, '2026-04-17 19:15:59'),
(207, 15, 2, 19.682814, -98.890808, '2026-04-17 19:16:03'),
(208, 15, 2, 19.682814, -98.890808, '2026-04-17 19:16:07'),
(209, 15, 2, 19.682814, -98.890808, '2026-04-17 19:16:11'),
(210, 15, 2, 19.682814, -98.890808, '2026-04-17 19:16:13'),
(211, 15, 2, 19.682814, -98.890808, '2026-04-17 19:16:17'),
(212, 15, 2, 19.682814, -98.890808, '2026-04-17 19:16:21'),
(213, 15, 2, 19.682814, -98.890808, '2026-04-17 19:16:25'),
(214, 15, 2, 19.682814, -98.890808, '2026-04-17 19:16:29'),
(215, 15, 2, 19.682814, -98.890808, '2026-04-17 19:16:33'),
(216, 15, 2, 19.682814, -98.890808, '2026-04-17 19:16:37'),
(217, 15, 2, 19.682814, -98.890808, '2026-04-17 19:16:41'),
(218, 15, 2, 19.682813, -98.890807, '2026-04-17 19:16:45'),
(219, 15, 2, 19.682813, -98.890807, '2026-04-17 19:16:49'),
(220, 15, 2, 19.682813, -98.890807, '2026-04-17 19:16:53'),
(221, 15, 2, 19.682813, -98.890807, '2026-04-17 19:16:57'),
(222, 15, 2, 19.682813, -98.890807, '2026-04-17 19:17:01'),
(223, 15, 2, 19.682813, -98.890807, '2026-04-17 19:17:05'),
(224, 15, 2, 19.682813, -98.890807, '2026-04-17 19:17:09'),
(225, 15, 2, 19.682813, -98.890807, '2026-04-17 19:17:13'),
(226, 15, 2, 19.682813, -98.890807, '2026-04-17 19:17:17'),
(227, 15, 2, 19.682824, -98.890817, '2026-04-17 19:17:21'),
(228, 15, 2, 19.682824, -98.890817, '2026-04-17 19:17:25'),
(229, 15, 2, 19.682824, -98.890817, '2026-04-17 19:17:29'),
(230, 15, 2, 19.682824, -98.890817, '2026-04-17 19:17:33'),
(231, 15, 2, 19.682824, -98.890817, '2026-04-17 19:17:37'),
(232, 15, 2, 19.682824, -98.890817, '2026-04-17 19:17:41'),
(233, 15, 2, 19.682824, -98.890817, '2026-04-17 19:17:45'),
(234, 15, 2, 19.682824, -98.890817, '2026-04-17 19:17:49'),
(235, 15, 2, 19.682824, -98.890817, '2026-04-17 19:17:53'),
(236, 15, 2, 19.682459, -98.89093, '2026-04-17 19:17:57'),
(237, 15, 2, 19.682459, -98.89093, '2026-04-17 19:18:01'),
(238, 15, 2, 19.682459, -98.89093, '2026-04-17 19:18:05'),
(239, 15, 2, 19.682459, -98.89093, '2026-04-17 19:18:09'),
(240, 15, 2, 19.682459, -98.89093, '2026-04-17 19:18:13'),
(241, 15, 2, 19.682459, -98.89093, '2026-04-17 19:18:17'),
(242, 15, 2, 19.682459, -98.89093, '2026-04-17 19:18:21'),
(243, 15, 2, 19.682459, -98.89093, '2026-04-17 19:18:25'),
(244, 15, 2, 19.682459, -98.89093, '2026-04-17 19:18:29'),
(245, 15, 2, 19.682459, -98.89093, '2026-04-17 19:18:33'),
(246, 15, 2, 19.682459, -98.89093, '2026-04-17 19:18:37'),
(247, 15, 2, 19.682459, -98.89093, '2026-04-17 19:18:41'),
(248, 15, 2, 19.682459, -98.89093, '2026-04-17 19:18:45'),
(249, 15, 2, 19.682459, -98.89093, '2026-04-17 19:18:49'),
(250, 15, 2, 19.682459, -98.89093, '2026-04-17 19:18:53'),
(251, 15, 2, 19.682461, -98.890923, '2026-04-17 19:18:57'),
(252, 15, 2, 19.682461, -98.890923, '2026-04-17 19:19:01'),
(253, 15, 2, 19.682461, -98.890923, '2026-04-17 19:19:05'),
(254, 15, 2, 19.682459, -98.89093, '2026-04-17 19:19:09'),
(255, 15, 2, 19.682459, -98.89093, '2026-04-17 19:19:13'),
(256, 15, 2, 19.682459, -98.89093, '2026-04-17 19:19:17'),
(257, 15, 2, 19.682459, -98.89093, '2026-04-17 19:19:21'),
(258, 15, 2, 19.682459, -98.89093, '2026-04-17 19:19:25'),
(259, 15, 2, 19.682459, -98.89093, '2026-04-17 19:19:29'),
(260, 15, 2, 19.682459, -98.89093, '2026-04-17 19:19:33'),
(261, 15, 2, 19.682459, -98.89093, '2026-04-17 19:19:36'),
(262, 15, 2, 19.682459, -98.89093, '2026-04-17 19:19:38'),
(263, 15, 2, 19.682459, -98.89093, '2026-04-17 19:19:39'),
(264, 15, 2, 19.682459, -98.89093, '2026-04-17 19:19:40'),
(265, 15, 2, 19.682459, -98.89093, '2026-04-17 19:19:41'),
(266, 15, 2, 19.682459, -98.89093, '2026-04-17 19:19:41'),
(267, 15, 2, 19.682459, -98.89093, '2026-04-17 19:19:43'),
(268, 15, 2, 19.682459, -98.89093, '2026-04-17 19:19:44'),
(269, 15, 2, 19.682459, -98.89093, '2026-04-17 19:19:45'),
(270, 15, 2, 19.682459, -98.89093, '2026-04-17 19:19:46'),
(271, 15, 2, 19.682459, -98.89093, '2026-04-17 19:19:46'),
(272, 15, 2, 19.682459, -98.89093, '2026-04-17 19:19:47'),
(273, 15, 2, 19.682459, -98.89093, '2026-04-17 19:19:48'),
(274, 15, 2, 19.682459, -98.89093, '2026-04-17 19:19:48'),
(275, 15, 2, 19.682459, -98.89093, '2026-04-17 19:19:49'),
(276, 15, 2, 19.682459, -98.89093, '2026-04-17 19:19:49'),
(277, 15, 2, 19.682459, -98.89093, '2026-04-17 19:19:50'),
(278, 15, 2, 19.682459, -98.89093, '2026-04-17 19:19:50'),
(279, 15, 2, 19.682459, -98.89093, '2026-04-17 19:19:51'),
(280, 15, 2, 19.682459, -98.89093, '2026-04-17 19:19:55'),
(281, 15, 2, 19.682459, -98.89093, '2026-04-17 19:19:59'),
(282, 15, 2, 19.682459, -98.89093, '2026-04-17 19:20:03'),
(283, 15, 2, 19.682461, -98.890915, '2026-04-17 19:20:07'),
(284, 15, 2, 19.682461, -98.890915, '2026-04-17 19:20:11'),
(285, 15, 2, 19.682461, -98.890915, '2026-04-17 19:20:15'),
(286, 15, 2, 19.682461, -98.890915, '2026-04-17 19:20:19'),
(287, 15, 2, 19.682461, -98.890915, '2026-04-17 19:20:23'),
(288, 15, 2, 19.682461, -98.890915, '2026-04-17 19:20:27'),
(289, 15, 2, 19.682461, -98.890915, '2026-04-17 19:20:28'),
(290, 15, 2, 19.682815, -98.890809, '2026-04-17 19:20:29'),
(291, 15, 2, 19.682815, -98.890809, '2026-04-17 19:20:30'),
(292, 15, 2, 19.682815, -98.890809, '2026-04-17 19:20:31'),
(293, 15, 2, 19.682815, -98.890809, '2026-04-17 19:20:35'),
(294, 15, 2, 19.682815, -98.890809, '2026-04-17 19:20:38'),
(295, 16, 2, 19.3621, -99.2405, '2026-04-20 14:55:00'),
(296, 16, 2, 19.3621, -99.2405, '2026-04-20 14:55:24'),
(297, 16, 2, 19.3621, -99.2405, '2026-04-20 14:55:33'),
(298, 16, 2, 19.3621, -99.2405, '2026-04-20 14:55:36'),
(299, 16, 2, 19.3621, -99.2405, '2026-04-20 14:55:40'),
(300, 16, 2, 19.3621, -99.2405, '2026-04-20 14:56:15'),
(301, 16, 2, 19.3621, -99.2405, '2026-04-20 14:56:19'),
(302, 16, 2, 19.3621, -99.2405, '2026-04-20 14:56:23'),
(303, 16, 2, 19.3621, -99.2405, '2026-04-20 14:56:27'),
(304, 16, 2, 19.3621, -99.2405, '2026-04-20 14:56:31'),
(305, 16, 2, 19.3621, -99.2405, '2026-04-20 14:56:35'),
(306, 16, 2, 19.3621, -99.2405, '2026-04-20 14:56:39'),
(307, 16, 2, 19.3621, -99.2405, '2026-04-20 14:56:43'),
(308, 16, 2, 19.3621, -99.2405, '2026-04-20 14:56:47'),
(309, 16, 2, 19.3621, -99.2405, '2026-04-20 14:56:51'),
(310, 16, 2, 19.3621, -99.2405, '2026-04-20 14:56:55'),
(311, 16, 2, 19.3621, -99.2405, '2026-04-20 14:56:59'),
(312, 16, 2, 19.3621, -99.2405, '2026-04-20 14:57:03'),
(313, 16, 2, 19.3621, -99.2405, '2026-04-20 14:57:07'),
(314, 16, 2, 19.3621, -99.2405, '2026-04-20 14:57:11'),
(315, 16, 2, 19.3621, -99.2405, '2026-04-20 14:57:15'),
(316, 16, 2, 19.3621, -99.2405, '2026-04-20 14:57:19'),
(317, 16, 2, 19.3621, -99.2405, '2026-04-20 14:57:23'),
(318, 16, 2, 19.3621, -99.2405, '2026-04-20 14:57:27'),
(319, 16, 2, 19.3621, -99.2405, '2026-04-20 14:57:31'),
(320, 16, 2, 19.3621, -99.2405, '2026-04-20 14:57:35'),
(321, 16, 2, 19.3621, -99.2405, '2026-04-20 14:57:39'),
(322, 16, 2, 19.3621, -99.2405, '2026-04-20 14:57:43'),
(323, 16, 2, 19.3621, -99.2405, '2026-04-20 14:57:47'),
(324, 16, 2, 19.3621, -99.2405, '2026-04-20 14:57:51'),
(325, 16, 2, 19.3621, -99.2405, '2026-04-20 14:57:55'),
(326, 16, 2, 19.3621, -99.2405, '2026-04-20 14:57:59'),
(327, 16, 2, 19.3621, -99.2405, '2026-04-20 14:58:03'),
(328, 16, 2, 19.3621, -99.2405, '2026-04-20 14:58:07'),
(329, 16, 2, 19.3621, -99.2405, '2026-04-20 14:58:11'),
(330, 16, 2, 19.3621, -99.2405, '2026-04-20 14:58:15'),
(331, 16, 2, 19.3621, -99.2405, '2026-04-20 14:58:19'),
(332, 16, 2, 19.3621, -99.2405, '2026-04-20 14:58:23'),
(333, 16, 2, 19.3621, -99.2405, '2026-04-20 14:58:27'),
(334, 16, 2, 19.3621, -99.2405, '2026-04-20 14:58:31'),
(335, 16, 2, 19.3621, -99.2405, '2026-04-20 14:58:35'),
(336, 16, 2, 19.3621, -99.2405, '2026-04-20 14:58:39'),
(337, 16, 2, 19.3621, -99.2405, '2026-04-20 14:58:43'),
(338, 16, 2, 19.3621, -99.2405, '2026-04-20 14:58:47'),
(339, 16, 2, 19.3621, -99.2405, '2026-04-20 14:58:51'),
(340, 16, 2, 19.3621, -99.2405, '2026-04-20 14:58:55'),
(341, 16, 2, 19.3621, -99.2405, '2026-04-20 14:58:59'),
(342, 16, 2, 19.3621, -99.2405, '2026-04-20 14:59:03'),
(343, 16, 2, 19.3621, -99.2405, '2026-04-20 14:59:07'),
(344, 16, 2, 19.3621, -99.2405, '2026-04-20 14:59:11'),
(345, 16, 2, 19.3621, -99.2405, '2026-04-20 14:59:15'),
(346, 16, 2, 19.3621, -99.2405, '2026-04-20 14:59:19'),
(347, 16, 2, 19.3621, -99.2405, '2026-04-20 14:59:23'),
(348, 16, 2, 19.3621, -99.2405, '2026-04-20 14:59:27'),
(349, 16, 2, 19.3621, -99.2405, '2026-04-20 14:59:31'),
(350, 16, 2, 19.3621, -99.2405, '2026-04-20 14:59:35'),
(351, 16, 2, 19.3621, -99.2405, '2026-04-20 14:59:39'),
(352, 16, 2, 19.3621, -99.2405, '2026-04-20 14:59:43'),
(353, 16, 2, 19.3621, -99.2405, '2026-04-20 14:59:47'),
(354, 16, 2, 19.3621, -99.2405, '2026-04-20 14:59:51'),
(355, 16, 2, 19.3621, -99.2405, '2026-04-20 14:59:55'),
(356, 16, 2, 19.3621, -99.2405, '2026-04-20 14:59:59'),
(357, 16, 2, 19.3621, -99.2405, '2026-04-20 15:00:04'),
(358, 16, 2, 19.3621, -99.2405, '2026-04-20 15:00:07'),
(359, 16, 2, 19.3621, -99.2405, '2026-04-20 15:00:11'),
(360, 16, 2, 19.3621, -99.2405, '2026-04-20 15:00:15'),
(361, 16, 2, 19.3621, -99.2405, '2026-04-20 15:00:19'),
(362, 16, 2, 19.3621, -99.2405, '2026-04-20 15:00:23'),
(363, 16, 2, 19.3621, -99.2405, '2026-04-20 15:00:27'),
(364, 16, 2, 19.3621, -99.2405, '2026-04-20 15:00:31'),
(365, 16, 2, 19.3621, -99.2405, '2026-04-20 15:00:35'),
(366, 16, 2, 19.3621, -99.2405, '2026-04-20 15:00:39'),
(367, 16, 2, 19.3621, -99.2405, '2026-04-20 15:00:43'),
(368, 16, 2, 19.3621, -99.2405, '2026-04-20 15:00:47'),
(369, 16, 2, 19.3621, -99.2405, '2026-04-20 15:00:51'),
(370, 16, 2, 19.3621, -99.2405, '2026-04-20 15:00:55'),
(371, 16, 2, 19.3621, -99.2405, '2026-04-20 15:00:59'),
(372, 16, 2, 19.3621, -99.2405, '2026-04-20 15:01:03'),
(373, 16, 2, 19.3621, -99.2405, '2026-04-20 15:01:07'),
(374, 16, 2, 19.3621, -99.2405, '2026-04-20 15:01:11'),
(375, 16, 2, 19.3621, -99.2405, '2026-04-20 15:01:15'),
(376, 16, 2, 19.3621, -99.2405, '2026-04-20 15:01:19'),
(377, 16, 2, 19.3621, -99.2405, '2026-04-20 15:01:23'),
(378, 16, 2, 19.3621, -99.2405, '2026-04-20 15:01:27'),
(379, 16, 2, 19.3621, -99.2405, '2026-04-20 15:01:31'),
(380, 16, 2, 19.3621, -99.2405, '2026-04-20 15:01:35'),
(381, 16, 2, 19.3621, -99.2405, '2026-04-20 15:01:39'),
(382, 16, 2, 19.3621, -99.2405, '2026-04-20 15:01:43'),
(383, 16, 2, 19.3621, -99.2405, '2026-04-20 15:01:47'),
(384, 16, 2, 19.3621, -99.2405, '2026-04-20 15:01:51'),
(385, 16, 2, 19.3621, -99.2405, '2026-04-20 15:01:55'),
(386, 16, 2, 19.3621, -99.2405, '2026-04-20 15:01:59'),
(387, 16, 2, 19.3621, -99.2405, '2026-04-20 15:02:03'),
(388, 16, 2, 19.3621, -99.2405, '2026-04-20 15:02:07'),
(389, 16, 2, 19.3621, -99.2405, '2026-04-20 15:02:11'),
(390, 16, 2, 19.3621, -99.2405, '2026-04-20 15:02:15'),
(391, 16, 2, 19.3621, -99.2405, '2026-04-20 15:02:28'),
(392, 16, 2, 19.3621, -99.2405, '2026-04-20 15:02:32'),
(393, 16, 2, 19.3621, -99.2405, '2026-04-20 15:02:36'),
(394, 16, 2, 19.3621, -99.2405, '2026-04-20 15:02:40'),
(395, 16, 2, 19.3621, -99.2405, '2026-04-20 15:03:47'),
(396, 16, 2, 19.3621, -99.2405, '2026-04-20 15:03:51'),
(397, 16, 2, 19.3621, -99.2405, '2026-04-20 15:03:55'),
(398, 16, 2, 19.682438, -98.891014, '2026-04-22 19:34:55'),
(399, 16, 2, 19.682438, -98.891014, '2026-04-22 19:35:02'),
(400, 16, 2, 19.682438, -98.891014, '2026-04-22 19:35:05'),
(401, 16, 2, 19.682438, -98.891014, '2026-04-22 19:35:07'),
(402, 16, 2, 19.682438, -98.891014, '2026-04-22 19:35:10'),
(403, 16, 2, 19.682438, -98.891014, '2026-04-22 19:35:12'),
(404, 16, 2, 19.682438, -98.891014, '2026-04-22 19:35:14'),
(405, 16, 2, 19.682438, -98.891014, '2026-04-22 19:35:18'),
(406, 16, 2, 19.682438, -98.891014, '2026-04-22 19:35:22'),
(407, 16, 2, 19.682438, -98.891014, '2026-04-22 19:35:26'),
(408, 16, 2, 19.682438, -98.891014, '2026-04-22 19:35:30'),
(409, 16, 2, 19.682438, -98.891014, '2026-04-22 19:35:35'),
(410, 16, 2, 19.682438, -98.891014, '2026-04-22 19:35:39'),
(411, 16, 2, 19.682438, -98.891014, '2026-04-22 19:35:43'),
(412, 16, 2, 19.682438, -98.891014, '2026-04-22 19:35:51'),
(413, 16, 2, 19.682438, -98.891014, '2026-04-22 19:35:55'),
(414, 16, 2, 19.682438, -98.891014, '2026-04-22 19:35:59'),
(415, 16, 2, 19.682438, -98.891014, '2026-04-22 19:36:02'),
(416, 16, 2, 19.682438, -98.891014, '2026-04-22 19:36:06'),
(417, 16, 2, 19.682438, -98.891014, '2026-04-22 19:36:10'),
(418, 16, 2, 19.682438, -98.891014, '2026-04-22 19:36:14'),
(419, 16, 2, 19.682438, -98.891014, '2026-04-22 19:36:18'),
(420, 16, 2, 19.682438, -98.891014, '2026-04-22 19:36:22'),
(421, 16, 2, 19.682438, -98.891014, '2026-04-22 19:36:26'),
(422, 16, 2, 19.682438, -98.891014, '2026-04-22 19:36:30'),
(423, 16, 2, 19.682438, -98.891014, '2026-04-22 19:36:34'),
(424, 16, 2, 19.682438, -98.891014, '2026-04-22 19:36:38'),
(425, 16, 2, 19.682438, -98.891014, '2026-04-22 19:36:42'),
(426, 16, 2, 19.682438, -98.891014, '2026-04-22 19:36:46'),
(427, 16, 2, 19.682438, -98.891014, '2026-04-22 19:36:49'),
(428, 16, 2, 19.682438, -98.891014, '2026-04-22 19:36:51'),
(429, 16, 2, 19.682438, -98.891014, '2026-04-22 19:36:55'),
(430, 16, 2, 19.682438, -98.891014, '2026-04-22 19:36:59'),
(431, 16, 2, 19.682438, -98.891014, '2026-04-22 19:37:03'),
(432, 16, 2, 19.682438, -98.891014, '2026-04-22 19:37:07'),
(433, 16, 2, 19.682438, -98.891014, '2026-04-22 19:37:11'),
(434, 16, 2, 19.682438, -98.891014, '2026-04-22 19:37:15'),
(435, 16, 2, 19.682438, -98.891014, '2026-04-22 19:37:19'),
(436, 16, 2, 19.682438, -98.891014, '2026-04-22 19:37:21'),
(437, 16, 2, 19.682438, -98.891014, '2026-04-22 19:37:24'),
(438, 16, 2, 19.682438, -98.891014, '2026-04-22 19:37:28'),
(439, 16, 2, 19.682438, -98.891014, '2026-04-22 19:37:32'),
(440, 16, 2, 19.682438, -98.891014, '2026-04-22 19:37:36'),
(441, 16, 2, 19.682438, -98.891014, '2026-04-22 19:37:40'),
(442, 16, 2, 19.682438, -98.891014, '2026-04-22 19:37:45'),
(443, 16, 2, 19.682438, -98.891014, '2026-04-22 19:37:49'),
(444, 16, 2, 19.682438, -98.891014, '2026-04-22 19:37:52'),
(445, 16, 2, 19.682438, -98.891014, '2026-04-22 19:37:57'),
(446, 16, 2, 19.682438, -98.891014, '2026-04-22 19:38:01'),
(447, 16, 2, 19.682438, -98.891014, '2026-04-22 19:38:04'),
(448, 16, 2, 19.682438, -98.891014, '2026-04-22 19:38:08'),
(449, 16, 2, 19.682438, -98.891014, '2026-04-22 19:38:12'),
(450, 16, 2, 19.682438, -98.891014, '2026-04-22 19:38:16'),
(451, 16, 2, 19.682438, -98.891014, '2026-04-22 19:38:20'),
(452, 16, 2, 19.682438, -98.891014, '2026-04-22 19:38:25'),
(453, 16, 2, 19.682438, -98.891014, '2026-04-22 19:38:28'),
(454, 16, 2, 19.682438, -98.891014, '2026-04-22 19:38:33'),
(455, 16, 2, 19.682438, -98.891014, '2026-04-22 19:38:36'),
(456, 16, 2, 19.682438, -98.891014, '2026-04-22 19:38:40'),
(457, 16, 2, 19.682438, -98.891014, '2026-04-22 19:38:45'),
(458, 16, 2, 19.682438, -98.891014, '2026-04-22 19:38:48'),
(459, 16, 2, 19.682438, -98.891014, '2026-04-22 19:38:52'),
(460, 16, 2, 19.682438, -98.891014, '2026-04-22 19:38:56'),
(461, 16, 2, 19.682438, -98.891014, '2026-04-22 19:39:01'),
(462, 16, 2, 19.682438, -98.891014, '2026-04-22 19:39:04'),
(463, 16, 2, 19.682438, -98.891014, '2026-04-22 19:39:09'),
(464, 16, 2, 19.682438, -98.891014, '2026-04-22 19:39:13'),
(465, 16, 2, 19.682438, -98.891014, '2026-04-22 19:39:16'),
(466, 16, 2, 19.682438, -98.891014, '2026-04-22 19:39:20'),
(467, 16, 2, 19.682438, -98.891014, '2026-04-22 19:39:24'),
(468, 16, 2, 19.682438, -98.891014, '2026-04-22 19:39:28'),
(469, 16, 2, 19.682438, -98.891014, '2026-04-22 19:39:32'),
(470, 16, 2, 19.682438, -98.891014, '2026-04-22 19:39:36'),
(471, 16, 2, 19.682438, -98.891014, '2026-04-22 19:39:41'),
(472, 16, 2, 19.682438, -98.891014, '2026-04-22 19:39:45'),
(473, 16, 2, 19.682438, -98.891014, '2026-04-22 19:39:48'),
(474, 16, 2, 19.682438, -98.891014, '2026-04-22 19:39:53'),
(475, 16, 2, 19.682438, -98.891014, '2026-04-22 19:39:57'),
(476, 16, 2, 19.682438, -98.891014, '2026-04-22 19:40:00'),
(477, 16, 2, 19.682438, -98.891014, '2026-04-22 19:40:05'),
(478, 16, 2, 19.682438, -98.891014, '2026-04-22 19:40:09'),
(479, 16, 2, 19.682438, -98.891014, '2026-04-22 19:40:12'),
(480, 16, 2, 19.682438, -98.891014, '2026-04-22 19:40:16'),
(481, 16, 2, 19.682789, -98.890785, '2026-04-22 19:40:20'),
(482, 16, 2, 19.682789, -98.890785, '2026-04-22 19:40:24'),
(483, 16, 2, 19.682789, -98.890785, '2026-04-22 19:40:26'),
(484, 16, 2, 19.682789, -98.890785, '2026-04-22 19:40:30'),
(485, 16, 2, 19.682789, -98.890785, '2026-04-22 19:40:34'),
(486, 16, 2, 19.682789, -98.890785, '2026-04-22 19:40:38'),
(487, 16, 2, 19.682789, -98.890785, '2026-04-22 19:40:42'),
(488, 16, 2, 19.682789, -98.890785, '2026-04-22 19:40:46'),
(489, 16, 2, 19.682789, -98.890785, '2026-04-22 19:40:50'),
(490, 16, 2, 19.682789, -98.890785, '2026-04-22 19:40:54'),
(491, 16, 2, 19.682789, -98.890785, '2026-04-22 19:40:59'),
(492, 16, 2, 19.682789, -98.890785, '2026-04-22 19:41:02'),
(493, 16, 2, 19.682789, -98.890785, '2026-04-22 19:41:06'),
(494, 16, 2, 19.682789, -98.890785, '2026-04-22 19:41:10'),
(495, 16, 2, 19.682789, -98.890785, '2026-04-22 19:41:14'),
(496, 16, 2, 19.682789, -98.890785, '2026-04-22 19:41:18'),
(497, 16, 2, 19.682789, -98.890785, '2026-04-22 19:41:22'),
(498, 16, 2, 19.682789, -98.890785, '2026-04-22 19:41:26'),
(499, 16, 2, 19.682789, -98.890785, '2026-04-22 19:41:30'),
(500, 16, 2, 19.682789, -98.890785, '2026-04-22 19:41:34'),
(501, 16, 2, 19.682789, -98.890785, '2026-04-22 19:41:38'),
(502, 16, 2, 19.682438, -98.891014, '2026-04-22 19:41:42'),
(503, 16, 2, 19.682438, -98.891014, '2026-04-22 19:41:42'),
(504, 16, 2, 19.682438, -98.891014, '2026-04-22 19:41:46'),
(505, 16, 2, 19.682438, -98.891014, '2026-04-22 19:41:50'),
(506, 16, 2, 19.682438, -98.891014, '2026-04-22 19:41:54'),
(507, 16, 2, 19.682438, -98.891014, '2026-04-22 19:41:58'),
(508, 16, 2, 19.682438, -98.891014, '2026-04-22 19:42:02'),
(509, 16, 2, 19.682438, -98.891014, '2026-04-22 19:42:06'),
(510, 16, 2, 19.682438, -98.891014, '2026-04-22 19:42:10'),
(511, 16, 2, 19.682438, -98.891014, '2026-04-22 19:42:14'),
(512, 16, 2, 19.682436, -98.890991, '2026-04-22 19:42:18'),
(513, 16, 2, 19.682436, -98.890991, '2026-04-22 19:42:22'),
(514, 16, 2, 19.682436, -98.890991, '2026-04-22 19:42:26'),
(515, 16, 2, 19.682451, -98.890943, '2026-04-22 19:42:30'),
(516, 16, 2, 19.682451, -98.890943, '2026-04-22 19:42:34'),
(517, 16, 2, 19.682451, -98.890943, '2026-04-22 19:42:38'),
(518, 16, 2, 19.682451, -98.890943, '2026-04-22 19:42:42'),
(519, 16, 2, 19.682451, -98.890943, '2026-04-22 19:42:46'),
(520, 16, 2, 19.682451, -98.890943, '2026-04-22 19:42:47'),
(521, 16, 2, 19.682451, -98.890943, '2026-04-22 19:42:48'),
(522, 16, 2, 19.682451, -98.890943, '2026-04-22 19:42:52'),
(523, 16, 2, 19.682451, -98.890943, '2026-04-22 19:42:56'),
(524, 16, 2, 19.682451, -98.890943, '2026-04-22 19:43:00'),
(525, 16, 2, 19.682451, -98.890943, '2026-04-22 19:43:03'),
(526, 16, 2, 19.682451, -98.890943, '2026-04-22 19:43:07'),
(527, 16, 2, 19.682438, -98.891014, '2026-04-22 19:45:26'),
(528, 16, 2, 19.682438, -98.891014, '2026-04-22 19:45:30'),
(529, 16, 2, 19.682438, -98.891014, '2026-04-22 19:45:34'),
(530, 16, 2, 19.682438, -98.891014, '2026-04-22 19:45:38'),
(531, 16, 2, 19.682438, -98.891014, '2026-04-22 19:45:42'),
(532, 16, 2, 19.682438, -98.891014, '2026-04-22 19:45:45'),
(533, 16, 2, 19.682438, -98.891014, '2026-04-22 19:45:49'),
(534, 16, 2, 19.682438, -98.891014, '2026-04-22 19:45:53'),
(535, 16, 2, 19.682438, -98.891014, '2026-04-22 19:45:57'),
(536, 16, 2, 19.682438, -98.891014, '2026-04-22 19:46:00'),
(537, 16, 2, 19.682438, -98.891014, '2026-04-22 19:46:01'),
(538, 16, 2, 19.682438, -98.891014, '2026-04-22 19:46:04'),
(539, 16, 2, 19.682438, -98.891014, '2026-04-22 19:46:08'),
(540, 16, 2, 19.682438, -98.891014, '2026-04-22 19:46:12'),
(541, 16, 2, 19.682438, -98.891014, '2026-04-22 19:46:16'),
(542, 16, 2, 19.682438, -98.891014, '2026-04-22 19:46:20'),
(543, 16, 2, 19.682438, -98.891014, '2026-04-22 19:46:24'),
(544, 16, 2, 19.682438, -98.891014, '2026-04-22 19:46:28'),
(545, 16, 2, 19.682438, -98.891014, '2026-04-22 19:46:32'),
(546, 16, 2, 19.682438, -98.891014, '2026-04-22 19:46:36'),
(547, 16, 2, 19.682438, -98.891014, '2026-04-22 19:46:40'),
(548, 16, 2, 19.682438, -98.891014, '2026-04-22 19:46:44'),
(549, 16, 2, 19.682438, -98.891014, '2026-04-22 19:46:48'),
(550, 16, 2, 19.682438, -98.891014, '2026-04-22 19:46:52'),
(551, 16, 2, 19.682438, -98.891014, '2026-04-22 19:46:56'),
(552, 16, 2, 19.682438, -98.891014, '2026-04-22 19:47:00'),
(553, 16, 2, 19.682438, -98.891014, '2026-04-22 19:47:04'),
(554, 16, 2, 19.682438, -98.891014, '2026-04-22 19:47:08'),
(555, 16, 2, 19.682438, -98.891014, '2026-04-22 19:47:11'),
(556, 16, 2, 19.682438, -98.891014, '2026-04-22 19:47:15'),
(557, 16, 2, 19.682438, -98.891014, '2026-04-22 19:47:19'),
(558, 16, 2, 19.682438, -98.891014, '2026-04-22 19:47:22'),
(559, 16, 2, 19.682438, -98.891014, '2026-04-22 19:47:25'),
(560, 16, 2, 19.682438, -98.891014, '2026-04-22 19:47:29'),
(561, 16, 2, 19.682438, -98.891014, '2026-04-22 19:47:33'),
(562, 16, 2, 19.682438, -98.891014, '2026-04-22 19:47:37'),
(563, 16, 2, 19.682438, -98.891014, '2026-04-22 19:47:40'),
(564, 16, 2, 19.682438, -98.891014, '2026-04-22 19:47:42'),
(565, 16, 2, 19.682438, -98.891014, '2026-04-22 19:47:46'),
(566, 16, 2, 19.682438, -98.891014, '2026-04-22 19:47:50'),
(567, 16, 2, 19.682438, -98.891014, '2026-04-22 19:47:54'),
(568, 16, 2, 19.682438, -98.891014, '2026-04-22 19:47:58'),
(569, 16, 2, 19.682438, -98.891014, '2026-04-22 19:48:02'),
(570, 16, 2, 19.682438, -98.891014, '2026-04-22 19:48:06'),
(571, 16, 2, 19.682438, -98.891014, '2026-04-22 19:48:07'),
(572, 16, 2, 19.682438, -98.891014, '2026-04-22 19:48:11'),
(573, 16, 2, 19.682438, -98.891014, '2026-04-22 19:48:15'),
(574, 16, 2, 19.682438, -98.891014, '2026-04-22 19:48:19'),
(575, 16, 2, 19.682438, -98.891014, '2026-04-22 19:48:24'),
(576, 16, 2, 19.682438, -98.891014, '2026-04-22 19:48:27'),
(577, 16, 2, 19.682438, -98.891014, '2026-04-22 19:48:31'),
(578, 16, 2, 19.682451, -98.890938, '2026-04-22 19:48:35'),
(579, 16, 2, 19.682451, -98.890938, '2026-04-22 19:48:39'),
(580, 16, 2, 19.682451, -98.890938, '2026-04-22 19:48:43'),
(581, 16, 2, 19.682451, -98.890938, '2026-04-22 19:48:47'),
(582, 16, 2, 19.682451, -98.890938, '2026-04-22 19:48:51'),
(583, 16, 2, 19.682438, -98.891014, '2026-04-22 19:50:09'),
(584, 16, 2, 19.682438, -98.891014, '2026-04-22 19:50:11'),
(585, 16, 2, 19.682438, -98.891014, '2026-04-22 19:50:15'),
(586, 16, 2, 19.682739, -98.890813, '2026-04-22 19:50:19'),
(587, 16, 2, 19.682739, -98.890813, '2026-04-22 19:50:23'),
(588, 16, 2, 19.682739, -98.890813, '2026-04-22 19:50:27'),
(589, 16, 2, 19.682459, -98.890923, '2026-04-22 19:50:31'),
(590, 16, 2, 19.682459, -98.890923, '2026-04-22 19:50:35'),
(591, 16, 2, 19.682459, -98.890923, '2026-04-22 19:50:39'),
(592, 16, 2, 19.682459, -98.890923, '2026-04-22 19:50:43'),
(593, 16, 2, 19.682459, -98.890923, '2026-04-22 19:50:47'),
(594, 16, 2, 19.682459, -98.890923, '2026-04-22 19:50:51'),
(595, 16, 2, 19.682459, -98.890923, '2026-04-22 19:50:55'),
(596, 16, 2, 19.682459, -98.890923, '2026-04-22 19:50:59'),
(597, 16, 2, 19.682459, -98.890923, '2026-04-22 19:51:03'),
(598, 16, 2, 19.682459, -98.890923, '2026-04-22 19:51:07'),
(599, 16, 2, 19.682459, -98.890923, '2026-04-22 19:51:11'),
(600, 16, 2, 19.682459, -98.890923, '2026-04-22 19:51:15'),
(601, 16, 2, 19.682459, -98.890923, '2026-04-22 19:51:19'),
(602, 16, 2, 19.682459, -98.890923, '2026-04-22 19:51:23'),
(603, 16, 2, 19.682459, -98.890923, '2026-04-22 19:51:27'),
(604, 16, 2, 19.682459, -98.890923, '2026-04-22 19:51:31'),
(605, 16, 2, 19.682459, -98.890923, '2026-04-22 19:51:35'),
(606, 16, 2, 19.682459, -98.890923, '2026-04-22 19:51:39'),
(607, 16, 2, 19.682739, -98.890813, '2026-04-22 19:51:43'),
(608, 16, 2, 19.682739, -98.890813, '2026-04-22 19:51:47'),
(609, 16, 2, 19.682739, -98.890813, '2026-04-22 19:51:51'),
(610, 16, 2, 19.682739, -98.890813, '2026-04-22 19:51:55'),
(611, 16, 2, 19.682739, -98.890813, '2026-04-22 19:51:59'),
(612, 16, 2, 19.682739, -98.890813, '2026-04-22 19:52:03'),
(613, 16, 2, 19.682739, -98.890813, '2026-04-22 19:52:07'),
(614, 16, 2, 19.682739, -98.890813, '2026-04-22 19:52:11'),
(615, 16, 2, 19.682739, -98.890813, '2026-04-22 19:52:15'),
(616, 16, 2, 19.682739, -98.890813, '2026-04-22 19:52:19'),
(617, 16, 2, 19.682739, -98.890813, '2026-04-22 19:52:23'),
(618, 16, 2, 19.682739, -98.890813, '2026-04-22 19:52:27'),
(619, 16, 2, 19.682453, -98.89093, '2026-04-22 19:52:31'),
(620, 16, 2, 19.682453, -98.89093, '2026-04-22 19:52:35'),
(621, 16, 2, 19.682453, -98.89093, '2026-04-22 19:52:39'),
(622, 16, 2, 19.682453, -98.89093, '2026-04-22 19:52:43'),
(623, 16, 2, 19.682453, -98.89093, '2026-04-22 19:52:47'),
(624, 16, 2, 19.682453, -98.89093, '2026-04-22 19:52:51'),
(625, 16, 2, 19.68277, -98.890796, '2026-04-22 19:52:55'),
(626, 16, 2, 19.68277, -98.890796, '2026-04-22 19:52:59'),
(627, 16, 2, 19.68277, -98.890796, '2026-04-22 19:53:03'),
(628, 16, 2, 19.68277, -98.890796, '2026-04-22 19:53:07'),
(629, 16, 2, 19.68277, -98.890796, '2026-04-22 19:53:11'),
(630, 16, 2, 19.68277, -98.890796, '2026-04-22 19:53:15'),
(631, 16, 2, 19.68277, -98.890796, '2026-04-22 19:53:19'),
(632, 16, 2, 19.68277, -98.890796, '2026-04-22 19:53:23'),
(633, 16, 2, 19.68277, -98.890796, '2026-04-22 19:53:27'),
(634, 16, 2, 19.68277, -98.890796, '2026-04-22 19:53:31'),
(635, 16, 2, 19.68277, -98.890796, '2026-04-22 19:53:35'),
(636, 16, 2, 19.68277, -98.890796, '2026-04-22 19:53:39'),
(637, 16, 2, 19.682438, -98.891014, '2026-04-22 19:53:43'),
(638, 16, 2, 19.682438, -98.891014, '2026-04-22 19:53:47'),
(639, 16, 2, 19.682438, -98.891014, '2026-04-22 19:53:51'),
(640, 16, 2, 19.682438, -98.891014, '2026-04-22 19:53:55'),
(641, 16, 2, 19.682438, -98.891014, '2026-04-22 19:53:59'),
(642, 16, 2, 19.682438, -98.891014, '2026-04-22 19:54:03'),
(643, 16, 2, 19.682438, -98.891014, '2026-04-22 19:54:07'),
(644, 16, 2, 19.682438, -98.891014, '2026-04-22 19:54:11'),
(645, 16, 2, 19.682438, -98.891014, '2026-04-22 19:54:15'),
(646, 16, 2, 19.682438, -98.891014, '2026-04-22 19:54:19'),
(647, 16, 2, 19.682438, -98.891014, '2026-04-22 19:54:23'),
(648, 16, 2, 19.682438, -98.891014, '2026-04-22 19:54:27'),
(649, 16, 2, 19.682449, -98.890938, '2026-04-22 19:54:31'),
(650, 16, 2, 19.682449, -98.890938, '2026-04-22 19:54:35'),
(651, 16, 2, 19.682449, -98.890938, '2026-04-22 19:54:39'),
(652, 16, 2, 19.682449, -98.890938, '2026-04-22 19:54:43'),
(653, 16, 2, 19.682449, -98.890938, '2026-04-22 19:54:47'),
(654, 16, 2, 19.682449, -98.890938, '2026-04-22 19:54:51'),
(655, 16, 2, 19.682449, -98.890938, '2026-04-22 19:54:55'),
(656, 16, 2, 19.682449, -98.890938, '2026-04-22 19:54:59'),
(657, 16, 2, 19.682449, -98.890938, '2026-04-22 19:55:03'),
(658, 16, 2, 19.682449, -98.890938, '2026-04-22 19:55:07'),
(659, 16, 2, 19.682449, -98.890938, '2026-04-22 19:55:11'),
(660, 16, 2, 19.682449, -98.890938, '2026-04-22 19:55:15'),
(661, 16, 2, 19.682449, -98.890938, '2026-04-22 19:55:19'),
(662, 16, 2, 19.682449, -98.890938, '2026-04-22 19:55:23'),
(663, 16, 2, 19.682449, -98.890938, '2026-04-22 19:55:27'),
(664, 16, 2, 19.682449, -98.890938, '2026-04-22 19:55:31'),
(665, 16, 2, 19.682449, -98.890938, '2026-04-22 19:55:35'),
(666, 16, 2, 19.682449, -98.890938, '2026-04-22 19:55:39'),
(667, 16, 2, 19.682438, -98.891014, '2026-04-22 19:55:43'),
(668, 16, 2, 19.682438, -98.891014, '2026-04-22 19:55:47'),
(669, 16, 2, 19.682438, -98.891014, '2026-04-22 19:55:51'),
(670, 16, 2, 19.682438, -98.891014, '2026-04-22 19:55:55'),
(671, 16, 2, 19.682438, -98.891014, '2026-04-22 19:55:59'),
(672, 16, 2, 19.682438, -98.891014, '2026-04-22 19:56:03'),
(673, 16, 2, 19.682438, -98.891014, '2026-04-22 19:56:07'),
(674, 16, 2, 19.682438, -98.891014, '2026-04-22 19:56:11'),
(675, 16, 2, 19.682438, -98.891014, '2026-04-22 19:56:15'),
(676, 16, 2, 19.682438, -98.891014, '2026-04-22 19:56:19'),
(677, 16, 2, 19.682438, -98.891014, '2026-04-22 19:56:23'),
(678, 16, 2, 19.682438, -98.891014, '2026-04-22 19:56:27'),
(679, 16, 2, 19.682438, -98.891014, '2026-04-22 19:56:31'),
(680, 16, 2, 19.682438, -98.891014, '2026-04-22 19:56:35'),
(681, 16, 2, 19.682438, -98.891014, '2026-04-22 19:56:39'),
(682, 16, 2, 19.682446, -98.890976, '2026-04-22 19:56:43'),
(683, 16, 2, 19.682446, -98.890976, '2026-04-22 19:56:47'),
(684, 16, 2, 19.682446, -98.890976, '2026-04-22 19:56:51'),
(685, 16, 2, 19.682446, -98.890976, '2026-04-22 19:56:55'),
(686, 16, 2, 19.682446, -98.890976, '2026-04-22 19:56:59'),
(687, 16, 2, 19.682446, -98.890976, '2026-04-22 19:57:03'),
(688, 16, 2, 19.682446, -98.890976, '2026-04-22 19:57:07'),
(689, 16, 2, 19.682446, -98.890976, '2026-04-22 19:57:11'),
(690, 16, 2, 19.682446, -98.890976, '2026-04-22 19:57:15'),
(691, 16, 2, 19.682446, -98.890976, '2026-04-22 19:57:19'),
(692, 16, 2, 19.682446, -98.890976, '2026-04-22 19:57:23'),
(693, 16, 2, 19.682446, -98.890976, '2026-04-22 19:57:27'),
(694, 16, 2, 19.682446, -98.890976, '2026-04-22 19:57:31'),
(695, 16, 2, 19.682446, -98.890976, '2026-04-22 19:57:35'),
(696, 16, 2, 19.682446, -98.890976, '2026-04-22 19:57:39'),
(697, 16, 2, 19.682446, -98.890976, '2026-04-22 19:57:43'),
(698, 16, 2, 19.682446, -98.890976, '2026-04-22 19:57:47'),
(699, 16, 2, 19.682446, -98.890976, '2026-04-22 19:57:51'),
(700, 16, 2, 19.682446, -98.890976, '2026-04-22 19:57:55'),
(701, 16, 2, 19.682446, -98.890976, '2026-04-22 19:57:59'),
(702, 16, 2, 19.682446, -98.890976, '2026-04-22 19:58:03'),
(703, 16, 2, 19.682446, -98.890976, '2026-04-22 19:58:07'),
(704, 16, 2, 19.682446, -98.890976, '2026-04-22 19:58:11'),
(705, 16, 2, 19.682446, -98.890976, '2026-04-22 19:58:15'),
(706, 16, 2, 19.682446, -98.890976, '2026-04-22 19:58:19'),
(707, 16, 2, 19.682446, -98.890976, '2026-04-22 19:58:23'),
(708, 16, 2, 19.682446, -98.890976, '2026-04-22 19:58:27'),
(709, 16, 2, 19.682446, -98.890976, '2026-04-22 19:58:31'),
(710, 16, 2, 19.682446, -98.890976, '2026-04-22 19:58:35'),
(711, 16, 2, 19.682446, -98.890976, '2026-04-22 19:58:39'),
(712, 16, 2, 19.682446, -98.890976, '2026-04-22 19:58:43'),
(713, 16, 2, 19.682446, -98.890976, '2026-04-22 19:58:47'),
(714, 16, 2, 19.682446, -98.890976, '2026-04-22 19:58:51'),
(715, 16, 2, 19.682446, -98.890976, '2026-04-22 19:58:55'),
(716, 16, 2, 19.682446, -98.890976, '2026-04-22 19:58:59'),
(717, 16, 2, 19.682446, -98.890976, '2026-04-22 19:59:03'),
(718, 16, 2, 19.682446, -98.890976, '2026-04-22 19:59:07'),
(719, 16, 2, 19.682446, -98.890976, '2026-04-22 19:59:11'),
(720, 16, 2, 19.682446, -98.890976, '2026-04-22 19:59:15'),
(721, 16, 2, 19.682446, -98.890976, '2026-04-22 19:59:19'),
(722, 16, 2, 19.682446, -98.890976, '2026-04-22 19:59:23'),
(723, 16, 2, 19.682446, -98.890976, '2026-04-22 19:59:27'),
(724, 16, 2, 19.682438, -98.891014, '2026-04-22 19:59:31'),
(725, 16, 2, 19.682438, -98.891014, '2026-04-22 19:59:35'),
(726, 16, 2, 19.682438, -98.891014, '2026-04-22 19:59:39'),
(727, 16, 2, 19.682438, -98.891014, '2026-04-22 19:59:43'),
(728, 16, 2, 19.682438, -98.891014, '2026-04-22 19:59:47'),
(729, 16, 2, 19.682438, -98.891014, '2026-04-22 19:59:51'),
(730, 16, 2, 19.682438, -98.891014, '2026-04-22 19:59:55'),
(731, 16, 2, 19.682438, -98.891014, '2026-04-22 19:59:59'),
(732, 16, 2, 19.682438, -98.891014, '2026-04-22 20:00:03'),
(733, 16, 2, 19.682438, -98.891014, '2026-04-22 20:00:07'),
(734, 16, 2, 19.682438, -98.891014, '2026-04-22 20:00:11'),
(735, 16, 2, 19.682438, -98.891014, '2026-04-22 20:00:15'),
(736, 16, 2, 19.682438, -98.891014, '2026-04-22 20:00:19'),
(737, 16, 2, 19.682438, -98.891014, '2026-04-22 20:00:23'),
(738, 16, 2, 19.682438, -98.891014, '2026-04-22 20:00:27'),
(739, 16, 2, 19.682438, -98.891014, '2026-04-22 20:00:31'),
(740, 16, 2, 19.682438, -98.891014, '2026-04-22 20:00:35'),
(741, 16, 2, 19.682438, -98.891014, '2026-04-22 20:00:39'),
(742, 16, 2, 19.682438, -98.891014, '2026-04-22 20:00:43'),
(743, 16, 2, 19.682438, -98.891014, '2026-04-22 20:00:47'),
(744, 16, 2, 19.682438, -98.891014, '2026-04-22 20:00:51'),
(745, 16, 2, 19.682438, -98.891014, '2026-04-22 20:00:55'),
(746, 16, 2, 19.682438, -98.891014, '2026-04-22 20:00:59'),
(747, 16, 2, 19.682438, -98.891014, '2026-04-22 20:01:03'),
(748, 16, 2, 19.682438, -98.891014, '2026-04-22 20:01:07'),
(749, 16, 2, 19.682438, -98.891014, '2026-04-22 20:01:11'),
(750, 16, 2, 19.682438, -98.891014, '2026-04-22 20:01:15'),
(751, 16, 2, 19.682438, -98.891014, '2026-04-22 20:01:19'),
(752, 16, 2, 19.682438, -98.891014, '2026-04-22 20:01:23'),
(753, 16, 2, 19.682438, -98.891014, '2026-04-22 20:01:27'),
(754, 16, 2, 19.682438, -98.891014, '2026-04-22 20:01:31'),
(755, 16, 2, 19.682438, -98.891014, '2026-04-22 20:01:35'),
(756, 16, 2, 19.682438, -98.891014, '2026-04-22 20:01:39'),
(757, 16, 2, 19.682438, -98.891014, '2026-04-22 20:01:43'),
(758, 16, 2, 19.682438, -98.891014, '2026-04-22 20:01:47'),
(759, 16, 2, 19.682438, -98.891014, '2026-04-22 20:01:51'),
(760, 16, 2, 19.682438, -98.891014, '2026-04-22 20:01:55'),
(761, 16, 2, 19.682438, -98.891014, '2026-04-22 20:01:59'),
(762, 16, 2, 19.682438, -98.891014, '2026-04-22 20:02:03'),
(763, 16, 2, 19.682438, -98.891014, '2026-04-22 20:02:07'),
(764, 16, 2, 19.682438, -98.891014, '2026-04-22 20:02:11'),
(765, 16, 2, 19.682438, -98.891014, '2026-04-22 20:02:15'),
(766, 16, 2, 19.682438, -98.891014, '2026-04-22 20:02:19'),
(767, 16, 2, 19.682438, -98.891014, '2026-04-22 20:02:23'),
(768, 16, 2, 19.682438, -98.891014, '2026-04-22 20:02:27'),
(769, 16, 2, 19.682446, -98.890976, '2026-04-22 20:02:31'),
(770, 16, 2, 19.682446, -98.890976, '2026-04-22 20:02:35'),
(771, 16, 2, 19.682446, -98.890976, '2026-04-22 20:02:39'),
(772, 16, 2, 19.682446, -98.890976, '2026-04-22 20:02:43'),
(773, 16, 2, 19.682446, -98.890976, '2026-04-22 20:02:47'),
(774, 16, 2, 19.682446, -98.890976, '2026-04-22 20:02:51'),
(775, 16, 2, 19.682446, -98.890976, '2026-04-22 20:02:55'),
(776, 16, 2, 19.682446, -98.890976, '2026-04-22 20:02:59'),
(777, 16, 2, 19.682446, -98.890976, '2026-04-22 20:03:03'),
(778, 16, 2, 19.682446, -98.890976, '2026-04-22 20:03:07'),
(779, 16, 2, 19.682446, -98.890976, '2026-04-22 20:03:11'),
(780, 16, 2, 19.682446, -98.890976, '2026-04-22 20:03:15'),
(781, 16, 2, 19.682446, -98.890976, '2026-04-22 20:03:19'),
(782, 16, 2, 19.682446, -98.890976, '2026-04-22 20:03:23'),
(783, 16, 2, 19.682446, -98.890976, '2026-04-22 20:03:27'),
(784, 16, 2, 19.682446, -98.890976, '2026-04-22 20:03:31'),
(785, 16, 2, 19.682446, -98.890976, '2026-04-22 20:03:35'),
(786, 16, 2, 19.682446, -98.890976, '2026-04-22 20:03:39'),
(787, 16, 2, 19.682446, -98.890976, '2026-04-22 20:03:43'),
(788, 16, 2, 19.682446, -98.890976, '2026-04-22 20:03:47'),
(789, 16, 2, 19.682446, -98.890976, '2026-04-22 20:03:51'),
(790, 16, 2, 19.682446, -98.890976, '2026-04-22 20:03:55'),
(791, 16, 2, 19.682446, -98.890976, '2026-04-22 20:03:59'),
(792, 16, 2, 19.682446, -98.890976, '2026-04-22 20:04:03'),
(793, 16, 2, 19.682446, -98.890976, '2026-04-22 20:04:07'),
(794, 16, 2, 19.682446, -98.890976, '2026-04-22 20:04:11'),
(795, 16, 2, 19.682446, -98.890976, '2026-04-22 20:04:15'),
(796, 16, 2, 19.682446, -98.890976, '2026-04-22 20:04:19'),
(797, 16, 2, 19.682446, -98.890976, '2026-04-22 20:04:23'),
(798, 16, 2, 19.682446, -98.890976, '2026-04-22 20:04:27'),
(799, 16, 2, 19.682451, -98.890923, '2026-04-22 20:04:32'),
(800, 16, 2, 19.682451, -98.890923, '2026-04-22 20:04:35'),
(801, 16, 2, 19.682451, -98.890923, '2026-04-22 20:04:39'),
(802, 16, 2, 19.682451, -98.890923, '2026-04-22 20:04:43'),
(803, 16, 2, 19.682451, -98.890923, '2026-04-22 20:04:47'),
(804, 16, 2, 19.682451, -98.890923, '2026-04-22 20:04:51'),
(805, 16, 2, 19.682451, -98.890923, '2026-04-22 20:04:55'),
(806, 16, 2, 19.682451, -98.890923, '2026-04-22 20:04:59'),
(807, 16, 2, 19.682451, -98.890923, '2026-04-22 20:05:41'),
(808, 16, 2, 19.682451, -98.890923, '2026-04-22 20:06:41'),
(809, 16, 2, 19.682451, -98.890923, '2026-04-22 20:07:41'),
(810, 16, 2, 19.682769, -98.890788, '2026-04-22 20:08:42'),
(811, 16, 2, 19.682769, -98.890788, '2026-04-22 20:09:41'),
(812, 16, 2, 19.682451, -98.890923, '2026-04-22 20:10:42'),
(813, 16, 2, 19.682451, -98.890923, '2026-04-22 20:11:41'),
(814, 16, 2, 19.682453, -98.890938, '2026-04-22 20:12:41'),
(815, 16, 2, 19.682453, -98.890938, '2026-04-22 20:13:41'),
(816, 16, 2, 19.682446, -98.890976, '2026-04-22 20:14:41'),
(817, 16, 2, 19.682446, -98.890976, '2026-04-22 20:15:41'),
(818, 16, 2, 19.682446, -98.890976, '2026-04-22 20:16:41'),
(819, 16, 2, 19.682446, -98.890976, '2026-04-22 20:17:41'),
(820, 16, 2, 19.682446, -98.890976, '2026-04-22 20:18:41'),
(821, 16, 2, 19.682446, -98.890976, '2026-04-22 20:19:41'),
(822, 16, 2, 19.682446, -98.890976, '2026-04-22 20:20:41'),
(823, 16, 2, 19.682446, -98.890976, '2026-04-22 20:21:41'),
(824, 16, 2, 19.682453, -98.890938, '2026-04-22 20:22:42'),
(825, 16, 2, 19.682453, -98.890938, '2026-04-22 20:23:41'),
(826, 16, 2, 19.682449, -98.890938, '2026-04-22 20:24:41'),
(827, 16, 2, 19.682449, -98.890938, '2026-04-22 20:25:41'),
(828, 16, 2, 19.682446, -98.890976, '2026-04-22 20:26:41'),
(829, 16, 2, 19.682446, -98.890976, '2026-04-22 20:27:41'),
(830, 16, 2, 19.682446, -98.890976, '2026-04-22 20:28:41'),
(831, 16, 2, 19.682453, -98.890938, '2026-04-22 20:29:42'),
(832, 16, 2, 19.682453, -98.890938, '2026-04-22 20:30:41'),
(833, 16, 2, 19.682451, -98.890923, '2026-04-22 20:31:41'),
(834, 16, 2, 19.682451, -98.890923, '2026-04-22 20:32:41'),
(835, 16, 2, 19.682449, -98.890938, '2026-04-22 20:33:41'),
(836, 16, 2, 19.682449, -98.890938, '2026-04-22 20:34:41'),
(837, 16, 2, 19.682449, -98.890938, '2026-04-22 20:35:42'),
(838, 16, 2, 19.682449, -98.890938, '2026-04-22 20:36:42'),
(839, 16, 2, 19.682453, -98.890938, '2026-04-22 20:37:41'),
(840, 16, 2, 19.682453, -98.890938, '2026-04-22 20:38:41'),
(841, 16, 2, 19.682449, -98.890938, '2026-04-22 20:39:42'),
(842, 16, 2, 19.682449, -98.890938, '2026-04-22 20:40:41'),
(843, 16, 2, 19.682453, -98.890938, '2026-04-22 20:41:41'),
(844, 16, 2, 19.682453, -98.890938, '2026-04-22 20:42:42'),
(845, 16, 2, 19.682446, -98.890976, '2026-04-22 20:43:42'),
(846, 16, 2, 19.682446, -98.890976, '2026-04-22 20:44:42'),
(847, 16, 2, 19.682446, -98.890976, '2026-04-22 20:45:42'),
(848, 16, 2, 19.682446, -98.890976, '2026-04-22 20:46:42'),
(849, 16, 2, 19.682446, -98.890976, '2026-04-22 20:47:42'),
(850, 16, 2, 19.682446, -98.890976, '2026-04-22 20:48:42'),
(851, 16, 2, 19.682453, -98.890938, '2026-04-22 20:49:41'),
(852, 16, 2, 19.682446, -98.890976, '2026-04-22 20:50:42'),
(853, 16, 2, 19.682453, -98.890938, '2026-04-22 20:51:42'),
(854, 16, 2, 19.682446, -98.890976, '2026-04-22 20:52:42'),
(855, 16, 2, 19.682449, -98.890938, '2026-04-22 20:53:41'),
(856, 16, 2, 19.682446, -98.890976, '2026-04-22 20:54:41'),
(857, 16, 2, 19.682449, -98.890938, '2026-04-22 20:55:41'),
(858, 16, 2, 19.682446, -98.890976, '2026-04-22 20:56:41'),
(859, 16, 2, 19.682449, -98.890938, '2026-04-22 20:57:41'),
(860, 16, 2, 19.682446, -98.890976, '2026-04-22 20:58:42');
INSERT INTO `tracking` (`id`, `pedido_id`, `operador_id`, `lat`, `lng`, `ts`) VALUES
(861, 16, 2, 19.682446, -98.890976, '2026-04-22 20:59:41'),
(862, 16, 2, 19.682446, -98.890976, '2026-04-22 21:00:42'),
(863, 16, 2, 19.682446, -98.890976, '2026-04-22 21:01:41'),
(864, 16, 2, 19.682446, -98.890976, '2026-04-22 21:02:42'),
(865, 16, 2, 19.682455, -98.890923, '2026-04-23 18:58:18'),
(866, 16, 2, 19.682459, -98.890923, '2026-04-23 19:01:54'),
(867, 16, 2, 19.682453, -98.890923, '2026-04-23 19:02:19'),
(868, 16, 2, 19.682453, -98.890923, '2026-04-23 19:02:23'),
(869, 16, 2, 19.654375525038, -99.027512850259, '2026-04-24 18:57:11'),
(870, 16, 2, 19.654375525038, -99.027512850259, '2026-04-24 18:57:15'),
(871, 16, 2, 19.654375525038, -99.027512850259, '2026-04-24 18:57:19'),
(872, 16, 2, 19.654461328487, -99.027464404811, '2026-04-24 18:57:25'),
(873, 16, 2, 19.654461328487, -99.027464404811, '2026-04-24 18:57:27'),
(874, 16, 2, 19.654461328487, -99.027464404811, '2026-04-24 18:57:31'),
(875, 16, 2, 19.654461328487, -99.027464404811, '2026-04-24 18:57:35'),
(876, 16, 2, 19.654442665029, -99.027474974537, '2026-04-24 18:57:42'),
(877, 16, 2, 19.654442665029, -99.027474974537, '2026-04-24 18:57:43'),
(878, 16, 2, 19.654442665029, -99.027474974537, '2026-04-24 18:57:47'),
(879, 16, 2, 19.654442665029, -99.027474974537, '2026-04-24 18:57:51'),
(880, 16, 2, 19.654369989266, -99.027505039868, '2026-04-24 18:57:58'),
(881, 16, 2, 19.654369989266, -99.027505039868, '2026-04-24 18:57:59'),
(882, 16, 2, 19.654369989266, -99.027505039868, '2026-04-24 18:58:03'),
(883, 16, 2, 19.654369989266, -99.027505039868, '2026-04-24 18:58:07'),
(884, 16, 2, 19.65436316501, -99.027508959418, '2026-04-24 18:58:14'),
(885, 16, 2, 19.65436316501, -99.027508959418, '2026-04-24 18:58:15'),
(886, 16, 2, 19.654382269624, -99.027476897669, '2026-04-24 18:59:51'),
(887, 16, 2, 19.654382269624, -99.027476897669, '2026-04-24 18:59:51'),
(888, 16, 2, 19.654382269624, -99.027476897669, '2026-04-24 18:59:55'),
(889, 16, 2, 19.654467659911, -99.027421014802, '2026-04-24 19:04:26'),
(890, 16, 2, 19.654467659911, -99.027421014802, '2026-04-24 19:04:27'),
(891, 16, 2, 19.654467659911, -99.027421014802, '2026-04-24 19:04:36'),
(892, 16, 2, 19.654532380341, -99.027393455771, '2026-04-24 19:04:42'),
(893, 16, 2, 19.654532380341, -99.027393455771, '2026-04-24 19:04:44'),
(894, 16, 2, 19.654591017176, -99.027367278014, '2026-04-25 02:15:58'),
(895, 16, 2, 19.654591017176, -99.027367278014, '2026-04-25 02:16:02'),
(896, 16, 2, 19.654427366334, -99.027457716272, '2026-04-25 02:16:14'),
(897, 16, 2, 19.654430892932, -99.027459546425, '2026-04-25 02:16:20'),
(898, 16, 2, 19.654430892932, -99.027459546425, '2026-04-25 02:16:22'),
(899, 16, 2, 19.654430892932, -99.027459546425, '2026-04-25 02:16:26'),
(900, 16, 2, 19.654430892932, -99.027459546425, '2026-04-25 02:16:30'),
(901, 16, 2, 19.654388999152, -99.027478453892, '2026-04-25 02:16:38'),
(902, 16, 2, 19.654388999152, -99.027478453892, '2026-04-25 02:16:38'),
(903, 16, 2, 19.654388999152, -99.027478453892, '2026-04-25 02:16:42'),
(904, 16, 2, 19.654388999152, -99.027478453892, '2026-04-25 02:16:46'),
(905, 16, 2, 19.654397968746, -99.027472968546, '2026-04-25 02:16:51'),
(906, 16, 2, 19.654397968746, -99.027472968546, '2026-04-25 02:16:54'),
(907, 16, 2, 19.654397968746, -99.027472968546, '2026-04-25 02:16:55'),
(908, 16, 2, 19.654397968746, -99.027472968546, '2026-04-25 02:16:59'),
(909, 16, 2, 19.654398220778, -99.027481144558, '2026-04-25 02:17:08'),
(910, 16, 2, 19.654398220778, -99.027481144558, '2026-04-25 02:17:08'),
(911, 16, 2, 19.654398220778, -99.027481144558, '2026-04-25 02:17:11'),
(912, 16, 2, 19.654398220778, -99.027481144558, '2026-04-25 02:17:15'),
(913, 16, 2, 19.654398220778, -99.027481144558, '2026-04-25 02:17:21'),
(914, 16, 2, 19.654398220778, -99.027481144558, '2026-04-25 02:17:23'),
(915, 16, 2, 19.654398220778, -99.027481144558, '2026-04-25 02:17:28'),
(916, 16, 2, 19.654391765137, -99.027483599668, '2026-04-25 02:17:34'),
(917, 16, 2, 19.654391765137, -99.027483599668, '2026-04-25 02:17:36'),
(918, 16, 2, 19.654391765137, -99.027483599668, '2026-04-25 02:17:40'),
(919, 16, 2, 19.654391765137, -99.027483599668, '2026-04-25 02:17:43'),
(920, 16, 2, 19.654418640714, -99.027465566141, '2026-04-25 02:17:50'),
(921, 16, 2, 19.654418640714, -99.027465566141, '2026-04-25 02:17:52'),
(922, 16, 2, 19.654418640714, -99.027465566141, '2026-04-25 02:17:55'),
(923, 16, 2, 19.654418640714, -99.027465566141, '2026-04-25 02:18:02'),
(924, 16, 2, 19.654418640714, -99.027465566141, '2026-04-25 02:18:03'),
(925, 16, 2, 19.654418640714, -99.027465566141, '2026-04-25 02:18:07'),
(926, 16, 2, 19.654418640714, -99.027465566141, '2026-04-25 02:18:11'),
(927, 16, 2, 19.654418640714, -99.027465566141, '2026-04-25 02:18:19'),
(928, 16, 2, 19.654418640714, -99.027465566141, '2026-04-25 02:18:20'),
(929, 16, 2, 19.654418640714, -99.027465566141, '2026-04-25 02:18:23'),
(930, 16, 2, 19.654422993783, -99.027461535914, '2026-04-25 02:19:32'),
(931, 16, 2, 19.654442957875, -99.02744937756, '2026-04-25 02:19:38'),
(932, 16, 2, 19.654442957875, -99.02744937756, '2026-04-25 02:19:40'),
(933, 16, 2, 19.654442957875, -99.02744937756, '2026-04-25 02:19:44'),
(934, 16, 2, 19.654442957875, -99.02744937756, '2026-04-25 02:19:48'),
(935, 16, 2, 19.654406639795, -99.027478965475, '2026-04-25 02:19:56'),
(936, 16, 2, 19.654406639795, -99.027478965475, '2026-04-25 02:19:57'),
(937, 16, 2, 19.654406639795, -99.027478965475, '2026-04-25 02:20:00'),
(938, 16, 2, 19.654406639795, -99.027478965475, '2026-04-25 02:20:05'),
(939, 16, 2, 19.654391365816, -99.027482135504, '2026-04-25 02:20:11'),
(940, 16, 2, 19.654391365816, -99.027482135504, '2026-04-25 02:20:13'),
(941, 16, 2, 19.654391365816, -99.027482135504, '2026-04-25 02:20:17'),
(942, 16, 2, 19.654391365816, -99.027482135504, '2026-04-25 02:20:21'),
(943, 16, 2, 19.654378882729, -99.027497778145, '2026-04-25 02:20:28'),
(944, 16, 2, 19.654378882729, -99.027497778145, '2026-04-25 02:20:28'),
(945, 16, 2, 19.654378882729, -99.027497778145, '2026-04-25 02:20:33'),
(946, 16, 2, 19.654378882729, -99.027497778145, '2026-04-25 02:20:36'),
(947, 16, 2, 19.654388024405, -99.027494120939, '2026-04-25 02:21:54'),
(948, 16, 2, 19.654388024405, -99.027494120939, '2026-04-25 02:21:55'),
(949, 16, 2, 19.654388024405, -99.027494120939, '2026-04-25 02:21:59'),
(950, 16, 2, 19.654388024405, -99.027494120939, '2026-04-25 02:22:03'),
(951, 16, 2, 19.654389122803, -99.027491931103, '2026-04-25 02:22:12'),
(952, 16, 2, 19.654389122803, -99.027491931103, '2026-04-25 02:22:12'),
(953, 16, 2, 19.654389122803, -99.027491931103, '2026-04-25 02:22:16'),
(954, 16, 2, 19.654389122803, -99.027491931103, '2026-04-25 02:22:20'),
(955, 16, 2, 19.65440742362, -99.027477985574, '2026-04-25 02:22:26'),
(956, 16, 2, 19.65440742362, -99.027477985574, '2026-04-25 02:22:28'),
(957, 16, 2, 19.65440742362, -99.027477985574, '2026-04-25 02:22:31'),
(958, 16, 2, 19.654485796166, -99.027424464926, '2026-04-25 08:30:45'),
(959, 16, 2, 19.654485796166, -99.027424464926, '2026-04-25 08:30:46'),
(960, 16, 2, 19.654485796166, -99.027424464926, '2026-04-25 08:30:50'),
(961, 16, 2, 19.654485796166, -99.027424464926, '2026-04-25 08:30:54'),
(962, 16, 2, 19.654454977017, -99.027430550131, '2026-04-25 08:30:57'),
(963, 16, 2, 19.654454977017, -99.027430550131, '2026-04-25 08:30:59'),
(964, 16, 2, 19.654454977017, -99.027430550131, '2026-04-25 08:31:03'),
(965, 16, 2, 19.654454977017, -99.027430550131, '2026-04-25 08:31:10'),
(966, 16, 2, 19.654454977017, -99.027430550131, '2026-04-25 08:31:11'),
(967, 16, 2, 19.654454977017, -99.027430550131, '2026-04-25 08:31:14'),
(968, 16, 2, 19.654454977017, -99.027430550131, '2026-04-25 08:31:18'),
(969, 16, 2, 19.654428776362, -99.027442451908, '2026-04-25 08:31:26'),
(970, 16, 2, 19.654428776362, -99.027442451908, '2026-04-25 08:31:26'),
(971, 16, 2, 19.654428776362, -99.027442451908, '2026-04-25 08:31:30'),
(972, 16, 2, 19.654428776362, -99.027442451908, '2026-04-25 08:31:34'),
(973, 16, 2, 19.654399876524, -99.027454506675, '2026-04-25 08:31:40'),
(974, 16, 2, 19.654399876524, -99.027454506675, '2026-04-25 08:31:42'),
(975, 16, 2, 19.654399876524, -99.027454506675, '2026-04-25 08:31:46'),
(976, 16, 2, 19.654399876524, -99.027454506675, '2026-04-25 08:31:50'),
(977, 16, 2, 19.654388265659, -99.027460146998, '2026-04-25 08:31:58'),
(978, 16, 2, 19.654388265659, -99.027460146998, '2026-04-25 08:31:58'),
(979, 16, 2, 19.654388265659, -99.027460146998, '2026-04-25 08:32:02'),
(980, 16, 2, 19.654388265659, -99.027460146998, '2026-04-25 08:32:06'),
(981, 16, 2, 19.654387174471, -99.027461681202, '2026-04-25 08:32:13'),
(982, 16, 2, 19.654387174471, -99.027461681202, '2026-04-25 08:32:14'),
(983, 16, 2, 19.654387174471, -99.027461681202, '2026-04-25 08:32:18'),
(984, 16, 2, 19.654387174471, -99.027461681202, '2026-04-25 08:32:22'),
(985, 16, 2, 19.654387174471, -99.027461681202, '2026-04-25 08:32:29'),
(986, 16, 2, 19.654387174471, -99.027461681202, '2026-04-25 08:32:30'),
(987, 16, 2, 19.654387174471, -99.027461681202, '2026-04-25 08:32:34'),
(988, 16, 2, 19.654387174471, -99.027461681202, '2026-04-25 08:32:38'),
(989, 16, 2, 19.654385858302, -99.027466101962, '2026-04-25 08:32:46'),
(990, 16, 2, 19.654385858302, -99.027466101962, '2026-04-25 08:32:46'),
(991, 16, 2, 19.654385858302, -99.027466101962, '2026-04-25 08:32:50'),
(992, 16, 2, 19.654385858302, -99.027466101962, '2026-04-25 08:32:54'),
(993, 16, 2, 19.654356819691, -99.027489221054, '2026-04-25 08:33:00'),
(994, 16, 2, 19.654356819691, -99.027489221054, '2026-04-25 08:33:02'),
(995, 16, 2, 19.654356819691, -99.027489221054, '2026-04-25 08:33:06'),
(996, 16, 2, 19.654373127524, -99.027476004998, '2026-04-25 08:33:11'),
(997, 16, 2, 19.654373127524, -99.027476004998, '2026-04-25 08:33:14'),
(998, 16, 2, 19.654373127524, -99.027476004998, '2026-04-25 08:33:18'),
(999, 16, 2, 19.654365364555, -99.027479920906, '2026-04-25 08:33:26'),
(1000, 16, 2, 19.654365364555, -99.027479920906, '2026-04-25 08:33:29'),
(1001, 16, 2, 19.654365364555, -99.027479920906, '2026-04-25 08:33:33'),
(1002, 16, 2, 19.654314442449, -99.027527131853, '2026-04-25 08:33:40'),
(1003, 16, 2, 19.654314442449, -99.027527131853, '2026-04-25 08:33:42'),
(1004, 16, 2, 19.654314442449, -99.027527131853, '2026-04-25 08:33:46'),
(1005, 16, 2, 19.654314442449, -99.027527131853, '2026-04-25 08:33:50'),
(1006, 16, 2, 19.654311409951, -99.02752887558, '2026-04-25 08:34:04'),
(1007, 16, 2, 19.654311409951, -99.02752887558, '2026-04-25 08:34:04'),
(1008, 16, 2, 19.654311409951, -99.02752887558, '2026-04-25 08:34:06'),
(1009, 16, 2, 19.654311409951, -99.02752887558, '2026-04-25 08:34:10'),
(1010, 16, 2, 19.654311409951, -99.02752887558, '2026-04-25 08:34:14'),
(1011, 16, 2, 19.654313442252, -99.027530570915, '2026-04-25 08:34:23'),
(1012, 16, 2, 19.654313442252, -99.027530570915, '2026-04-25 08:34:23'),
(1013, 16, 2, 19.654313442252, -99.027530570915, '2026-04-25 08:34:26'),
(1014, 16, 2, 19.654313442252, -99.027530570915, '2026-04-25 08:34:30'),
(1015, 16, 2, 19.654324045417, -99.02752974069, '2026-04-25 08:34:40'),
(1016, 16, 2, 19.654324045417, -99.02752974069, '2026-04-25 08:34:40'),
(1017, 16, 2, 19.654324045417, -99.02752974069, '2026-04-25 08:34:42'),
(1018, 16, 2, 19.654324045417, -99.02752974069, '2026-04-25 08:34:46'),
(1019, 16, 2, 19.654319335517, -99.027534601198, '2026-04-25 08:34:52'),
(1020, 16, 2, 19.654319335517, -99.027534601198, '2026-04-25 08:34:54'),
(1021, 16, 2, 19.654319335517, -99.027534601198, '2026-04-25 08:34:58'),
(1022, 16, 2, 19.654319335517, -99.027534601198, '2026-04-25 08:35:02'),
(1023, 16, 2, 19.654314240906, -99.027533498036, '2026-04-25 08:35:08'),
(1024, 16, 2, 19.654314240906, -99.027533498036, '2026-04-25 08:35:10'),
(1025, 16, 2, 19.654314240906, -99.027533498036, '2026-04-25 08:35:14'),
(1026, 16, 2, 19.654314240906, -99.027533498036, '2026-04-25 08:35:18'),
(1027, 16, 2, 19.654314240906, -99.027533498036, '2026-04-25 08:35:24'),
(1028, 16, 2, 19.654314240906, -99.027533498036, '2026-04-25 08:35:26'),
(1029, 16, 2, 19.654314240906, -99.027533498036, '2026-04-25 08:35:30'),
(1030, 16, 2, 19.654314240906, -99.027533498036, '2026-04-25 08:35:38'),
(1031, 16, 2, 19.654314240906, -99.027533498036, '2026-04-25 08:35:38'),
(1032, 16, 2, 19.654314240906, -99.027533498036, '2026-04-25 08:35:42'),
(1033, 16, 2, 19.654314240906, -99.027533498036, '2026-04-25 08:35:46'),
(1034, 16, 2, 19.654318719621, -99.027522480209, '2026-04-25 08:35:53'),
(1035, 16, 2, 19.654318719621, -99.027522480209, '2026-04-25 08:35:54'),
(1036, 16, 2, 19.654318719621, -99.027522480209, '2026-04-25 08:35:58'),
(1037, 16, 2, 19.654318719621, -99.027522480209, '2026-04-25 08:36:02'),
(1038, 16, 2, 19.654334715235, -99.027512363635, '2026-04-25 08:36:08'),
(1039, 16, 2, 19.654334715235, -99.027512363635, '2026-04-25 08:36:10'),
(1040, 16, 2, 19.654334715235, -99.027512363635, '2026-04-25 08:36:14'),
(1041, 16, 2, 19.654307793019, -99.02752848457, '2026-04-25 08:36:20'),
(1042, 16, 2, 19.654307793019, -99.02752848457, '2026-04-25 08:36:22'),
(1043, 16, 2, 19.654307793019, -99.02752848457, '2026-04-25 08:36:26'),
(1044, 16, 2, 19.654307793019, -99.02752848457, '2026-04-25 08:36:32'),
(1045, 16, 2, 19.654307793019, -99.02752848457, '2026-04-25 08:36:34'),
(1046, 16, 2, 19.654307793019, -99.02752848457, '2026-04-25 08:36:38'),
(1047, 16, 2, 19.654375165024, -99.027489695125, '2026-04-25 08:36:46'),
(1048, 16, 2, 19.654375165024, -99.027489695125, '2026-04-25 08:36:46'),
(1049, 16, 2, 19.654375165024, -99.027489695125, '2026-04-25 08:36:50'),
(1050, 16, 2, 19.654375165024, -99.027489695125, '2026-04-25 08:36:54'),
(1051, 16, 2, 19.654429275245, -99.027448650869, '2026-04-25 08:37:01'),
(1052, 16, 2, 19.654429275245, -99.027448650869, '2026-04-25 08:37:02'),
(1053, 16, 2, 19.654429275245, -99.027448650869, '2026-04-25 08:37:06'),
(1054, 16, 2, 19.654429275245, -99.027448650869, '2026-04-25 08:37:10'),
(1055, 16, 2, 19.65446158131, -99.02743243592, '2026-04-25 08:37:16'),
(1056, 16, 2, 19.65446158131, -99.02743243592, '2026-04-25 08:37:18'),
(1057, 16, 2, 19.65446158131, -99.02743243592, '2026-04-25 08:37:22'),
(1058, 16, 2, 19.65446158131, -99.02743243592, '2026-04-25 08:37:26'),
(1059, 16, 2, 19.654461062036, -99.027432700375, '2026-04-25 08:37:32'),
(1060, 16, 2, 19.654461062036, -99.027432700375, '2026-04-25 08:37:34'),
(1061, 16, 2, 19.654461062036, -99.027432700375, '2026-04-25 08:37:38'),
(1062, 16, 2, 19.654461062036, -99.027432700375, '2026-04-25 08:37:42'),
(1063, 16, 2, 19.654461062036, -99.027432700375, '2026-04-25 08:37:48'),
(1064, 16, 2, 19.654461062036, -99.027432700375, '2026-04-25 08:37:50'),
(1065, 16, 2, 19.654461062036, -99.027432700375, '2026-04-25 08:37:54'),
(1066, 16, 2, 19.654461062036, -99.027432700375, '2026-04-25 08:37:58'),
(1067, 16, 2, 19.654398489735, -99.027471927229, '2026-04-25 08:38:06'),
(1068, 16, 2, 19.654398489735, -99.027471927229, '2026-04-25 08:38:06'),
(1069, 16, 2, 19.654398489735, -99.027471927229, '2026-04-25 08:38:10'),
(1070, 16, 2, 19.654398489735, -99.027471927229, '2026-04-25 08:38:14'),
(1071, 16, 2, 19.654377565013, -99.027479287601, '2026-04-25 08:38:20'),
(1072, 16, 2, 19.654377565013, -99.027479287601, '2026-04-25 08:38:22'),
(1073, 16, 2, 19.654377565013, -99.027479287601, '2026-04-25 08:38:26'),
(1074, 16, 2, 19.654377565013, -99.027479287601, '2026-04-25 08:38:30'),
(1075, 16, 2, 19.654281611805, -99.02756285667, '2026-04-25 08:39:01'),
(1076, 16, 2, 19.654281950001, -99.027561420177, '2026-04-25 08:40:02'),
(1077, 16, 2, 19.654312331364, -99.027527896255, '2026-04-25 08:41:02'),
(1078, 16, 2, 19.654289093223, -99.027527005367, '2026-04-25 08:42:03'),
(1079, 16, 2, 19.654263111942, -99.027537957426, '2026-04-25 08:43:02'),
(1080, 16, 2, 19.654401918402, -99.027492939242, '2026-04-25 08:44:01'),
(1081, 16, 2, 19.654428145099, -99.027488634329, '2026-04-25 08:45:02'),
(1082, 16, 2, 19.654280039475, -99.02753984993, '2026-04-25 08:46:02'),
(1083, 16, 2, 19.654352033357, -99.027492448263, '2026-04-25 08:47:04'),
(1084, 16, 2, 19.654329180385, -99.027506598345, '2026-04-25 08:48:02'),
(1085, 16, 2, 19.65425192507, -99.027547022211, '2026-04-25 08:49:02'),
(1086, 16, 2, 19.654326294744, -99.027528638116, '2026-04-25 08:50:04'),
(1087, 16, 2, 19.654328517159, -99.027529551529, '2026-04-25 08:51:01'),
(1088, 16, 2, 19.654299530554, -99.027535402303, '2026-04-25 08:52:03'),
(1089, 16, 2, 19.654289229024, -99.027535749956, '2026-04-25 08:53:01'),
(1090, 16, 2, 19.654314103831, -99.027530779185, '2026-04-25 08:54:01'),
(1091, 16, 2, 19.654392637037, -99.027468545935, '2026-04-25 08:55:02'),
(1092, 16, 2, 19.654371550325, -99.027488612411, '2026-04-25 08:56:02'),
(1093, 16, 2, 19.65431495575, -99.027534650251, '2026-04-25 08:57:03'),
(1094, 16, 2, 19.654354796345, -99.027493021023, '2026-04-25 08:58:03'),
(1095, 16, 2, 19.654296886638, -99.02752907425, '2026-04-25 08:59:01'),
(1096, 16, 2, 19.654397878171, -99.027499392173, '2026-04-25 09:00:01'),
(1097, 16, 2, 19.654357659836, -99.027509855704, '2026-04-25 09:01:02'),
(1098, 16, 2, 19.654343719207, -99.027518242835, '2026-04-25 09:02:04'),
(1099, 16, 2, 19.654357452521, -99.027508841577, '2026-04-25 09:03:02'),
(1100, 16, 2, 19.654312036741, -99.02751685965, '2026-04-25 09:04:03'),
(1101, 16, 2, 19.654293413897, -99.027518505395, '2026-04-25 09:05:03'),
(1102, 16, 2, 19.654405161367, -99.027479932099, '2026-04-25 09:06:03'),
(1103, 16, 2, 19.654425383782, -99.027482591405, '2026-04-25 09:07:04'),
(1104, 16, 2, 19.654361655622, -99.027498820383, '2026-04-25 09:08:02'),
(1105, 16, 2, 19.654382450641, -99.027487259581, '2026-04-25 09:09:04'),
(1106, 16, 2, 19.654372772403, -99.027493284009, '2026-04-25 09:10:02'),
(1107, 16, 2, 19.654363476204, -99.027491699419, '2026-04-25 09:11:02'),
(1108, 16, 2, 19.654382469667, -99.027495097102, '2026-04-25 09:12:03'),
(1109, 16, 2, 19.654359326133, -99.027497416816, '2026-04-25 09:13:02'),
(1110, 16, 2, 19.654301625693, -99.027518766919, '2026-04-25 09:14:02'),
(1111, 16, 2, 19.654298724163, -99.027523210029, '2026-04-25 09:15:03'),
(1112, 16, 2, 19.654291406293, -99.027530452173, '2026-04-25 09:16:02'),
(1113, 16, 2, 19.654340843339, -99.027517601753, '2026-04-25 09:17:04'),
(1114, 16, 2, 19.654315720067, -99.027524807052, '2026-04-25 09:18:02'),
(1115, 16, 2, 19.654302134389, -99.027516918754, '2026-04-25 09:19:03'),
(1116, 16, 2, 19.654325507301, -99.027508905412, '2026-04-25 09:20:02'),
(1117, 16, 2, 19.654380749149, -99.027507864823, '2026-04-25 09:21:02'),
(1118, 16, 2, 19.654335976967, -99.027524230597, '2026-04-25 09:22:03'),
(1119, 16, 2, 19.654344837444, -99.027535859297, '2026-04-25 09:22:48'),
(1120, 16, 2, 19.654344837444, -99.027535859297, '2026-04-25 09:22:51'),
(1121, 16, 2, 19.654344837444, -99.027535859297, '2026-04-25 09:22:52'),
(1122, 16, 2, 19.654344837444, -99.027535859297, '2026-04-25 09:22:53'),
(1123, 16, 2, 19.654344837444, -99.027535859297, '2026-04-25 09:22:58'),
(1124, 16, 2, 19.654338617652, -99.027532298687, '2026-04-25 09:23:04'),
(1125, 16, 2, 19.654338617652, -99.027532298687, '2026-04-25 09:23:05'),
(1126, 16, 2, 19.654338617652, -99.027532298687, '2026-04-25 09:23:07'),
(1127, 16, 2, 19.654338617652, -99.027532298687, '2026-04-25 09:23:09'),
(1128, 16, 2, 19.654338617652, -99.027532298687, '2026-04-25 09:23:13'),
(1129, 16, 2, 19.654322361782, -99.027530141614, '2026-04-25 09:23:20'),
(1130, 16, 2, 19.654322361782, -99.027530141614, '2026-04-25 09:23:21'),
(1131, 16, 2, 19.654322361782, -99.027530141614, '2026-04-25 09:23:25'),
(1132, 16, 2, 19.654322361782, -99.027530141614, '2026-04-25 09:23:29'),
(1133, 16, 2, 19.654322361782, -99.027530141614, '2026-04-25 09:23:31'),
(1134, 16, 2, 19.654321910827, -99.027529347454, '2026-04-25 09:23:38'),
(1135, 16, 2, 19.654321910827, -99.027529347454, '2026-04-25 09:23:40'),
(1136, 16, 2, 19.654321910827, -99.027529347454, '2026-04-25 09:23:44'),
(1137, 16, 2, 19.654366138054, -99.027511551195, '2026-04-25 09:23:53'),
(1138, 16, 2, 19.654366138054, -99.027511551195, '2026-04-25 09:23:53'),
(1139, 16, 2, 19.654366138054, -99.027511551195, '2026-04-25 09:23:56'),
(1140, 16, 2, 19.654366138054, -99.027511551195, '2026-04-25 09:24:00'),
(1141, 16, 2, 19.654366138054, -99.027511551195, '2026-04-25 09:24:07'),
(1142, 16, 2, 19.654366138054, -99.027511551195, '2026-04-25 09:24:07'),
(1143, 16, 2, 19.654366138054, -99.027511551195, '2026-04-25 09:24:08'),
(1144, 16, 2, 19.654366138054, -99.027511551195, '2026-04-25 09:24:12'),
(1145, 16, 2, 19.654366138054, -99.027511551195, '2026-04-25 09:24:14'),
(1146, 16, 2, 19.654436272901, -99.027491147582, '2026-04-25 10:39:20'),
(1147, 16, 2, 19.654436272901, -99.027491147582, '2026-04-25 10:39:21'),
(1148, 16, 2, 19.654436272901, -99.027491147582, '2026-04-25 10:39:28'),
(1149, 16, 2, 19.654356205381, -99.027517879666, '2026-04-25 10:39:34'),
(1150, 16, 2, 19.654356205381, -99.027517879666, '2026-04-25 10:39:36'),
(1151, 16, 2, 19.654356205381, -99.027517879666, '2026-04-25 10:39:38'),
(1152, 16, 2, 19.654356205381, -99.027517879666, '2026-04-25 10:39:42'),
(1153, 16, 2, 19.654356205381, -99.027517879666, '2026-04-25 10:39:47'),
(1154, 16, 2, 19.654356205381, -99.027517879666, '2026-04-25 10:39:49'),
(1155, 16, 2, 19.654356205381, -99.027517879666, '2026-04-25 10:39:50'),
(1156, 16, 2, 19.654318252582, -99.027549305874, '2026-04-25 10:58:32'),
(1157, 16, 2, 19.654318252582, -99.027549305874, '2026-04-25 10:58:34'),
(1158, 16, 2, 19.654318252582, -99.027549305874, '2026-04-25 10:58:36'),
(1159, 16, 2, 19.654318252582, -99.027549305874, '2026-04-25 10:58:38'),
(1160, 16, 2, 19.654318252582, -99.027549305874, '2026-04-25 10:58:39'),
(1161, 16, 2, 19.654308241094, -99.027548015789, '2026-04-25 10:58:46'),
(1162, 16, 2, 19.654308241094, -99.027548015789, '2026-04-25 10:58:47'),
(1163, 16, 2, 19.654308241094, -99.027548015789, '2026-04-25 10:58:51'),
(1164, 16, 2, 19.654308241094, -99.027548015789, '2026-04-25 10:58:55'),
(1165, 16, 2, 19.654308241094, -99.027548015789, '2026-04-25 10:59:01'),
(1166, 16, 2, 19.654308241094, -99.027548015789, '2026-04-25 10:59:03'),
(1167, 16, 2, 19.654308241094, -99.027548015789, '2026-04-25 10:59:07'),
(1168, 16, 2, 19.654308241094, -99.027548015789, '2026-04-25 10:59:11'),
(1169, 16, 2, 19.654281883902, -99.027568562148, '2026-04-25 10:59:19'),
(1170, 16, 2, 19.654281883902, -99.027568562148, '2026-04-25 10:59:19'),
(1171, 16, 2, 19.654281883902, -99.027568562148, '2026-04-25 10:59:23'),
(1172, 16, 2, 19.654281883902, -99.027568562148, '2026-04-25 10:59:27'),
(1173, 16, 2, 19.654327513085, -99.027534523508, '2026-04-25 10:59:33'),
(1174, 16, 2, 19.654327513085, -99.027534523508, '2026-04-25 10:59:35'),
(1175, 16, 2, 19.654327513085, -99.027534523508, '2026-04-25 10:59:39'),
(1176, 16, 2, 19.654327513085, -99.027534523508, '2026-04-25 10:59:43'),
(1177, 16, 2, 19.654329254395, -99.027531128545, '2026-04-25 10:59:50'),
(1178, 16, 2, 19.654329254395, -99.027531128545, '2026-04-25 10:59:51'),
(1179, 16, 2, 19.654329254395, -99.027531128545, '2026-04-25 10:59:55'),
(1180, 16, 2, 19.654329254395, -99.027531128545, '2026-04-25 10:59:59'),
(1181, 16, 2, 19.654329254395, -99.027531128545, '2026-04-25 11:00:05'),
(1182, 16, 2, 19.654329254395, -99.027531128545, '2026-04-25 11:00:08'),
(1183, 16, 2, 19.654329254395, -99.027531128545, '2026-04-25 11:00:12'),
(1184, 16, 2, 19.654325123792, -99.027530949516, '2026-04-25 11:00:18'),
(1185, 16, 2, 19.654325123792, -99.027530949516, '2026-04-25 11:00:19'),
(1186, 16, 2, 19.654325123792, -99.027530949516, '2026-04-25 11:00:23'),
(1187, 16, 2, 19.654325123792, -99.027530949516, '2026-04-25 11:00:28'),
(1188, 16, 2, 19.654343013017, -99.027520928297, '2026-04-25 11:00:35'),
(1189, 16, 2, 19.654343013017, -99.027520928297, '2026-04-25 11:00:35'),
(1190, 16, 2, 19.654343013017, -99.027520928297, '2026-04-25 11:00:39'),
(1191, 16, 2, 19.654343013017, -99.027520928297, '2026-04-25 11:00:43'),
(1192, 16, 2, 19.654341439116, -99.027522869184, '2026-04-25 11:00:50'),
(1193, 16, 2, 19.654341439116, -99.027522869184, '2026-04-25 11:00:52'),
(1194, 16, 2, 19.654341439116, -99.027522869184, '2026-04-25 11:00:55'),
(1195, 16, 2, 19.654341439116, -99.027522869184, '2026-04-25 11:01:00'),
(1196, 16, 2, 19.654336381388, -99.027524455802, '2026-04-25 11:01:06'),
(1197, 16, 2, 19.654336381388, -99.027524455802, '2026-04-25 11:01:07'),
(1198, 16, 2, 19.654336381388, -99.027524455802, '2026-04-25 11:01:12'),
(1199, 16, 2, 19.654336381388, -99.027524455802, '2026-04-25 11:01:16'),
(1200, 16, 2, 19.654336381388, -99.027524455802, '2026-04-25 11:01:19'),
(1201, 16, 2, 19.65435792582, -99.027501411631, '2026-04-25 11:01:26'),
(1202, 16, 2, 19.65435792582, -99.027501411631, '2026-04-25 11:01:28'),
(1203, 16, 2, 19.65435792582, -99.027501411631, '2026-04-25 11:01:32'),
(1204, 16, 2, 19.65435792582, -99.027501411631, '2026-04-25 11:01:36'),
(1205, 16, 2, 19.654330543002, -99.027522328037, '2026-04-25 11:01:42'),
(1206, 16, 2, 19.654330543002, -99.027522328037, '2026-04-25 11:01:44'),
(1207, 16, 2, 19.654330543002, -99.027522328037, '2026-04-25 11:01:47'),
(1208, 16, 2, 19.654330543002, -99.027522328037, '2026-04-25 11:01:52'),
(1209, 16, 2, 19.654298451915, -99.027542104036, '2026-04-25 11:01:59'),
(1210, 16, 2, 19.654298451915, -99.027542104036, '2026-04-25 11:01:59'),
(1211, 16, 2, 19.654298451915, -99.027542104036, '2026-04-25 11:02:04'),
(1212, 16, 2, 19.654298451915, -99.027542104036, '2026-04-25 11:02:07'),
(1213, 16, 2, 19.654298451915, -99.027542104036, '2026-04-25 11:02:14'),
(1214, 16, 2, 19.654298451915, -99.027542104036, '2026-04-25 11:02:15'),
(1215, 16, 2, 19.654298451915, -99.027542104036, '2026-04-25 11:02:20'),
(1216, 16, 2, 19.654298451915, -99.027542104036, '2026-04-25 11:02:23'),
(1217, 16, 2, 19.654293694006, -99.027541394039, '2026-04-25 11:02:29'),
(1218, 16, 2, 19.654293694006, -99.027541394039, '2026-04-25 11:02:31'),
(1219, 16, 2, 19.654293694006, -99.027541394039, '2026-04-25 11:02:36'),
(1220, 16, 2, 19.654293694006, -99.027541394039, '2026-04-25 11:02:39'),
(1221, 16, 2, 19.654275759876, -99.02755199658, '2026-04-25 11:02:45'),
(1222, 16, 2, 19.654275759876, -99.02755199658, '2026-04-25 11:02:47'),
(1223, 16, 2, 19.654275759876, -99.02755199658, '2026-04-25 11:02:51'),
(1224, 16, 2, 19.654275759876, -99.02755199658, '2026-04-25 11:02:55'),
(1225, 16, 2, 19.654280395576, -99.027552331813, '2026-04-25 11:03:02'),
(1226, 16, 2, 19.654280395576, -99.027552331813, '2026-04-25 11:03:03'),
(1227, 16, 2, 19.654280395576, -99.027552331813, '2026-04-25 11:03:07'),
(1228, 16, 2, 19.654280395576, -99.027552331813, '2026-04-25 11:03:11'),
(1229, 16, 2, 19.654281209293, -99.027552842691, '2026-04-25 11:03:19'),
(1230, 16, 2, 19.654281209293, -99.027552842691, '2026-04-25 11:03:19'),
(1231, 16, 2, 19.654281209293, -99.027552842691, '2026-04-25 11:03:23'),
(1232, 16, 2, 19.654281209293, -99.027552842691, '2026-04-25 11:03:27'),
(1233, 16, 2, 19.654280987681, -99.027555835173, '2026-04-25 11:03:34'),
(1234, 16, 2, 19.654280987681, -99.027555835173, '2026-04-25 11:03:35'),
(1235, 16, 2, 19.654280987681, -99.027555835173, '2026-04-25 11:03:39'),
(1236, 16, 2, 19.654280987681, -99.027555835173, '2026-04-25 11:03:43'),
(1237, 16, 2, 19.654280987681, -99.027555835173, '2026-04-25 11:03:49'),
(1238, 16, 2, 19.654280987681, -99.027555835173, '2026-04-25 11:03:51'),
(1239, 16, 2, 19.654280987681, -99.027555835173, '2026-04-25 11:03:55'),
(1240, 16, 2, 19.654283482504, -99.027561942704, '2026-04-25 11:04:01'),
(1241, 16, 2, 19.654283482504, -99.027561942704, '2026-04-25 11:04:03'),
(1242, 16, 2, 19.654283482504, -99.027561942704, '2026-04-25 11:04:07'),
(1243, 16, 2, 19.654283482504, -99.027561942704, '2026-04-25 11:04:14'),
(1244, 16, 2, 19.654283482504, -99.027561942704, '2026-04-25 11:04:15'),
(1245, 16, 2, 19.654283482504, -99.027561942704, '2026-04-25 11:04:19'),
(1246, 16, 2, 19.654283482504, -99.027561942704, '2026-04-25 11:04:23'),
(1247, 16, 2, 19.654257644666, -99.027579485469, '2026-04-25 11:04:31'),
(1248, 16, 2, 19.654257644666, -99.027579485469, '2026-04-25 11:04:31'),
(1249, 16, 2, 19.654257644666, -99.027579485469, '2026-04-25 11:04:35'),
(1250, 16, 2, 19.654257644666, -99.027579485469, '2026-04-25 11:04:39'),
(1251, 16, 2, 19.654257644666, -99.027579485469, '2026-04-25 11:04:46'),
(1252, 16, 2, 19.654257644666, -99.027579485469, '2026-04-25 11:04:47'),
(1253, 16, 2, 19.654257644666, -99.027579485469, '2026-04-25 11:04:51'),
(1254, 16, 2, 19.654258314884, -99.027579399149, '2026-04-25 11:05:01'),
(1255, 16, 2, 19.654238484541, -99.027573352854, '2026-04-25 11:06:02'),
(1256, 16, 2, 19.654233316264, -99.027572507892, '2026-04-25 11:07:02'),
(1257, 16, 2, 19.654220508486, -99.027578324984, '2026-04-25 11:08:03'),
(1258, 16, 2, 19.654261982466, -99.027552859319, '2026-04-25 11:09:02'),
(1259, 16, 2, 19.682449, -98.89093, '2026-04-25 11:20:38'),
(1260, 16, 2, 19.682449, -98.89093, '2026-04-25 11:20:42'),
(1261, 16, 2, 19.682449, -98.89093, '2026-04-25 11:20:45'),
(1262, 16, 2, 19.682453, -98.890938, '2026-04-25 11:20:50'),
(1263, 16, 2, 19.682453, -98.890938, '2026-04-25 11:20:54'),
(1264, 16, 2, 19.682453, -98.890938, '2026-04-25 11:20:57'),
(1265, 16, 2, 19.682455, -98.89093, '2026-04-25 11:21:02'),
(1266, 16, 2, 19.682455, -98.89093, '2026-04-25 11:21:03'),
(1267, 16, 2, 19.682455, -98.89093, '2026-04-25 11:21:07'),
(1268, 16, 2, 19.682455, -98.89093, '2026-04-25 11:21:11'),
(1269, 16, 2, 19.682455, -98.89093, '2026-04-25 11:21:16'),
(1270, 16, 2, 19.682455, -98.89093, '2026-04-25 11:21:19'),
(1271, 16, 2, 19.682455, -98.89093, '2026-04-25 11:21:28'),
(1272, 16, 2, 19.682455, -98.89093, '2026-04-25 11:21:32'),
(1273, 16, 2, 19.682455, -98.89093, '2026-04-25 11:21:36'),
(1274, 16, 2, 19.682455, -98.89093, '2026-04-25 11:21:40'),
(1275, 16, 2, 19.682455, -98.89093, '2026-04-25 11:21:44'),
(1276, 16, 2, 19.682455, -98.89093, '2026-04-25 11:21:48'),
(1277, 16, 2, 19.682455, -98.89093, '2026-04-25 11:21:52'),
(1278, 16, 2, 19.682455, -98.89093, '2026-04-25 11:21:56'),
(1279, 16, 2, 19.682455, -98.89093, '2026-04-25 11:22:00'),
(1280, 16, 2, 19.682455, -98.89093, '2026-04-25 11:22:04'),
(1281, 16, 2, 19.682455, -98.89093, '2026-04-25 11:22:08'),
(1282, 16, 2, 19.682455, -98.89093, '2026-04-25 11:22:12'),
(1283, 16, 2, 19.682455, -98.89093, '2026-04-25 11:22:16'),
(1284, 16, 2, 19.682455, -98.89093, '2026-04-25 11:22:20'),
(1285, 16, 2, 19.682455, -98.89093, '2026-04-25 11:22:24'),
(1286, 16, 2, 19.682455, -98.89093, '2026-04-25 11:22:28'),
(1287, 16, 2, 19.682455, -98.89093, '2026-04-25 11:22:32'),
(1288, 16, 2, 19.682455, -98.89093, '2026-04-25 11:22:36'),
(1289, 16, 2, 19.682455, -98.89093, '2026-04-25 11:22:40'),
(1290, 16, 2, 19.682455, -98.89093, '2026-04-25 11:22:44'),
(1291, 16, 2, 19.682455, -98.89093, '2026-04-25 11:22:48'),
(1292, 16, 2, 19.682455, -98.89093, '2026-04-25 11:22:52'),
(1293, 16, 2, 19.682455, -98.89093, '2026-04-25 11:22:56'),
(1294, 16, 2, 19.682455, -98.89093, '2026-04-25 11:23:00'),
(1295, 16, 2, 19.682455, -98.89093, '2026-04-25 11:23:04'),
(1296, 16, 2, 19.682455, -98.89093, '2026-04-25 11:23:08'),
(1297, 16, 2, 19.682455, -98.89093, '2026-04-25 11:23:09'),
(1298, 16, 2, 19.682455, -98.89093, '2026-04-25 11:23:13'),
(1299, 16, 2, 19.682455, -98.89093, '2026-04-25 11:23:17'),
(1300, 16, 2, 19.682455, -98.89093, '2026-04-25 11:23:19'),
(1301, 16, 2, 19.682455, -98.89093, '2026-04-25 11:23:22'),
(1302, 16, 2, 19.682455, -98.89093, '2026-04-25 11:23:26'),
(1303, 16, 2, 19.682455, -98.89093, '2026-04-25 11:23:31'),
(1304, 16, 2, 19.682455, -98.89093, '2026-04-25 11:23:34'),
(1305, 16, 2, 19.682455, -98.89093, '2026-04-25 11:23:38'),
(1306, 16, 2, 19.682455, -98.89093, '2026-04-25 11:23:43'),
(1307, 16, 2, 19.682455, -98.89093, '2026-04-25 11:23:46'),
(1308, 16, 2, 19.682459, -98.890907, '2026-04-25 11:23:51'),
(1309, 16, 2, 19.682459, -98.890907, '2026-04-25 11:23:55'),
(1310, 16, 2, 19.682459, -98.890907, '2026-04-25 11:23:58'),
(1311, 16, 2, 19.682453, -98.89093, '2026-04-25 11:24:03'),
(1312, 16, 2, 19.682453, -98.89093, '2026-04-25 11:24:07'),
(1313, 16, 2, 19.682453, -98.89093, '2026-04-25 11:24:10'),
(1314, 16, 2, 19.682453, -98.89093, '2026-04-25 11:24:14'),
(1315, 16, 2, 19.682453, -98.89093, '2026-04-25 11:24:18'),
(1316, 16, 2, 19.682453, -98.89093, '2026-04-25 11:24:22'),
(1317, 16, 2, 19.682453, -98.89093, '2026-04-25 11:24:26'),
(1318, 16, 2, 19.682453, -98.89093, '2026-04-25 11:24:31'),
(1319, 16, 2, 19.682453, -98.89093, '2026-04-25 11:24:34'),
(1320, 16, 2, 19.682453, -98.89093, '2026-04-25 11:24:38'),
(1321, 16, 2, 19.682453, -98.89093, '2026-04-25 11:24:43'),
(1322, 16, 2, 19.682453, -98.89093, '2026-04-25 11:24:46'),
(1323, 16, 2, 19.682453, -98.89093, '2026-04-25 11:24:50'),
(1324, 16, 2, 19.682453, -98.89093, '2026-04-25 11:24:55'),
(1325, 16, 2, 19.682453, -98.89093, '2026-04-25 11:24:58'),
(1326, 16, 2, 19.682453, -98.89093, '2026-04-25 11:25:03'),
(1327, 16, 2, 19.682453, -98.89093, '2026-04-25 11:25:06'),
(1328, 16, 2, 19.682453, -98.89093, '2026-04-25 11:25:10'),
(1329, 16, 2, 19.682457, -98.890915, '2026-04-25 11:25:15'),
(1330, 16, 2, 19.682457, -98.890915, '2026-04-25 11:25:19'),
(1331, 16, 2, 19.682457, -98.890915, '2026-04-25 11:25:22'),
(1332, 16, 2, 19.682457, -98.890915, '2026-04-25 11:25:26'),
(1333, 16, 2, 19.682457, -98.890915, '2026-04-25 11:25:31'),
(1334, 16, 2, 19.682457, -98.890915, '2026-04-25 11:25:34'),
(1335, 16, 2, 19.682457, -98.890915, '2026-04-25 11:25:38'),
(1336, 16, 2, 19.682457, -98.890915, '2026-04-25 11:25:43'),
(1337, 16, 2, 19.682457, -98.890915, '2026-04-25 11:25:46'),
(1338, 16, 2, 19.682457, -98.890915, '2026-04-25 11:25:50'),
(1339, 16, 2, 19.682457, -98.890915, '2026-04-25 11:25:55'),
(1340, 16, 2, 19.682457, -98.890915, '2026-04-25 11:25:58'),
(1341, 16, 2, 19.682453, -98.89093, '2026-04-25 11:26:03'),
(1342, 16, 2, 19.682453, -98.89093, '2026-04-25 11:26:07'),
(1343, 16, 2, 19.682453, -98.89093, '2026-04-25 11:26:10'),
(1344, 16, 2, 19.682453, -98.89093, '2026-04-25 11:26:14'),
(1345, 16, 2, 19.682453, -98.89093, '2026-04-25 11:26:19'),
(1346, 16, 2, 19.682453, -98.89093, '2026-04-25 11:26:22'),
(1347, 16, 2, 19.682453, -98.89093, '2026-04-25 11:26:26'),
(1348, 16, 2, 19.682453, -98.89093, '2026-04-25 11:26:31'),
(1349, 16, 2, 19.682453, -98.89093, '2026-04-25 11:26:34'),
(1350, 16, 2, 19.682453, -98.89093, '2026-04-25 11:26:38'),
(1351, 16, 2, 19.682453, -98.89093, '2026-04-25 11:26:42'),
(1352, 16, 2, 19.682453, -98.89093, '2026-04-25 11:26:46'),
(1353, 16, 2, 19.682451, -98.890945, '2026-04-25 11:26:50'),
(1354, 16, 2, 19.682451, -98.890945, '2026-04-25 11:26:54'),
(1355, 16, 2, 19.682451, -98.890945, '2026-04-25 11:26:58'),
(1356, 16, 2, 19.682453, -98.89093, '2026-04-25 11:27:01'),
(1357, 16, 2, 19.682453, -98.89093, '2026-04-25 11:27:05'),
(1358, 16, 2, 19.682453, -98.89093, '2026-04-25 11:27:09'),
(1359, 16, 2, 19.682453, -98.89093, '2026-04-25 11:27:13'),
(1360, 16, 2, 19.682453, -98.89093, '2026-04-25 11:27:17'),
(1361, 16, 2, 19.682453, -98.89093, '2026-04-25 11:27:21'),
(1362, 16, 2, 19.682453, -98.89093, '2026-04-25 11:27:25'),
(1363, 16, 2, 19.682453, -98.89093, '2026-04-25 11:27:29'),
(1364, 16, 2, 19.682453, -98.89093, '2026-04-25 11:27:32'),
(1365, 16, 2, 19.682453, -98.89093, '2026-04-25 11:27:36'),
(1366, 16, 2, 19.682453, -98.89093, '2026-04-25 11:27:39'),
(1367, 16, 2, 19.682453, -98.89093, '2026-04-25 11:27:43'),
(1368, 16, 2, 19.682453, -98.890945, '2026-04-25 11:27:48'),
(1369, 16, 2, 19.682453, -98.890945, '2026-04-25 11:27:51'),
(1370, 16, 2, 19.682453, -98.890945, '2026-04-25 11:27:55'),
(1371, 16, 2, 19.682453, -98.89093, '2026-04-25 11:28:00'),
(1372, 16, 2, 19.682453, -98.89093, '2026-04-25 11:28:03'),
(1373, 16, 2, 19.682453, -98.89093, '2026-04-25 11:28:07'),
(1374, 16, 2, 19.682453, -98.89093, '2026-04-25 11:28:12'),
(1375, 16, 2, 19.682453, -98.89093, '2026-04-25 11:28:15'),
(1376, 16, 2, 19.682453, -98.89093, '2026-04-25 11:28:19'),
(1377, 16, 2, 19.682457, -98.890915, '2026-04-25 11:28:24'),
(1378, 16, 2, 19.682457, -98.890915, '2026-04-25 11:28:27'),
(1379, 16, 2, 19.682457, -98.890915, '2026-04-25 11:28:31'),
(1380, 16, 2, 19.682453, -98.89093, '2026-04-25 11:28:36'),
(1381, 16, 2, 19.682453, -98.89093, '2026-04-25 11:28:39'),
(1382, 16, 2, 19.682453, -98.89093, '2026-04-25 11:28:43'),
(1383, 16, 2, 19.682453, -98.89093, '2026-04-25 11:28:48'),
(1384, 16, 2, 19.682453, -98.89093, '2026-04-25 11:28:50'),
(1385, 16, 2, 19.682453, -98.89093, '2026-04-25 11:28:54'),
(1386, 16, 2, 19.682459, -98.890923, '2026-04-25 11:28:58'),
(1387, 16, 2, 19.682459, -98.890923, '2026-04-25 11:29:02'),
(1388, 16, 2, 19.682459, -98.890923, '2026-04-25 11:29:06'),
(1389, 16, 2, 19.682459, -98.890923, '2026-04-25 11:29:10'),
(1390, 16, 2, 19.682459, -98.890923, '2026-04-25 11:29:13'),
(1391, 16, 2, 19.682459, -98.890923, '2026-04-25 11:29:17'),
(1392, 16, 2, 19.682459, -98.89093, '2026-04-25 11:29:21'),
(1393, 16, 2, 19.682459, -98.89093, '2026-04-25 11:29:25'),
(1394, 16, 2, 19.682459, -98.89093, '2026-04-25 11:29:29'),
(1395, 16, 2, 19.682459, -98.89093, '2026-04-25 11:29:33'),
(1396, 16, 2, 19.682459, -98.89093, '2026-04-25 11:29:37'),
(1397, 16, 2, 19.682459, -98.89093, '2026-04-25 11:29:41'),
(1398, 16, 2, 19.682459, -98.89093, '2026-04-25 11:29:45'),
(1399, 16, 2, 19.682459, -98.89093, '2026-04-25 11:29:49'),
(1400, 16, 2, 19.682459, -98.89093, '2026-04-25 11:29:53'),
(1401, 16, 2, 19.682457, -98.890938, '2026-04-25 11:29:57'),
(1402, 16, 2, 19.682457, -98.890938, '2026-04-25 11:30:01'),
(1403, 16, 2, 19.682457, -98.890938, '2026-04-25 11:30:05'),
(1404, 16, 2, 19.682457, -98.890938, '2026-04-25 11:30:09'),
(1405, 16, 2, 19.682457, -98.890938, '2026-04-25 11:30:13'),
(1406, 16, 2, 19.682457, -98.890938, '2026-04-25 11:30:17'),
(1407, 16, 2, 19.682453, -98.890945, '2026-04-25 11:30:21'),
(1408, 16, 2, 19.682453, -98.890945, '2026-04-25 11:30:25'),
(1409, 16, 2, 19.682453, -98.890945, '2026-04-25 11:30:29'),
(1410, 16, 2, 19.682451, -98.89093, '2026-04-25 11:30:33'),
(1411, 16, 2, 19.682451, -98.89093, '2026-04-25 11:30:37'),
(1412, 16, 2, 19.682451, -98.89093, '2026-04-25 11:30:41'),
(1413, 16, 2, 19.682451, -98.89093, '2026-04-25 11:30:45'),
(1414, 16, 2, 19.682451, -98.89093, '2026-04-25 11:30:49'),
(1415, 16, 2, 19.682451, -98.89093, '2026-04-25 11:30:53'),
(1416, 16, 2, 19.682451, -98.89093, '2026-04-25 11:30:57'),
(1417, 16, 2, 19.682451, -98.89093, '2026-04-25 11:31:01'),
(1418, 16, 2, 19.682451, -98.89093, '2026-04-25 11:31:05'),
(1419, 16, 2, 19.682451, -98.89093, '2026-04-25 11:31:09'),
(1420, 16, 2, 19.682451, -98.89093, '2026-04-25 11:31:13'),
(1421, 16, 2, 19.682451, -98.89093, '2026-04-25 11:31:17');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `tracking_shares`
--

CREATE TABLE `tracking_shares` (
  `id` int(11) NOT NULL,
  `pedido_id` int(11) NOT NULL,
  `created_by` int(11) NOT NULL,
  `token_hash` char(64) NOT NULL,
  `expires_at` datetime NOT NULL,
  `revoked_at` datetime DEFAULT NULL,
  `last_access_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `tracking_shares`
--

INSERT INTO `tracking_shares` (`id`, `pedido_id`, `created_by`, `token_hash`, `expires_at`, `revoked_at`, `last_access_at`, `created_at`) VALUES
(1, 9, 4, '9fd0e1e014d1220b9e2f7d5e63b4d4db82bc3841c4350de714bf94f3911f1693', '2026-04-01 09:13:51', '2026-03-31 19:14:11', NULL, '2026-03-31 19:13:51'),
(2, 9, 4, '1605f1c9b15417cfc0774d139ee9d8e3b88011545993188630c6b0a7ec904e05', '2026-04-01 09:14:15', '2026-03-31 19:14:23', NULL, '2026-03-31 19:14:15'),
(3, 9, 4, '693bd039a5c484bb7b35cd1c8b51c57ffcb525852200a6a700a99eca8a227fb1', '2026-04-01 09:14:23', '2026-03-31 19:15:02', '2026-03-31 19:14:56', '2026-03-31 19:14:23'),
(4, 9, 4, 'b1d504da6f2363e1d65a176cc69f2f2334f1dfad1b013d164cf49b1314a5b289', '2026-04-01 09:24:15', '2026-03-31 19:25:01', '2026-03-31 19:24:59', '2026-03-31 19:24:15'),
(5, 11, 4, '9b8f103f250a909fb705be5fd70248dc635d7d71939432e78ba423e2f8f30bfe', '2026-04-09 21:59:45', '2026-04-09 10:00:30', '2026-04-09 10:00:25', '2026-04-09 09:59:45'),
(6, 11, 4, 'af64c2d22efe83904a647bfb636c348fb66db64507caae324170d591caf5ce75', '2026-04-10 23:06:50', NULL, NULL, '2026-04-10 11:06:50'),
(7, 14, 1, 'ac35876e82f307c9bb4d5af8d23ece9e94712c3cef34c8f9d5a40ef83d0ebc53', '2026-04-20 05:22:42', NULL, NULL, '2026-04-19 17:22:42'),
(8, 15, 1, 'caf0fd17996d65576b3eec19ea37ca692626c690d30917b483aba8abf6eac0ad', '2026-04-20 05:46:45', NULL, '2026-04-19 17:46:45', '2026-04-19 17:46:45'),
(9, 15, 1, 'f2c19f6db28a44bca86b683fc588ed8c6fae331dfaf3cf6feeb194094b582803', '2026-04-20 23:54:09', NULL, '2026-04-20 11:54:09', '2026-04-20 11:54:09'),
(10, 16, 1, '44a92c11aafe384211d0565bf856380f83bac63cdd0f77f704c61a341fea2476', '2026-04-20 23:54:12', NULL, '2026-04-20 11:54:13', '2026-04-20 11:54:12'),
(11, 17, 1, '58c0f910f2c23b234086343fa7c6963bd61792d638f615acc8f84ad878a5e90f', '2026-04-21 02:33:48', NULL, '2026-04-20 15:12:31', '2026-04-20 14:33:48'),
(12, 21, 1, 'ad14e62391af1e9cc376b1b09c58bf885cb54242d0dc084534bdff76004f0d0d', '2026-04-24 07:28:10', NULL, '2026-04-23 19:40:54', '2026-04-23 19:28:10'),
(13, 21, 1, '67e438a1df08600890c27ff4814dc44577479f56e8452c7b64d0908dc47145ed', '2026-04-25 01:38:11', NULL, '2026-04-24 13:38:26', '2026-04-24 13:38:11'),
(14, 21, 1, '18d3fad2b5aa6a423edd6bdd861819f00ea37a21ec65e53bf651baff30af2559', '2026-04-25 21:27:25', NULL, '2026-04-25 09:27:26', '2026-04-25 09:27:25');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `transporte_lineas`
--

CREATE TABLE `transporte_lineas` (
  `id` int(11) NOT NULL,
  `nombre` varchar(120) NOT NULL,
  `capacidad_kg` int(11) DEFAULT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `transporte_lineas`
--

INSERT INTO `transporte_lineas` (`id`, `nombre`, `capacidad_kg`, `activo`, `created_at`) VALUES
(1, 'Linea Norte', 12000, 1, '2026-04-16 17:56:06'),
(2, 'Linea Centro', 10000, 1, '2026-04-16 17:56:06'),
(3, 'Linea Golfo', 8000, 1, '2026-04-16 17:56:06');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `usuarios`
--

CREATE TABLE `usuarios` (
  `id` int(11) NOT NULL,
  `nombre` varchar(120) NOT NULL,
  `email` varchar(180) NOT NULL,
  `password` varchar(255) NOT NULL,
  `rol` enum('administrador','inventario','distribucion','operador','cliente') NOT NULL,
  `domicilio` varchar(255) DEFAULT NULL,
  `edad` tinyint(3) UNSIGNED DEFAULT NULL,
  `telefono` varchar(20) DEFAULT NULL,
  `lat` double DEFAULT NULL,
  `lng` double DEFAULT NULL,
  `zona_radio` double DEFAULT 50,
  `activo` tinyint(1) DEFAULT 1,
  `ultimo_acceso` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `usuarios`
--

INSERT INTO `usuarios` (`id`, `nombre`, `email`, `password`, `rol`, `domicilio`, `edad`, `telefono`, `lat`, `lng`, `zona_radio`, `activo`, `ultimo_acceso`, `created_at`) VALUES
(1, 'Admin Principal', 'admin@demo.com', '$2y$10$8UL9LNio3aTeb8NHeajUo.Z6VIB1YhuEU6HWA2jK4mIRFsVqHBOf.', 'administrador', 'Av. Insurgentes Sur 123, CDMX', 35, '5511223344', 19.4326, -99.1332, 9999, 1, '2026-04-25 11:46:54', '2026-03-23 16:44:38'),
(2, 'Carlos López', 'operador@demo.com', '$2y$10$SY3tQ1cUOwl4qbvXGSq5e.hwuSzaM5YMzRPbLkpHmCdlIIMIjAcfe', 'operador', 'Calle Sonora 45, Col. Roma, CDMX', 28, '5571075067', 19.682451, -98.89093, 15, 1, '2026-04-25 11:20:37', '2026-03-23 16:44:38'),
(3, 'Ana Martínez', 'operador2@demo.com', '$2y$10$E/EUkp93cIXY4JGSsbTgAujmfeywHRwgjZaBvokH/6oE.1dPFlmxW', 'operador', 'Blvd. Manuel Ávila Camacho 32, GDL', 31, '3312345678', 19.7, -98.98, 15, 1, NULL, '2026-03-23 16:44:38'),
(4, 'Juan Cliente', 'cliente@demo.com', '$2y$10$x8b2dV/yIiBKQCugACLqx.weL7.4Fhd2TPMJnWze4/hMsn3pknWfa', 'cliente', 'Av. Álvaro Obregón 88, Col. Roma, CDMX', 25, '5522334455', 19.7, -98.98, 0, 1, '2026-04-25 11:39:15', '2026-03-23 16:44:38'),
(5, 'Artega Muñoz', 'arteaga@gmail.co', '$2y$10$uvMcsrkBjAg50gm7CcuKMuRtbN3pkhI.HHV/xEGXASVNb6Vc6jrAG', 'cliente', 'Ojo de agua', 25, '55 2343 1726', 20.2690825, -97.5251407, 50, 1, '2026-03-23 17:40:36', '2026-03-23 17:20:42'),
(6, 'Coordinacion Inventario', 'inventario@demo.com', '$2y$10$q5ioETEISog2RcmMFU1hi.V78ii8fyU8kmvgSFJCvTv8Wuj9towS2', 'inventario', 'Centro logistico NexusPanel', 30, '5500000000', 19.4326, -99.1332, 9999, 1, '2026-04-25 11:39:45', '2026-04-17 18:07:38');

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `auditoria_eventos`
--
ALTER TABLE `auditoria_eventos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_ae_usuario` (`usuario_id`),
  ADD KEY `idx_ae_modulo` (`modulo`),
  ADD KEY `idx_ae_fecha` (`created_at`);

--
-- Indices de la tabla `cfdi_eventos`
--
ALTER TABLE `cfdi_eventos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_cfdi_pedido` (`pedido_id`),
  ADD KEY `idx_cfdi_estado` (`estado`);

--
-- Indices de la tabla `chat`
--
ALTER TABLE `chat`
  ADD PRIMARY KEY (`id`),
  ADD KEY `pedido_id` (`pedido_id`),
  ADD KEY `usuario_id` (`usuario_id`);

--
-- Indices de la tabla `chat_ayuda_operador`
--
ALTER TABLE `chat_ayuda_operador`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_chat_ayuda_pair` (`operador_id`,`admin_id`,`id`);

--
-- Indices de la tabla `cliente_producto_historial`
--
ALTER TABLE `cliente_producto_historial`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `ux_cliente_producto` (`cliente_id`,`producto_id`),
  ADD KEY `idx_historial_cliente` (`cliente_id`),
  ADD KEY `idx_historial_producto` (`producto_id`);

--
-- Indices de la tabla `especies`
--
ALTER TABLE `especies`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `nombre` (`nombre`),
  ADD UNIQUE KEY `slug` (`slug`);

--
-- Indices de la tabla `inventario_movimientos`
--
ALTER TABLE `inventario_movimientos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_im_producto` (`producto_id`),
  ADD KEY `idx_im_fecha` (`created_at`),
  ADD KEY `idx_im_ref` (`referencia_tipo`,`referencia_id`),
  ADD KEY `fk_im_user` (`created_by`);

--
-- Indices de la tabla `login_throttles`
--
ALTER TABLE `login_throttles`
  ADD PRIMARY KEY (`bucket`);

--
-- Indices de la tabla `notificaciones_eventos`
--
ALTER TABLE `notificaciones_eventos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_ne_pedido` (`pedido_id`),
  ADD KEY `idx_ne_cliente` (`cliente_id`),
  ADD KEY `idx_ne_evento` (`evento`);

--
-- Indices de la tabla `operador_reabastos`
--
ALTER TABLE `operador_reabastos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_operador_reabastos_op` (`operador_id`);

--
-- Indices de la tabla `pedidos`
--
ALTER TABLE `pedidos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `cliente_id` (`cliente_id`),
  ADD KEY `operador_id` (`operador_id`),
  ADD KEY `idx_pedidos_tipo` (`tipo_pedido`),
  ADD KEY `idx_pedidos_prioridad` (`prioridad`),
  ADD KEY `idx_pedidos_fecha_programada` (`fecha_programada`);

--
-- Indices de la tabla `pedido_evidencias`
--
ALTER TABLE `pedido_evidencias`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_pe_pedido` (`pedido_id`);

--
-- Indices de la tabla `pedido_historial_estados`
--
ALTER TABLE `pedido_historial_estados`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_phe_pedido` (`pedido_id`),
  ADD KEY `idx_phe_estado` (`estado`),
  ADD KEY `fk_phe_usuario` (`usuario_id`);

--
-- Indices de la tabla `pedido_items`
--
ALTER TABLE `pedido_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `pedido_id` (`pedido_id`),
  ADD KEY `producto_id` (`producto_id`);

--
-- Indices de la tabla `productos`
--
ALTER TABLE `productos`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `codigo` (`codigo`),
  ADD KEY `idx_productos_especie` (`especie_id`),
  ADD KEY `idx_productos_subcategoria` (`subcategoria`),
  ADD KEY `idx_productos_lote_activo` (`lote_activo_id`);

--
-- Indices de la tabla `producto_lotes`
--
ALTER TABLE `producto_lotes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `ux_lote_producto_numero` (`producto_id`,`lote_numero`),
  ADD KEY `idx_lote_producto` (`producto_id`);

--
-- Indices de la tabla `subcategorias_catalogo`
--
ALTER TABLE `subcategorias_catalogo`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `ux_subcat_especie_slug` (`especie_id`,`slug`),
  ADD KEY `idx_subcat_especie` (`especie_id`);

--
-- Indices de la tabla `subcategorias_globales`
--
ALTER TABLE `subcategorias_globales`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `tracking`
--
ALTER TABLE `tracking`
  ADD PRIMARY KEY (`id`),
  ADD KEY `pedido_id` (`pedido_id`),
  ADD KEY `operador_id` (`operador_id`);

--
-- Indices de la tabla `tracking_shares`
--
ALTER TABLE `tracking_shares`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `token_hash` (`token_hash`),
  ADD KEY `idx_tracking_shares_pedido_active` (`pedido_id`,`revoked_at`,`expires_at`),
  ADD KEY `created_by` (`created_by`);

--
-- Indices de la tabla `transporte_lineas`
--
ALTER TABLE `transporte_lineas`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `nombre` (`nombre`);

--
-- Indices de la tabla `usuarios`
--
ALTER TABLE `usuarios`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `auditoria_eventos`
--
ALTER TABLE `auditoria_eventos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=24;

--
-- AUTO_INCREMENT de la tabla `cfdi_eventos`
--
ALTER TABLE `cfdi_eventos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `chat`
--
ALTER TABLE `chat`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=68;

--
-- AUTO_INCREMENT de la tabla `chat_ayuda_operador`
--
ALTER TABLE `chat_ayuda_operador`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `cliente_producto_historial`
--
ALTER TABLE `cliente_producto_historial`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=197;

--
-- AUTO_INCREMENT de la tabla `especies`
--
ALTER TABLE `especies`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=37501;

--
-- AUTO_INCREMENT de la tabla `inventario_movimientos`
--
ALTER TABLE `inventario_movimientos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT de la tabla `notificaciones_eventos`
--
ALTER TABLE `notificaciones_eventos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=52;

--
-- AUTO_INCREMENT de la tabla `operador_reabastos`
--
ALTER TABLE `operador_reabastos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `pedidos`
--
ALTER TABLE `pedidos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT de la tabla `pedido_evidencias`
--
ALTER TABLE `pedido_evidencias`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `pedido_historial_estados`
--
ALTER TABLE `pedido_historial_estados`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=25;

--
-- AUTO_INCREMENT de la tabla `pedido_items`
--
ALTER TABLE `pedido_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=58;

--
-- AUTO_INCREMENT de la tabla `productos`
--
ALTER TABLE `productos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=30;

--
-- AUTO_INCREMENT de la tabla `producto_lotes`
--
ALTER TABLE `producto_lotes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=30;

--
-- AUTO_INCREMENT de la tabla `subcategorias_catalogo`
--
ALTER TABLE `subcategorias_catalogo`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `subcategorias_globales`
--
ALTER TABLE `subcategorias_globales`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT de la tabla `tracking`
--
ALTER TABLE `tracking`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1422;

--
-- AUTO_INCREMENT de la tabla `tracking_shares`
--
ALTER TABLE `tracking_shares`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT de la tabla `transporte_lineas`
--
ALTER TABLE `transporte_lineas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=631;

--
-- AUTO_INCREMENT de la tabla `usuarios`
--
ALTER TABLE `usuarios`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `auditoria_eventos`
--
ALTER TABLE `auditoria_eventos`
  ADD CONSTRAINT `fk_ae_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL;

--
-- Filtros para la tabla `cfdi_eventos`
--
ALTER TABLE `cfdi_eventos`
  ADD CONSTRAINT `fk_cfdi_pedido` FOREIGN KEY (`pedido_id`) REFERENCES `pedidos` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `chat`
--
ALTER TABLE `chat`
  ADD CONSTRAINT `chat_ibfk_1` FOREIGN KEY (`pedido_id`) REFERENCES `pedidos` (`id`),
  ADD CONSTRAINT `chat_ibfk_2` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`);

--
-- Filtros para la tabla `cliente_producto_historial`
--
ALTER TABLE `cliente_producto_historial`
  ADD CONSTRAINT `fk_historial_cliente` FOREIGN KEY (`cliente_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_historial_producto` FOREIGN KEY (`producto_id`) REFERENCES `productos` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `inventario_movimientos`
--
ALTER TABLE `inventario_movimientos`
  ADD CONSTRAINT `fk_im_producto` FOREIGN KEY (`producto_id`) REFERENCES `productos` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_im_user` FOREIGN KEY (`created_by`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL;

--
-- Filtros para la tabla `notificaciones_eventos`
--
ALTER TABLE `notificaciones_eventos`
  ADD CONSTRAINT `fk_ne_cliente` FOREIGN KEY (`cliente_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_ne_pedido` FOREIGN KEY (`pedido_id`) REFERENCES `pedidos` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `operador_reabastos`
--
ALTER TABLE `operador_reabastos`
  ADD CONSTRAINT `fk_operador_reabastos_usuario` FOREIGN KEY (`operador_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `pedidos`
--
ALTER TABLE `pedidos`
  ADD CONSTRAINT `pedidos_ibfk_1` FOREIGN KEY (`cliente_id`) REFERENCES `usuarios` (`id`),
  ADD CONSTRAINT `pedidos_ibfk_2` FOREIGN KEY (`operador_id`) REFERENCES `usuarios` (`id`);

--
-- Filtros para la tabla `pedido_evidencias`
--
ALTER TABLE `pedido_evidencias`
  ADD CONSTRAINT `fk_pe_pedido` FOREIGN KEY (`pedido_id`) REFERENCES `pedidos` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `pedido_historial_estados`
--
ALTER TABLE `pedido_historial_estados`
  ADD CONSTRAINT `fk_phe_pedido` FOREIGN KEY (`pedido_id`) REFERENCES `pedidos` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_phe_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL;

--
-- Filtros para la tabla `pedido_items`
--
ALTER TABLE `pedido_items`
  ADD CONSTRAINT `pedido_items_ibfk_1` FOREIGN KEY (`pedido_id`) REFERENCES `pedidos` (`id`),
  ADD CONSTRAINT `pedido_items_ibfk_2` FOREIGN KEY (`producto_id`) REFERENCES `productos` (`id`);

--
-- Filtros para la tabla `tracking`
--
ALTER TABLE `tracking`
  ADD CONSTRAINT `tracking_ibfk_1` FOREIGN KEY (`pedido_id`) REFERENCES `pedidos` (`id`),
  ADD CONSTRAINT `tracking_ibfk_2` FOREIGN KEY (`operador_id`) REFERENCES `usuarios` (`id`);

--
-- Filtros para la tabla `tracking_shares`
--
ALTER TABLE `tracking_shares`
  ADD CONSTRAINT `tracking_shares_ibfk_1` FOREIGN KEY (`pedido_id`) REFERENCES `pedidos` (`id`),
  ADD CONSTRAINT `tracking_shares_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `usuarios` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
