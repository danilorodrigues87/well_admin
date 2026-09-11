-- Well Eco — Coletas / MTR (Etapa 3)
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE IF NOT EXISTS `coleta_sequencia` (
  `id` tinyint unsigned NOT NULL DEFAULT 1,
  `ultimo_mtr` int unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `coleta_sequencia` (`id`, `ultimo_mtr`) VALUES (1, 0);

CREATE TABLE IF NOT EXISTS `coletas` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `numero_mtr` int unsigned DEFAULT NULL,
  `cliente_id` int unsigned NOT NULL,
  `coletor_id` int unsigned NOT NULL,
  `veiculo_id` int unsigned DEFAULT NULL,
  `status` enum('rascunho','finalizada','cancelada') NOT NULL DEFAULT 'rascunho',
  `doc_referencia` date DEFAULT NULL,
  `data_coleta` date DEFAULT NULL,
  `hora` time DEFAULT NULL,
  `relatorio` varchar(1000) DEFAULT NULL,
  `situacao_recebimento` enum('pendente','recebido') NOT NULL DEFAULT 'pendente',
  `data_recebimento` date DEFAULT NULL,
  `tratamento` varchar(80) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `finalized_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_coletas_numero_mtr` (`numero_mtr`),
  KEY `fk_coletas_cliente` (`cliente_id`),
  KEY `fk_coletas_coletor` (`coletor_id`),
  KEY `fk_coletas_veiculo` (`veiculo_id`),
  KEY `idx_coletas_status_data` (`status`, `data_coleta`),
  CONSTRAINT `fk_coletas_cliente` FOREIGN KEY (`cliente_id`) REFERENCES `clientes` (`id`),
  CONSTRAINT `fk_coletas_coletor` FOREIGN KEY (`coletor_id`) REFERENCES `usuarios` (`id`),
  CONSTRAINT `fk_coletas_veiculo` FOREIGN KEY (`veiculo_id`) REFERENCES `veiculos` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `coleta_snapshot` (
  `coleta_id` int unsigned NOT NULL,
  `gerador_nome_fantasia` varchar(120) DEFAULT NULL,
  `gerador_razao_social` varchar(120) DEFAULT NULL,
  `gerador_cnpj` varchar(20) DEFAULT NULL,
  `gerador_endereco` varchar(255) DEFAULT NULL,
  `gerador_responsavel` varchar(120) DEFAULT NULL,
  `gerador_plano` varchar(120) DEFAULT NULL,
  `transportador_nome` varchar(120) DEFAULT NULL,
  `transportador_cnpj` varchar(20) DEFAULT NULL,
  `motorista_nome` varchar(120) DEFAULT NULL,
  `veiculo_descricao` varchar(120) DEFAULT NULL,
  `veiculo_placa` varchar(15) DEFAULT NULL,
  `destinador_nome` varchar(120) DEFAULT NULL,
  `destinador_cnpj` varchar(20) DEFAULT NULL,
  `destinador_endereco` varchar(255) DEFAULT NULL,
  `destinador_telefone` varchar(20) DEFAULT NULL,
  `destinador_responsavel` varchar(120) DEFAULT NULL,
  `observacao_destinador` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`coleta_id`),
  CONSTRAINT `fk_coleta_snapshot_coleta` FOREIGN KEY (`coleta_id`) REFERENCES `coletas` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `coleta_itens` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `coleta_id` int unsigned NOT NULL,
  `tipo_residuo_id` int unsigned DEFAULT NULL,
  `nome` varchar(120) NOT NULL,
  `classe_nome` varchar(80) DEFAULT NULL,
  `grupo_codigo` varchar(10) DEFAULT NULL,
  `cod_ibama` varchar(30) DEFAULT NULL,
  `quantidade` decimal(10,3) NOT NULL DEFAULT 0.000,
  `unidade` enum('kg','l','un') NOT NULL DEFAULT 'kg',
  PRIMARY KEY (`id`),
  KEY `fk_coleta_itens_coleta` (`coleta_id`),
  KEY `fk_coleta_itens_tipo` (`tipo_residuo_id`),
  CONSTRAINT `fk_coleta_itens_coleta` FOREIGN KEY (`coleta_id`) REFERENCES `coletas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_coleta_itens_tipo` FOREIGN KEY (`tipo_residuo_id`) REFERENCES `tipos_residuos` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `coleta_evidencias` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `coleta_id` int unsigned NOT NULL,
  `ordem` tinyint unsigned NOT NULL,
  `arquivo` varchar(255) NOT NULL,
  `mime` varchar(80) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_coleta_evid_ordem` (`coleta_id`, `ordem`),
  CONSTRAINT `fk_coleta_evid_coleta` FOREIGN KEY (`coleta_id`) REFERENCES `coletas` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
