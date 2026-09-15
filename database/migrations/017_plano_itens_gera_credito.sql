-- Resíduos recicláveis: desconto na mensalidade (ignora saldo incluso)
SET NAMES utf8mb4;

ALTER TABLE `plano_itens`
  ADD COLUMN `gera_credito` tinyint(1) NOT NULL DEFAULT 0
    COMMENT '1 = desconto = coletado × tarifa (ignora saldo)' AFTER `saldo_compartilhado`;

-- Legado: valor excedente negativo vira crédito com tarifa positiva
UPDATE `plano_itens`
SET `gera_credito` = 1, `valor_excedente` = ABS(`valor_excedente`)
WHERE `valor_excedente` < 0;
