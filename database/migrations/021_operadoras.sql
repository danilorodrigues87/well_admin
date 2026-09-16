-- Well Admin — multitenancy: tabela operadoras (Well = id 1)
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE IF NOT EXISTS `operadoras` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `nome` varchar(120) NOT NULL,
  `razao_social` varchar(120) NOT NULL,
  `cnpj` varchar(20) DEFAULT NULL,
  `email` varchar(120) DEFAULT NULL,
  `telefone` varchar(20) DEFAULT NULL,
  `logradouro` varchar(120) DEFAULT NULL,
  `numero` varchar(10) DEFAULT NULL,
  `bairro` varchar(80) DEFAULT NULL,
  `cep` varchar(10) DEFAULT NULL,
  `cidade` varchar(80) DEFAULT NULL,
  `uf` char(2) DEFAULT NULL,
  `nome_fantasia` varchar(120) DEFAULT NULL,
  `nome_curto` varchar(40) DEFAULT NULL,
  `ativo` tinyint(1) NOT NULL DEFAULT 1,
  `modulos_liberados` json DEFAULT NULL COMMENT 'NULL = todos os módulos',
  `transportador_nome` varchar(120) DEFAULT NULL,
  `transportador_cnpj` varchar(20) DEFAULT NULL,
  `destinador_nome` varchar(120) DEFAULT NULL,
  `destinador_cnpj` varchar(20) DEFAULT NULL,
  `destinador_endereco` varchar(255) DEFAULT NULL,
  `destinador_telefone` varchar(20) DEFAULT NULL,
  `destinador_responsavel` varchar(120) DEFAULT NULL,
  `sinir_integration_token` varchar(500) DEFAULT NULL,
  `sinir_unidade` varchar(20) DEFAULT NULL,
  `sinir_unidade_destinador` varchar(20) DEFAULT NULL,
  `sinir_cnpj` varchar(20) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_operadoras_ativo` (`ativo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed mínimo; detalhes via database/scripts/seed_operadora_well.php (.env)
INSERT IGNORE INTO `operadoras` (
  `id`, `nome`, `razao_social`, `cnpj`, `nome_fantasia`, `nome_curto`, `ativo`
) VALUES (
  1,
  'Well Soluções Ambientais',
  'Well Soluções Ambientais',
  '18.675.233/0001-50',
  'Well Soluções Ambientais',
  'Well S.A.',
  1
);

SET FOREIGN_KEY_CHECKS = 1;
