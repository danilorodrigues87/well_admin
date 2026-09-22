-- Cliente que exige MTR (badge/filtro; não bloqueia relatório-only)
SET NAMES utf8mb4;

ALTER TABLE `clientes`
  ADD COLUMN `exige_mtr` tinyint(1) NOT NULL DEFAULT 0 AFTER `sinir_cod_unidade`;
