-- client_uuid: idempotência create (app offline / retry)
ALTER TABLE `coletas`
  ADD COLUMN `client_uuid` char(36) DEFAULT NULL AFTER `id`,
  ADD UNIQUE KEY `uk_coletas_operadora_client_uuid` (`operadora_id`, `client_uuid`);
