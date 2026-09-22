-- Transportadoras, destinadores, número de relatório vs MTR SINIR
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE IF NOT EXISTS `transportadoras` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `operadora_id` int unsigned NOT NULL DEFAULT 1,
  `nome` varchar(120) NOT NULL,
  `cnpj` varchar(20) NOT NULL,
  `sinir_cod_unidade` int unsigned DEFAULT NULL,
  `sinir_integration_token` varchar(500) DEFAULT NULL,
  `is_padrao` tinyint(1) NOT NULL DEFAULT 0,
  `ativo` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_transportadoras_operadora` (`operadora_id`),
  KEY `idx_transportadoras_ativo` (`operadora_id`, `ativo`),
  CONSTRAINT `fk_transportadoras_operadora` FOREIGN KEY (`operadora_id`) REFERENCES `operadoras` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `destinadores` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `operadora_id` int unsigned NOT NULL DEFAULT 1,
  `nome` varchar(120) NOT NULL,
  `cnpj` varchar(20) NOT NULL,
  `endereco` varchar(255) DEFAULT NULL,
  `telefone` varchar(20) DEFAULT NULL,
  `responsavel` varchar(120) DEFAULT NULL,
  `sinir_cod_unidade` int unsigned DEFAULT NULL,
  `is_padrao` tinyint(1) NOT NULL DEFAULT 0,
  `ativo` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_destinadores_operadora` (`operadora_id`),
  KEY `idx_destinadores_ativo` (`operadora_id`, `ativo`),
  CONSTRAINT `fk_destinadores_operadora` FOREIGN KEY (`operadora_id`) REFERENCES `operadoras` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `coletas`
  ADD COLUMN `numero_relatorio` int unsigned DEFAULT NULL AFTER `numero_mtr`,
  ADD COLUMN `transportadora_id` int unsigned DEFAULT NULL AFTER `coletor_id`,
  ADD COLUMN `destinador_id` int unsigned DEFAULT NULL AFTER `transportadora_id`,
  ADD COLUMN `assinatura_cliente_path` varchar(255) DEFAULT NULL AFTER `relatorio`;

ALTER TABLE `coletas`
  ADD KEY `fk_coletas_transportadora` (`transportadora_id`),
  ADD KEY `fk_coletas_destinador` (`destinador_id`),
  ADD UNIQUE KEY `uk_coletas_operadora_relatorio` (`operadora_id`, `numero_relatorio`),
  ADD CONSTRAINT `fk_coletas_transportadora` FOREIGN KEY (`transportadora_id`) REFERENCES `transportadoras` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_coletas_destinador` FOREIGN KEY (`destinador_id`) REFERENCES `destinadores` (`id`) ON DELETE SET NULL;

ALTER TABLE `usuarios`
  ADD COLUMN `transportadora_id` int unsigned DEFAULT NULL AFTER `funcao_id`,
  ADD KEY `fk_usuarios_transportadora` (`transportadora_id`),
  ADD CONSTRAINT `fk_usuarios_transportadora` FOREIGN KEY (`transportadora_id`) REFERENCES `transportadoras` (`id`) ON DELETE SET NULL;

ALTER TABLE `coleta_sequencia`
  ADD COLUMN `ultimo_relatorio` int unsigned NOT NULL DEFAULT 0 AFTER `ultimo_mtr`;

-- Backfill sequência de relatório a partir do MTR interno
UPDATE `coleta_sequencia` SET `ultimo_relatorio` = GREATEST(`ultimo_mtr`, COALESCE((
  SELECT MAX(`numero_mtr`) FROM `coletas` c WHERE c.operadora_id = `coleta_sequencia`.operadora_id
), 0));

UPDATE `coletas` SET `numero_relatorio` = `numero_mtr`
WHERE `status` = 'finalizada'
  AND `numero_relatorio` IS NULL
  AND `numero_mtr` IS NOT NULL;

-- Seed Well (operadora 1) a partir de operadoras
INSERT INTO `transportadoras` (`operadora_id`, `nome`, `cnpj`, `sinir_cod_unidade`, `is_padrao`, `ativo`)
SELECT o.id,
  COALESCE(NULLIF(TRIM(o.transportador_nome), ''), 'Well Soluções Ambientais'),
  COALESCE(NULLIF(TRIM(o.transportador_cnpj), ''), '18.675.233/0001-50'),
  CAST(NULLIF(TRIM(o.sinir_unidade), '') AS UNSIGNED),
  1,
  1
FROM `operadoras` o
WHERE o.id = 1
  AND NOT EXISTS (SELECT 1 FROM `transportadoras` t WHERE t.operadora_id = 1 AND t.is_padrao = 1);

INSERT INTO `destinadores` (`operadora_id`, `nome`, `cnpj`, `endereco`, `telefone`, `responsavel`, `sinir_cod_unidade`, `is_padrao`, `ativo`)
SELECT o.id,
  COALESCE(NULLIF(TRIM(o.destinador_nome), ''), 'Well Soluções Ambientais'),
  COALESCE(NULLIF(TRIM(o.destinador_cnpj), ''), '18.675.233/0001-50'),
  o.destinador_endereco,
  o.destinador_telefone,
  o.destinador_responsavel,
  CAST(NULLIF(TRIM(o.sinir_unidade_destinador), '') AS UNSIGNED),
  1,
  1
FROM `operadoras` o
WHERE o.id = 1
  AND NOT EXISTS (SELECT 1 FROM `destinadores` d WHERE d.operadora_id = 1 AND d.is_padrao = 1);

UPDATE `usuarios` u
INNER JOIN `transportadoras` t ON t.operadora_id = u.operadora_id AND t.is_padrao = 1
INNER JOIN `funcoes` f ON f.id = u.funcao_id AND f.slug = 'coletor'
SET u.transportadora_id = t.id
WHERE u.transportadora_id IS NULL;

INSERT INTO `modulos` (`slug`, `label`, `grupo`, `ordem`) VALUES
('transportadoras', 'Transportadoras', 'Cadastros', 38),
('destinadores', 'Destinadores', 'Cadastros', 39)
ON DUPLICATE KEY UPDATE label = VALUES(label), grupo = VALUES(grupo), ordem = VALUES(ordem);

INSERT IGNORE INTO `funcao_modulos` (`funcao_id`, `modulo_id`)
SELECT f.id, m.id FROM funcoes f CROSS JOIN modulos m
WHERE f.is_admin = 1 AND m.slug IN ('transportadoras', 'destinadores');

SET FOREIGN_KEY_CHECKS = 1;
