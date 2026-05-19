-- Tablas para el módulo de Logística Inteligente (NexusPanel)

-- Asegurar que productos tenga peso (para cálculo de carga) y atributos de seguridad
ALTER TABLE `productos` ADD COLUMN IF NOT EXISTS `peso_kg` DECIMAL(10,2) DEFAULT 25.00;
ALTER TABLE `productos` ADD COLUMN IF NOT EXISTS `es_peligroso` TINYINT(1) DEFAULT 0;
ALTER TABLE `productos` ADD COLUMN IF NOT EXISTS `categoria` VARCHAR(50) DEFAULT 'General';

-- Asegurar que pedidos tenga destino simplificado para el demo
ALTER TABLE `pedidos` ADD COLUMN IF NOT EXISTS `destino_demo` VARCHAR(100) DEFAULT 'QRO';
ALTER TABLE `pedidos` ADD COLUMN IF NOT EXISTS `area_flujo` ENUM('ventas','embarque','logistica','entrega') DEFAULT 'ventas';
ALTER TABLE `pedidos` ADD COLUMN IF NOT EXISTS `ruta_punto` INT DEFAULT 0; -- Para Point 1, Point 2, etc.

CREATE TABLE IF NOT EXISTS `logistica_lineas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(100) NOT NULL,
  `prioridad` int(11) DEFAULT 1, -- Basada en costo (1 = Mayor prioridad/Menor costo)
  `rastreo_tiempo_real` tinyint(1) DEFAULT 0,
  `horario_carga_inicio` time DEFAULT '08:00:00',
  `horario_carga_fin` time DEFAULT '18:00:00',
  `dias_operacion` varchar(50) DEFAULT 'Lunes-Sábado',
  `promedio_carga_min` int(11) DEFAULT 60,
  `requiere_cita` tinyint(1) DEFAULT 1,
  `anticipacion_agenda_hrs` int(11) DEFAULT 24,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `logistica_unidades` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `linea_id` int(11) NOT NULL,
  `tipo` varchar(50) NOT NULL, -- torton, rabón, tráiler, full, camioneta
  `tipo_caja` varchar(50) NOT NULL, -- seca, refrigerada, plataforma
  `capacidad_kg` int(11) NOT NULL,
  `peso_minimo` int(11) DEFAULT 0,
  `mercancia_permitida` varchar(255) DEFAULT 'General',
  PRIMARY KEY (`id`),
  FOREIGN KEY (`linea_id`) REFERENCES `logistica_lineas`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `logistica_rutas` (
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
  FOREIGN KEY (`linea_id`) REFERENCES `logistica_lineas`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `logistica_citas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `pedido_id` int(11) DEFAULT NULL,
  `pl_folio` varchar(50) DEFAULT NULL, -- Para el demo con PLs de Excel
  `linea_id` int(11) NOT NULL,
  `operador_id` int(11) DEFAULT NULL, -- Operador asignado
  `fecha_cita` datetime NOT NULL,
  `carga_kg` int(11) DEFAULT 0,
  `estado` enum('programada','en_proceso','completada','cancelada') DEFAULT 'programada',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`linea_id`) REFERENCES `logistica_lineas`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`operador_id`) REFERENCES `usuarios`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Limpiar tablas para re-inserción de demo (Uso DELETE para evitar errores de FK)
SET FOREIGN_KEY_CHECKS = 0;
DELETE FROM `logistica_rutas`;
DELETE FROM `logistica_unidades`;
DELETE FROM `logistica_lineas`;
-- Reiniciar contadores para que los IDs empiecen desde 1
ALTER TABLE `logistica_lineas` AUTO_INCREMENT = 1;
ALTER TABLE `logistica_unidades` AUTO_INCREMENT = 1;
ALTER TABLE `logistica_rutas` AUTO_INCREMENT = 1;
SET FOREIGN_KEY_CHECKS = 1;

-- 1. LÍNEAS DE TRANSPORTE (Incluyendo ROJAS del documento)
INSERT INTO `logistica_lineas` (`nombre`, `prioridad`, `rastreo_tiempo_real`, `horario_carga_inicio`, `horario_carga_fin`, `dias_operacion`, `promedio_carga_min`, `requiere_cita`, `anticipacion_agenda_hrs`) VALUES 
('Transportes Rojas', 1, 1, '08:00:00', '18:00:00', 'Lunes-Sábado', 60, 1, 12),
('Loxagon',          2, 1, '08:00:00', '18:00:00', 'Lunes-Sábado', 60, 1, 24),
('Bisonte',          3, 0, '08:00:00', '18:00:00', 'Lunes-Sábado', 60, 1, 12),
('Tres Guerras',     4, 1, '08:00:00', '18:00:00', 'Lunes-Sábado', 60, 1, 48);

