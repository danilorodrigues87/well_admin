-- Well Eco — plano_itens: saldo compartilhado + FK obrigatória (após backfill)
-- 1) php database/scripts/backfill_plano_itens_tipo.php
-- 2) aplicar este arquivo
SET NAMES utf8mb4;

ALTER TABLE `plano_itens`
  ADD COLUMN IF NOT EXISTS `saldo_compartilhado` tinyint(1) NOT NULL DEFAULT 0 AFTER `valor_excedente`;

-- Remover duplicatas (plano_id + tipo_residuo_id) mantendo menor id
DELETE pi FROM plano_itens pi
INNER JOIN (
  SELECT plano_id, tipo_residuo_id, MIN(id) AS keep_id
  FROM plano_itens
  WHERE tipo_residuo_id IS NOT NULL
  GROUP BY plano_id, tipo_residuo_id
  HAVING COUNT(*) > 1
) d ON d.plano_id = pi.plano_id AND d.tipo_residuo_id = pi.tipo_residuo_id AND pi.id <> d.keep_id;

ALTER TABLE `plano_itens` DROP FOREIGN KEY `fk_plano_itens_tipo`;

ALTER TABLE `plano_itens`
  MODIFY COLUMN `tipo_residuo_id` int unsigned NOT NULL,
  DROP COLUMN `nome`,
  DROP COLUMN `cod_ibama`,
  ADD UNIQUE KEY `uk_plano_tipo` (`plano_id`, `tipo_residuo_id`);

ALTER TABLE `plano_itens`
  ADD CONSTRAINT `fk_plano_itens_tipo` FOREIGN KEY (`tipo_residuo_id`) REFERENCES `tipos_residuos` (`id`) ON DELETE RESTRICT;
