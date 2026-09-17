-- Rastreamento frota (web/app) + status de parada na rota do dia
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS `frota_posicoes` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `operadora_id` int unsigned NOT NULL DEFAULT 1,
  `usuario_id` int unsigned NOT NULL,
  `veiculo_id` int unsigned DEFAULT NULL,
  `latitude` decimal(10,7) NOT NULL,
  `longitude` decimal(10,7) NOT NULL,
  `accuracy_m` decimal(8,2) DEFAULT NULL,
  `heading` decimal(6,2) DEFAULT NULL,
  `speed_mps` decimal(8,3) DEFAULT NULL,
  `fonte` enum('web','app','rastreador') NOT NULL DEFAULT 'web',
  `registrado_em` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_fp_operadora_registrado` (`operadora_id`,`registrado_em`),
  KEY `idx_fp_usuario_registrado` (`usuario_id`,`registrado_em`),
  CONSTRAINT `fk_fp_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `rota_dia_parada_status` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `operadora_id` int unsigned NOT NULL DEFAULT 1,
  `coletor_id` int unsigned NOT NULL,
  `data` date NOT NULL,
  `cliente_id` int unsigned NOT NULL,
  `status` enum('pendente','coletado','pulado') NOT NULL DEFAULT 'pendente',
  `observacao` varchar(500) DEFAULT NULL,
  `atualizado_em` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_rdps_operadora_coletor_data_cliente` (`operadora_id`,`coletor_id`,`data`,`cliente_id`),
  KEY `fk_rdps_coletor` (`coletor_id`),
  KEY `fk_rdps_cliente` (`cliente_id`),
  CONSTRAINT `fk_rdps_coletor` FOREIGN KEY (`coletor_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_rdps_cliente` FOREIGN KEY (`cliente_id`) REFERENCES `clientes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
