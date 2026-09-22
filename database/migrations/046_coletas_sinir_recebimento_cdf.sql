-- Coletas Fase 3: recebimento SINIR + PDF (CDF light) no storage
SET NAMES utf8mb4;

ALTER TABLE `coletas`
  ADD COLUMN `sinir_recebido_em` timestamp NULL DEFAULT NULL AFTER `sinir_enviado_em`,
  ADD COLUMN `cdf_path` varchar(255) DEFAULT NULL COMMENT 'Relativo a storage/ (PDF MTR SINIR ou CDF manual)' AFTER `sinir_recebido_em`,
  ADD COLUMN `cdf_obtido_em` timestamp NULL DEFAULT NULL AFTER `cdf_path`;

ALTER TABLE `sinir_envios`
  MODIFY COLUMN `status` enum('pendente','enviado','erro','cancelado','consulta','recebido') NOT NULL DEFAULT 'pendente';
