-- Well Admin — operadora_id DEFAULT 1 em dados tenant (backfill automático)
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- usuarios: email único por operadora
ALTER TABLE `usuarios`
  ADD COLUMN `operadora_id` int unsigned NOT NULL DEFAULT 1 AFTER `id`,
  ADD KEY `fk_usuarios_operadora` (`operadora_id`);

UPDATE `usuarios` SET `operadora_id` = 1 WHERE `operadora_id` = 0 OR `operadora_id` IS NULL;

ALTER TABLE `usuarios`
  DROP INDEX `uk_usuarios_email`,
  ADD UNIQUE KEY `uk_usuarios_operadora_email` (`operadora_id`, `email`),
  ADD CONSTRAINT `fk_usuarios_operadora` FOREIGN KEY (`operadora_id`) REFERENCES `operadoras` (`id`);

ALTER TABLE `clientes`
  ADD COLUMN `operadora_id` int unsigned NOT NULL DEFAULT 1 AFTER `id`,
  ADD KEY `idx_clientes_operadora` (`operadora_id`),
  ADD CONSTRAINT `fk_clientes_operadora` FOREIGN KEY (`operadora_id`) REFERENCES `operadoras` (`id`);

ALTER TABLE `veiculos`
  ADD COLUMN `operadora_id` int unsigned NOT NULL DEFAULT 1 AFTER `id`,
  ADD KEY `idx_veiculos_operadora` (`operadora_id`),
  ADD CONSTRAINT `fk_veiculos_operadora` FOREIGN KEY (`operadora_id`) REFERENCES `operadoras` (`id`);

ALTER TABLE `rotas`
  ADD COLUMN `operadora_id` int unsigned NOT NULL DEFAULT 1 AFTER `id`,
  ADD KEY `idx_rotas_operadora` (`operadora_id`),
  ADD CONSTRAINT `fk_rotas_operadora` FOREIGN KEY (`operadora_id`) REFERENCES `operadoras` (`id`);

ALTER TABLE `planos`
  ADD COLUMN `operadora_id` int unsigned NOT NULL DEFAULT 1 AFTER `id`,
  ADD KEY `idx_planos_operadora` (`operadora_id`),
  ADD CONSTRAINT `fk_planos_operadora` FOREIGN KEY (`operadora_id`) REFERENCES `operadoras` (`id`);

ALTER TABLE `coletas`
  ADD COLUMN `operadora_id` int unsigned NOT NULL DEFAULT 1 AFTER `id`,
  ADD KEY `idx_coletas_operadora` (`operadora_id`),
  ADD CONSTRAINT `fk_coletas_operadora` FOREIGN KEY (`operadora_id`) REFERENCES `operadoras` (`id`);

ALTER TABLE `rota_atribuicoes`
  ADD COLUMN `operadora_id` int unsigned NOT NULL DEFAULT 1 AFTER `id`,
  ADD KEY `idx_ra_operadora` (`operadora_id`),
  ADD CONSTRAINT `fk_ra_operadora` FOREIGN KEY (`operadora_id`) REFERENCES `operadoras` (`id`);

ALTER TABLE `rota_dia_ordem`
  ADD COLUMN `operadora_id` int unsigned NOT NULL DEFAULT 1 AFTER `id`,
  ADD KEY `idx_rdo_operadora` (`operadora_id`),
  ADD CONSTRAINT `fk_rdo_operadora` FOREIGN KEY (`operadora_id`) REFERENCES `operadoras` (`id`);

ALTER TABLE `plano_itens`
  ADD COLUMN `operadora_id` int unsigned NOT NULL DEFAULT 1 AFTER `id`,
  ADD KEY `idx_plano_itens_operadora` (`operadora_id`),
  ADD CONSTRAINT `fk_plano_itens_operadora` FOREIGN KEY (`operadora_id`) REFERENCES `operadoras` (`id`);

ALTER TABLE `inter_cobrancas`
  ADD COLUMN `operadora_id` int unsigned NOT NULL DEFAULT 1 AFTER `id`,
  ADD KEY `idx_inter_operadora` (`operadora_id`),
  ADD CONSTRAINT `fk_inter_operadora` FOREIGN KEY (`operadora_id`) REFERENCES `operadoras` (`id`);

-- Sequência MTR por operadora
CREATE TABLE IF NOT EXISTS `coleta_sequencia_new` (
  `operadora_id` int unsigned NOT NULL,
  `ultimo_mtr` int unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`operadora_id`),
  CONSTRAINT `fk_coleta_seq_operadora` FOREIGN KEY (`operadora_id`) REFERENCES `operadoras` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `coleta_sequencia_new` (`operadora_id`, `ultimo_mtr`)
SELECT 1, COALESCE((SELECT MAX(`ultimo_mtr`) FROM `coleta_sequencia` WHERE `id` = 1), 0)
ON DUPLICATE KEY UPDATE `ultimo_mtr` = VALUES(`ultimo_mtr`);

INSERT INTO `coleta_sequencia_new` (`operadora_id`, `ultimo_mtr`)
SELECT 1, COALESCE((SELECT MAX(`numero_mtr`) FROM `coletas`), 0)
WHERE NOT EXISTS (SELECT 1 FROM `coleta_sequencia_new` WHERE `operadora_id` = 1);

DROP TABLE IF EXISTS `coleta_sequencia`;
RENAME TABLE `coleta_sequencia_new` TO `coleta_sequencia`;

SET FOREIGN_KEY_CHECKS = 1;
