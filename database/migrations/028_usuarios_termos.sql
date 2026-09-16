-- Aceite de termos de uso — usuários admin
SET NAMES utf8mb4;

ALTER TABLE `usuarios`
  ADD COLUMN `termos_uso` tinyint(1) NOT NULL DEFAULT 0 AFTER `must_reset_password`,
  ADD COLUMN `termos_aceito_em` datetime DEFAULT NULL AFTER `termos_uso`,
  ADD COLUMN `termos_versao` varchar(16) DEFAULT NULL AFTER `termos_aceito_em`;
