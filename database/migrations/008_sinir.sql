-- Well Eco — Integração SINIR (Fase A: preparação)
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- Colunas SINIR em coletas (status denormalizado para listagem rápida)
ALTER TABLE `coletas`
  ADD COLUMN `sinir_man_numero` varchar(30) DEFAULT NULL AFTER `finalized_at`,
  ADD COLUMN `sinir_codigo_barras` varchar(60) DEFAULT NULL AFTER `sinir_man_numero`,
  ADD COLUMN `sinir_status` enum('pendente','enviado','erro') DEFAULT NULL AFTER `sinir_codigo_barras`,
  ADD COLUMN `sinir_enviado_em` timestamp NULL DEFAULT NULL AFTER `sinir_status`,
  ADD KEY `idx_coletas_sinir_status` (`sinir_status`);

-- Códigos SINIR no catálogo de resíduos (mapeamento para salvarManifestoLote)
ALTER TABLE `tipos_residuos`
  ADD COLUMN `tra_codigo` int unsigned DEFAULT NULL AFTER `cod_ibama`,
  ADD COLUMN `tie_codigo` int unsigned DEFAULT NULL AFTER `tra_codigo`,
  ADD COLUMN `tia_codigo` int unsigned DEFAULT NULL AFTER `tie_codigo`,
  ADD COLUMN `cla_codigo` int unsigned DEFAULT NULL AFTER `tia_codigo`,
  ADD COLUMN `uni_codigo` int unsigned DEFAULT NULL AFTER `cla_codigo`;

-- Histórico de tentativas de envio (auditoria / reenvio — Fase B)
CREATE TABLE IF NOT EXISTS `sinir_envios` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `coleta_id` int unsigned NOT NULL,
  `tentativa` tinyint unsigned NOT NULL DEFAULT 1,
  `status` enum('pendente','enviado','erro') NOT NULL DEFAULT 'pendente',
  `request_payload` json DEFAULT NULL,
  `response_payload` json DEFAULT NULL,
  `mensagem_erro` varchar(500) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_sinir_envios_coleta` (`coleta_id`),
  KEY `idx_sinir_envios_status` (`status`),
  CONSTRAINT `fk_sinir_envios_coleta` FOREIGN KEY (`coleta_id`) REFERENCES `coletas` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
