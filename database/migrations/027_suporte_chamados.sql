-- Suporte por ticket (operadora ↔ equipe admin)
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS `chamados` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `numero` varchar(24) NOT NULL,
  `operadora_id` int unsigned NOT NULL DEFAULT 1,
  `usuario_id` int unsigned NOT NULL,
  `categoria` varchar(32) NOT NULL DEFAULT 'duvida',
  `assunto` varchar(160) NOT NULL,
  `status` varchar(32) NOT NULL DEFAULT 'aberto',
  `prioridade` varchar(16) NOT NULL DEFAULT 'normal',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `fechado_em` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_chamados_numero` (`numero`),
  KEY `idx_chamados_operadora_status` (`operadora_id`, `status`),
  KEY `idx_chamados_status_updated` (`status`, `updated_at`),
  CONSTRAINT `fk_chamados_operadora` FOREIGN KEY (`operadora_id`) REFERENCES `operadoras` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `chamado_mensagens` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `chamado_id` int unsigned NOT NULL,
  `autor_tipo` enum('usuario','admin') NOT NULL,
  `autor_id` int unsigned NOT NULL,
  `mensagem` text NOT NULL,
  `anexo_path` varchar(255) DEFAULT NULL,
  `anexo_nome` varchar(160) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_chamado_mensagens_chamado` (`chamado_id`, `created_at`),
  CONSTRAINT `fk_chamado_mensagens_chamado`
    FOREIGN KEY (`chamado_id`) REFERENCES `chamados` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
