-- Responsável técnico do CDF (lista própria no portal) é distinto do
-- responsável pelo recebimento do MTR.

ALTER TABLE `destinadores`
  ADD COLUMN `sinir_responsavel_cdf` varchar(255) DEFAULT NULL
    COMMENT 'Nome exato do responsável técnico na emissão de CDF'
    AFTER `responsavel`;

-- Unidade Well 135551: recebimento aceita "WIDMER TRINDADE";
-- a lista de CDF dessa unidade usa o nome civil completo.
UPDATE `destinadores`
SET `sinir_responsavel_cdf` = 'WIDMER TRINDADE DE BEM'
WHERE `sinir_cod_unidade` = 135551
  AND TRIM(`responsavel`) = 'WIDMER TRINDADE'
  AND (`sinir_responsavel_cdf` IS NULL OR TRIM(`sinir_responsavel_cdf`) = '');
