-- Token API WS do destinador (receber MTR / CDF).
-- Fallback: operadoras.sinir_integration_token → .env SINIR_INTEGRATION_TOKEN.

ALTER TABLE `destinadores`
  ADD COLUMN `sinir_integration_token` varchar(512) DEFAULT NULL
    COMMENT 'Token API WS do destinador no portal MTR'
    AFTER `sinir_cod_unidade`;
