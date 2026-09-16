-- Aceite de termos de uso — portal gerador
SET NAMES utf8mb4;

ALTER TABLE `cliente_usuarios`
  ADD COLUMN `termos_uso` tinyint(1) NOT NULL DEFAULT 0 AFTER `ativo`,
  ADD COLUMN `termos_aceito_em` datetime DEFAULT NULL AFTER `termos_uso`,
  ADD COLUMN `termos_versao` varchar(16) DEFAULT NULL AFTER `termos_aceito_em`;
