-- Portal / app gerador — credenciais separadas de usuarios (staff)
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS `cliente_usuarios` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `operadora_id` int unsigned NOT NULL DEFAULT 1,
  `cliente_id` int unsigned NOT NULL,
  `nome` varchar(120) NOT NULL,
  `email` varchar(120) NOT NULL,
  `senha_hash` varchar(255) NOT NULL,
  `ativo` tinyint(1) NOT NULL DEFAULT 1,
  `ultimo_login` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_cliente_usuarios_operadora_email` (`operadora_id`, `email`),
  KEY `fk_cu_cliente` (`cliente_id`),
  KEY `idx_cu_cliente_ativo` (`cliente_id`, `ativo`),
  CONSTRAINT `fk_cu_operadora` FOREIGN KEY (`operadora_id`) REFERENCES `operadoras` (`id`),
  CONSTRAINT `fk_cu_cliente` FOREIGN KEY (`cliente_id`) REFERENCES `clientes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
