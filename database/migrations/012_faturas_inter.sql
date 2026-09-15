-- Campos de faturamento mensal em inter_cobrancas

ALTER TABLE inter_cobrancas
    ADD COLUMN competencia CHAR(7) NULL AFTER cliente_id,
    ADD COLUMN valor_calculado DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER valor_nominal,
    ADD COLUMN detalhes_json JSON NULL AFTER payload_response,
    ADD COLUMN multa_mora_json JSON NULL AFTER detalhes_json,
    ADD COLUMN observacao_ajuste VARCHAR(255) NULL AFTER multa_mora_json,
    ADD COLUMN email_enviado_em DATETIME NULL AFTER observacao_ajuste,
    ADD COLUMN email_erro VARCHAR(255) NULL AFTER email_enviado_em;

ALTER TABLE inter_cobrancas
    ADD UNIQUE KEY uk_inter_cliente_competencia (cliente_id, competencia);
