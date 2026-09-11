-- Well Eco — preparação ETL coletas legado (Etapa 5)
SET NAMES utf8mb4;

ALTER TABLE `coletas`
  ADD COLUMN `legacy_manifesto` int unsigned DEFAULT NULL COMMENT 'PK manifesto em well_antigo.coletas' AFTER `numero_mtr`,
  ADD UNIQUE KEY `uk_coletas_legacy_manifesto` (`legacy_manifesto`);
