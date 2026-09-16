-- Well Admin — config por operadora (multa/juros, chaves diversas)
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS `operadora_config` (
  `operadora_id` int unsigned NOT NULL,
  `chave` varchar(80) NOT NULL,
  `valor` text NOT NULL,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`operadora_id`, `chave`),
  CONSTRAINT `fk_operadora_config_operadora` FOREIGN KEY (`operadora_id`) REFERENCES `operadoras` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Copia config_sistema existente para operadora 1
INSERT IGNORE INTO `operadora_config` (`operadora_id`, `chave`, `valor`)
SELECT 1, `chave`, `valor` FROM `config_sistema`;
