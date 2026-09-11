-- Well Eco — Cadastros base (Etapa 2)
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE IF NOT EXISTS `planos` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `nome` varchar(120) NOT NULL,
  `descricao` varchar(500) DEFAULT NULL,
  `valor_mensal` decimal(10,2) NOT NULL DEFAULT 0.00,
  `coletas_mensais` decimal(5,2) NOT NULL DEFAULT 0.00,
  `tipo` varchar(40) DEFAULT NULL,
  `ativo` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `tipos_residuos` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `nome` varchar(120) NOT NULL,
  `classe` varchar(30) DEFAULT NULL,
  `grupo` varchar(50) DEFAULT NULL,
  `cod_ibama` varchar(30) DEFAULT NULL,
  `ativo` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `veiculos` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `modelo` varchar(80) NOT NULL,
  `marca` varchar(80) NOT NULL,
  `ano` varchar(10) DEFAULT NULL,
  `cor` varchar(40) DEFAULT NULL,
  `placa` varchar(15) NOT NULL,
  `ativo` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_veiculos_placa` (`placa`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `rotas` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `nome` varchar(80) NOT NULL,
  `descricao` varchar(255) DEFAULT NULL,
  `ativo` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `clientes` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `nome_fantasia` varchar(120) NOT NULL,
  `razao_social` varchar(120) NOT NULL,
  `cnpj` varchar(20) DEFAULT NULL,
  `email` varchar(120) DEFAULT NULL,
  `telefone` varchar(20) DEFAULT NULL,
  `plano_id` int unsigned DEFAULT NULL,
  `status` enum('ativo','inativo','suspenso') NOT NULL DEFAULT 'ativo',
  `logradouro` varchar(120) DEFAULT NULL,
  `numero` varchar(10) DEFAULT NULL,
  `bairro` varchar(80) DEFAULT NULL,
  `cep` varchar(10) DEFAULT NULL,
  `cidade` varchar(80) DEFAULT NULL,
  `uf` char(2) DEFAULT NULL,
  `responsavel` varchar(120) DEFAULT NULL,
  `telefone_resp` varchar(20) DEFAULT NULL,
  `proxima_coleta` date DEFAULT NULL,
  `saldo_residuo` decimal(10,2) NOT NULL DEFAULT 0.00,
  `prioridade` enum('normal','urgente') NOT NULL DEFAULT 'normal',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_clientes_plano` (`plano_id`),
  CONSTRAINT `fk_clientes_plano` FOREIGN KEY (`plano_id`) REFERENCES `planos` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `rota_atribuicoes` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `rota_id` int unsigned NOT NULL,
  `cliente_id` int unsigned NOT NULL,
  `coletor_id` int unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_rota_cliente` (`rota_id`,`cliente_id`),
  KEY `fk_ra_cliente` (`cliente_id`),
  KEY `fk_ra_coletor` (`coletor_id`),
  CONSTRAINT `fk_ra_rota` FOREIGN KEY (`rota_id`) REFERENCES `rotas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ra_cliente` FOREIGN KEY (`cliente_id`) REFERENCES `clientes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ra_coletor` FOREIGN KEY (`coletor_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
