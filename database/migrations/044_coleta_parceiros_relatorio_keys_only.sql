-- Use quando a 042 já criou as colunas em `coletas` e falhou só nos índices/FK (#1292).
-- Pré-requisito: 043_coletas_datetime_repair.sql (arquivo inteiro, com SET sql_mode).
-- Ignore #1060 "coluna duplicada" se tentar rodar a 042_finish de novo.

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ── coletas: índices + FKs (colunas numero_relatorio, transportadora_id, etc. já devem existir) ──
ALTER TABLE `coletas`
  ADD KEY `fk_coletas_transportadora` (`transportadora_id`),
  ADD KEY `fk_coletas_destinador` (`destinador_id`),
  ADD UNIQUE KEY `uk_coletas_operadora_relatorio` (`operadora_id`, `numero_relatorio`),
  ADD CONSTRAINT `fk_coletas_transportadora` FOREIGN KEY (`transportadora_id`) REFERENCES `transportadoras` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_coletas_destinador` FOREIGN KEY (`destinador_id`) REFERENCES `destinadores` (`id`) ON DELETE SET NULL;

-- ── usuarios (ignore #1060 se transportadora_id já existir) ──
ALTER TABLE `usuarios`
  ADD COLUMN `transportadora_id` int unsigned DEFAULT NULL AFTER `funcao_id`,
  ADD KEY `fk_usuarios_transportadora` (`transportadora_id`),
  ADD CONSTRAINT `fk_usuarios_transportadora` FOREIGN KEY (`transportadora_id`) REFERENCES `transportadoras` (`id`) ON DELETE SET NULL;

-- ── coleta_sequencia (ignore #1060 se ultimo_relatorio já existir) ──
ALTER TABLE `coleta_sequencia`
  ADD COLUMN `ultimo_relatorio` int unsigned NOT NULL DEFAULT 0 AFTER `ultimo_mtr`;

UPDATE `coleta_sequencia` cs
SET cs.`ultimo_relatorio` = GREATEST(cs.`ultimo_mtr`, COALESCE((
  SELECT MAX(c.`numero_mtr`) FROM `coletas` c WHERE c.`operadora_id` = cs.`operadora_id`
), 0))
WHERE cs.`ultimo_relatorio` = 0;

UPDATE `coletas` SET `numero_relatorio` = `numero_mtr`
WHERE `status` = 'finalizada'
  AND `numero_relatorio` IS NULL
  AND `numero_mtr` IS NOT NULL;

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
