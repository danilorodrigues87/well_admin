-- SINIR: status cancelado (manifesto cancelado no portal nacional)
SET NAMES utf8mb4;

ALTER TABLE `coletas`
  MODIFY COLUMN `sinir_status` enum('pendente','enviado','erro','cancelado') DEFAULT NULL;

ALTER TABLE `sinir_envios`
  MODIFY COLUMN `status` enum('pendente','enviado','erro','cancelado','consulta') NOT NULL DEFAULT 'pendente';
