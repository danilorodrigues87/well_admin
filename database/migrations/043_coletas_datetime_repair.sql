-- Corrige datetime zero/inválido em coletas (bloqueia ALTER TABLE no MySQL 8+).
-- IMPORTANTE: execute o arquivo INTEIRO de uma vez (phpMyAdmin → colar tudo → Executar).
-- Não rode só o UPDATE isolado — o SET sql_mode precisa estar na mesma sessão.

SET NAMES utf8mb4;

SET @old_sql_mode = @@SESSION.sql_mode;
SET SESSION sql_mode = '';

UPDATE `coletas`
SET `finalized_at` = NULL
WHERE `finalized_at` IS NOT NULL
  AND (`finalized_at` = '0000-00-00 00:00:00' OR `finalized_at` < '1000-01-01 00:00:00');

UPDATE `coletas`
SET `sinir_enviado_em` = NULL
WHERE `sinir_enviado_em` IS NOT NULL
  AND (`sinir_enviado_em` = '0000-00-00 00:00:00' OR `sinir_enviado_em` < '1000-01-01 00:00:00');

UPDATE `coletas`
SET `data_coleta` = NULL
WHERE `data_coleta` IS NOT NULL
  AND (`data_coleta` = '0000-00-00' OR `data_coleta` < '1000-01-01');

UPDATE `coletas`
SET `data_recebimento` = NULL
WHERE `data_recebimento` IS NOT NULL
  AND (`data_recebimento` = '0000-00-00' OR `data_recebimento` < '1000-01-01');

UPDATE `coletas`
SET `doc_referencia` = NULL
WHERE `doc_referencia` IS NOT NULL
  AND (`doc_referencia` = '0000-00-00' OR `doc_referencia` < '1000-01-01');

SET SESSION sql_mode = @old_sql_mode;
