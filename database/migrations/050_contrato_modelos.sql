-- Modelos jurídicos de contrato por operadora + snapshot comercial
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS `contrato_modelos` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `operadora_id` int unsigned NOT NULL DEFAULT 1,
  `slug` varchar(32) NOT NULL,
  `nome` varchar(120) NOT NULL,
  `versao` int unsigned NOT NULL DEFAULT 1,
  `titulo` varchar(255) NOT NULL,
  `body_html` mediumtext NOT NULL,
  `pricing_variant` varchar(32) NOT NULL DEFAULT 'FRANQUIA_KG',
  `flags_json` json DEFAULT NULL,
  `ativo` tinyint unsigned NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_contrato_modelos_op_slug_ver` (`operadora_id`, `slug`, `versao`),
  KEY `idx_contrato_modelos_op_slug_ativo` (`operadora_id`, `slug`, `ativo`),
  CONSTRAINT `fk_contrato_modelos_operadora` FOREIGN KEY (`operadora_id`) REFERENCES `operadoras` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `clientes_contratos`
  ADD COLUMN `contrato_modelo_id` int unsigned DEFAULT NULL AFTER `plano_id`,
  ADD COLUMN `contrato_modelo_versao` int unsigned DEFAULT NULL AFTER `contrato_modelo_id`,
  ADD COLUMN `comercial_snapshot_json` json DEFAULT NULL AFTER `flags_json`,
  ADD COLUMN `variables_json` json DEFAULT NULL AFTER `comercial_snapshot_json`,
  ADD COLUMN `encerrado_em` datetime DEFAULT NULL AFTER `assinado_por_cliente_usuario_id`,
  ADD COLUMN `encerrado_motivo` varchar(500) DEFAULT NULL AFTER `encerrado_em`,
  ADD COLUMN `encerrado_por_usuario_id` int unsigned DEFAULT NULL AFTER `encerrado_motivo`;

INSERT IGNORE INTO `operadora_config` (`operadora_id`, `chave`, `valor`) VALUES
  (1, 'contrato_endereco_sede', 'Avenida Industrial, Quadra nº 09, Lote nº 15, Distrito Industrial, Alta Floresta - MT, CEP 78580-000');
