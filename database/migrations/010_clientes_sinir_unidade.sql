-- Well Eco — SINIR Fase B: código unidade do gerador (cliente) no portal MTR
SET NAMES utf8mb4;

ALTER TABLE `clientes`
  ADD COLUMN `sinir_cod_unidade` int unsigned DEFAULT NULL AFTER `cnpj`;
