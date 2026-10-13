-- Token API WS do gerador (por cliente). Necessário para emitir MTR na API nacional MMA/SIGOR.
-- .env SINIR_INTEGRATION_TOKEN continua sendo o token da Well (listas, recebimento, CDF).

ALTER TABLE `clientes`
  ADD COLUMN `sinir_integration_token` varchar(512) DEFAULT NULL
    COMMENT 'Token API WS gerado no portal MTR na unidade geradora do cliente'
    AFTER `sinir_cod_unidade`;
