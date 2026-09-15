SET NAMES utf8mb4;

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
