-- Modelos de contrato por plano + campos comerciais/jurídicos
SET NAMES utf8mb4;

ALTER TABLE `planos`
  ADD COLUMN `contrato_modelo_tipo` varchar(32) NOT NULL DEFAULT 'GENERICO'
    COMMENT 'RSS_CLINICA, RSS_HOSPITAL, CLASSE_I_II, CLASSE_I, RECICLAVEIS, PNEUS, RCC_PADRAO, OBRA_GRANDE_PORTE, GENERICO'
    AFTER `contrato_obs_pontualidade`;

ALTER TABLE `clientes`
  ADD COLUMN `responsavel_cargo` varchar(120) DEFAULT NULL AFTER `responsavel`,
  ADD COLUMN `responsavel_cpf` varchar(14) DEFAULT NULL AFTER `responsavel_cargo`,
  ADD COLUMN `responsavel_rg` varchar(20) DEFAULT NULL AFTER `responsavel_cpf`;

ALTER TABLE `clientes_contratos`
  ADD COLUMN `taxa_adesao` decimal(10,2) DEFAULT NULL AFTER `valor_mensal`,
  ADD COLUMN `indice_reajuste` varchar(24) NOT NULL DEFAULT 'IPCA' AFTER `taxa_adesao`,
  ADD COLUMN `foro_cidade` varchar(80) NOT NULL DEFAULT 'Alta Floresta' AFTER `indice_reajuste`,
  ADD COLUMN `foro_uf` char(2) NOT NULL DEFAULT 'MT' AFTER `foro_cidade`,
  ADD COLUMN `multa_atraso_descricao` varchar(255) NOT NULL DEFAULT '3% ao dia sobre o valor em atraso'
    AFTER `juros_mora_pct_mes`,
  ADD COLUMN `aviso_previo_dias` int unsigned NOT NULL DEFAULT 30 AFTER `carencia_dias`,
  ADD COLUMN `multa_rescisao_texto` varchar(255) DEFAULT NULL AFTER `multa_cancelamento_pct`,
  ADD COLUMN `flags_json` json DEFAULT NULL AFTER `html_snapshot`;
