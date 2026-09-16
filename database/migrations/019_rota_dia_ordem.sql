-- Ordem manual/otimizada da rota do dia por coletor
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS `rota_dia_ordem` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `coletor_id` int unsigned NOT NULL,
  `data` date NOT NULL,
  `cliente_id` int unsigned NOT NULL,
  `ordem` smallint unsigned NOT NULL,
  `origem` enum('otimizada','manual') NOT NULL DEFAULT 'otimizada',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_coletor_data_cliente` (`coletor_id`,`data`,`cliente_id`),
  KEY `fk_rdo_coletor` (`coletor_id`),
  KEY `fk_rdo_cliente` (`cliente_id`),
  CONSTRAINT `fk_rdo_coletor` FOREIGN KEY (`coletor_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_rdo_cliente` FOREIGN KEY (`cliente_id`) REFERENCES `clientes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
