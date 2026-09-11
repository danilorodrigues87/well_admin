-- Well Eco — Itens de plano (saldo incluso + valor excedente por resíduo)
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE IF NOT EXISTS `plano_itens` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `plano_id` int unsigned NOT NULL,
  `tipo_residuo_id` int unsigned DEFAULT NULL,
  `nome` varchar(120) NOT NULL,
  `cod_ibama` varchar(30) DEFAULT NULL,
  `saldo_incluso` decimal(10,3) NOT NULL DEFAULT 0.000,
  `unidade` enum('kg','l','un') NOT NULL DEFAULT 'kg',
  `valor_excedente` decimal(10,2) NOT NULL DEFAULT 0.00,
  `ordem` smallint unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `fk_plano_itens_plano` (`plano_id`),
  KEY `fk_plano_itens_tipo` (`tipo_residuo_id`),
  CONSTRAINT `fk_plano_itens_plano` FOREIGN KEY (`plano_id`) REFERENCES `planos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_plano_itens_tipo` FOREIGN KEY (`tipo_residuo_id`) REFERENCES `tipos_residuos` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
