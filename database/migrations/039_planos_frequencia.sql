-- Frequência estruturada de coletas no plano
SET NAMES utf8mb4;

ALTER TABLE `planos`
  ADD COLUMN `coletas_periodo_meses` tinyint unsigned DEFAULT NULL COMMENT 'Ex.: 3 = trimestre' AFTER `coletas_mensais`,
  ADD COLUMN `coletas_por_periodo` tinyint unsigned NOT NULL DEFAULT 1 AFTER `coletas_periodo_meses`;
