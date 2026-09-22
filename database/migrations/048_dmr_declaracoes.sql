-- DMR — apoio operacional (competência por gerador; integração SINIR futura)
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS `dmr_declaracoes` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `operadora_id` int unsigned NOT NULL DEFAULT 1,
  `cliente_id` int unsigned NOT NULL,
  `competencia` char(7) NOT NULL COMMENT 'YYYY-MM',
  `status` enum('rascunho','fechada') NOT NULL DEFAULT 'rascunho',
  `observacao` text,
  `tot_coletas` int unsigned NOT NULL DEFAULT 0,
  `tot_kg` decimal(12,3) NOT NULL DEFAULT 0.000,
  `tot_com_mtr` int unsigned NOT NULL DEFAULT 0,
  `tot_com_cdf` int unsigned NOT NULL DEFAULT 0,
  `fechada_em` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_dmr_operadora_cliente_comp` (`operadora_id`,`cliente_id`,`competencia`),
  KEY `fk_dmr_cliente` (`cliente_id`),
  CONSTRAINT `fk_dmr_cliente` FOREIGN KEY (`cliente_id`) REFERENCES `clientes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `modulos` (`slug`, `label`, `grupo`, `ordem`) VALUES
('dmr', 'DMR Geradores', 'Operação', 25)
ON DUPLICATE KEY UPDATE `label` = VALUES(`label`), `grupo` = VALUES(`grupo`), `ordem` = VALUES(`ordem`);

INSERT IGNORE INTO `funcao_modulos` (`funcao_id`, `modulo_id`)
SELECT 1, id FROM `modulos` WHERE slug = 'dmr';

INSERT IGNORE INTO `funcao_modulos` (`funcao_id`, `modulo_id`)
SELECT 3, id FROM `modulos` WHERE slug = 'dmr';
