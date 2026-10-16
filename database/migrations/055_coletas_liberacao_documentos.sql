-- Liberação manual do MTR oficial e do CDF no portal do gerador.
-- NULL = retido no portal Well. O arquivo permanece em storage.

ALTER TABLE coletas
    ADD COLUMN mtr_oficial_liberado_em DATETIME NULL DEFAULT NULL AFTER cdf_tipo,
    ADD COLUMN cdf_liberado_em DATETIME NULL DEFAULT NULL AFTER mtr_oficial_liberado_em;
