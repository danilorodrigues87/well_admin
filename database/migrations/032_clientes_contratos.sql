-- Contratos comerciais operadora ↔ cliente gerador
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS `clientes_contratos` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `operadora_id` int unsigned NOT NULL DEFAULT 1,
  `cliente_id` int unsigned NOT NULL,
  `plano_id` int unsigned NOT NULL,
  `numero` varchar(24) NOT NULL,
  `valor_mensal` decimal(10,2) NOT NULL DEFAULT 0.00,
  `qtd_meses` int unsigned NOT NULL DEFAULT 12,
  `data_inicio` date NOT NULL,
  `data_fim` date NOT NULL,
  `dia_vencimento` tinyint unsigned NOT NULL DEFAULT 10,
  `primeira_competencia` char(7) NOT NULL,
  `multa_atraso_pct` decimal(5,2) NOT NULL DEFAULT 2.00,
  `juros_mora_pct_mes` decimal(5,2) NOT NULL DEFAULT 1.00,
  `multa_cancelamento_pct` decimal(5,2) NOT NULL DEFAULT 10.00,
  `carencia_dias` int unsigned NOT NULL DEFAULT 5,
  `status` enum('rascunho','aguardando_assinatura','ativo','encerrado','cancelado') NOT NULL DEFAULT 'rascunho',
  `html_snapshot` mediumtext NULL,
  `assinado_em` datetime DEFAULT NULL,
  `assinado_por_cliente_usuario_id` int unsigned DEFAULT NULL,
  `criado_por_usuario_id` int unsigned NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_clientes_contratos_numero` (`numero`),
  KEY `idx_cc_operadora_cliente` (`operadora_id`, `cliente_id`, `status`),
  CONSTRAINT `fk_cc_operadora` FOREIGN KEY (`operadora_id`) REFERENCES `operadoras` (`id`),
  CONSTRAINT `fk_cc_cliente` FOREIGN KEY (`cliente_id`) REFERENCES `clientes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_cc_plano` FOREIGN KEY (`plano_id`) REFERENCES `planos` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `clientes`
  ADD COLUMN `contrato_ativo_id` int unsigned DEFAULT NULL AFTER `plano_id`;