-- 2. UNIDADES (Basadas en el documento: 1 TON, 3.5 TON, TORTON)
INSERT INTO `logistica_unidades` (`linea_id`, `tipo`, `tipo_caja`, `capacidad_kg`, `peso_minimo`, `mercancia_permitida`) VALUES 
(1, '1 TON',    'Seca', 1000,  0,    'General, Alimentos'),
(1, '3.5 TON',  'Seca', 3500,  1000, 'General'),
(1, 'TORTON',   'Seca', 15000, 5000, 'General, Peligrosa'),
(2, 'TORTON',   'Seca', 15000, 2000, 'General'),
(3, 'Tráiler',  'Seca', 25000, 5000, 'General'),
(4, 'Full',     'Seca', 50000, 10000,'General');

-- 3. RUTAS Y COSTOS REALES (QRO, CDMX, TIZAYUCA)
INSERT INTO `logistica_rutas` (`linea_id`, `origen`, `destino`, `frecuencia`, `tarifa_fija`, `tiempo_estimado_hrs`, `cumplimiento_pct`) VALUES 
(1, 'ALMACEN', 'QRO',      'diaria', 3200.00, 4, 99),
(1, 'ALMACEN', 'CDMX',     'diaria', 4100.00, 6, 97),
(1, 'ALMACEN', 'TIZAYUCA', 'diaria', 5200.00, 8, 95),
(2, 'ALMACEN', 'QRO',      'diaria', 3500.00, 4, 98);

-- 4. PEDIDOS REALES DEL DOCUMENTO (Área: Logística)
-- PL 1076267 - RANCHO EL RINCON
INSERT INTO `pedidos` (id, cliente_id, estado, total, destino_demo, area_flujo) VALUES 
(10, 5, 'pendiente', 15000, 'QRO', 'logistica');
-- PL 1076046 - GAQSA
INSERT INTO `pedidos` (id, cliente_id, estado, total, destino_demo, area_flujo) VALUES 
(11, 5, 'pendiente', 22000, 'QRO', 'logistica');
-- PL 1076376 - PILGRIMS QRO
INSERT INTO `pedidos` (id, cliente_id, estado, total, destino_demo, area_flujo) VALUES 
(12, 5, 'pendiente', 18000, 'QRO', 'logistica');
-- PL 1076911 - COM (Consolidado CDMX)
INSERT INTO `pedidos` (id, cliente_id, estado, total, destino_demo, area_flujo) VALUES 
(13, 5, 'pendiente', 45000, 'CDMX', 'logistica');
-- PL 1076826 - NUTRIX
INSERT INTO `pedidos` (id, cliente_id, estado, total, destino_demo, area_flujo) VALUES 
(14, 5, 'pendiente', 12000, 'QRO', 'logistica');
-- PL 1076827 - AVIGRUPO
INSERT INTO `pedidos` (id, cliente_id, estado, total, destino_demo, area_flujo) VALUES 
(15, 5, 'pendiente', 35000, 'TIZAYUCA', 'logistica');

-- 5. ITEMS CON PESOS REALES (Para que el algoritmo de consolidación funcione)
-- RANCHO EL RINCON: 1000 KG
INSERT INTO `pedido_items` (pedido_id, producto_id, cantidad, precio_unit) VALUES (10, 1, 40, 375); 
-- GAQSA: 12000 KG (Consolidado varios productos)
INSERT INTO `pedido_items` (pedido_id, producto_id, cantidad, precio_unit) VALUES (11, 2, 480, 100); 
-- PILGRIMS QRO: 2000 KG
INSERT INTO `pedido_items` (pedido_id, producto_id, cantidad, precio_unit) VALUES (12, 3, 80, 225); 
-- COM CDMX: 9000 KG (Consolidado)
INSERT INTO `pedido_items` (pedido_id, producto_id, cantidad, precio_unit) VALUES (13, 4, 360, 125); 
-- NUTRIX QRO: 645 KG
INSERT INTO `pedido_items` (pedido_id, producto_id, cantidad, precio_unit) VALUES (14, 5, 26, 460); 
-- AVIGRUPO: 10000 KG
INSERT INTO `pedido_items` (pedido_id, producto_id, cantidad, precio_unit) VALUES (15, 6, 400, 87.5); 

-- Actualizar folios PL en citas para el demo visual
INSERT INTO `logistica_citas` (pl_folio, linea_id, fecha_cita, estado) VALUES 
('1076267', 1, '2026-05-11 08:00:00', 'completada'),
('1076046', 1, '2026-05-11 09:30:00', 'completada'),
('1076376', 1, '2026-05-11 11:00:00', 'completada');

 



