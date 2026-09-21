-- Solicitações de coleta pelo portal gerador (aprovação admin)
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS `coleta_solicitacoes` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `operadora_id` int unsigned NOT NULL DEFAULT 1,
  `cliente_id` int unsigned NOT NULL,
  `cliente_usuario_id` int unsigned NOT NULL,
  `data_desejada` date NOT NULL,
  `status` enum('pendente','aprovada','recusada','cancelada') NOT NULL DEFAULT 'pendente',
  `tipo` enum('inclusa','extra') NOT NULL DEFAULT 'inclusa',
  `motivo_gerador` varchar(2000) DEFAULT NULL,
  `resposta_admin` varchar(2000) DEFAULT NULL,
  `valor_cobranca_extra` decimal(10,2) DEFAULT NULL,
  `data_aprovada` date DEFAULT NULL,
  `aprovado_por_usuario_id` int unsigned DEFAULT NULL,
  `aprovado_em` datetime DEFAULT NULL,
  `coleta_id` int unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_cs_cliente_status` (`cliente_id`, `status`),
  KEY `idx_cs_operadora_status_data` (`operadora_id`, `status`, `data_desejada`),
  CONSTRAINT `fk_cs_cliente` FOREIGN KEY (`cliente_id`) REFERENCES `clientes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_cs_cliente_usuario` FOREIGN KEY (`cliente_usuario_id`) REFERENCES `cliente_usuarios` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_cs_aprovador` FOREIGN KEY (`aprovado_por_usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_cs_coleta` FOREIGN KEY (`coleta_id`) REFERENCES `coletas` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
