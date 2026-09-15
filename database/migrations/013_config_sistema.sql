-- Configurações editáveis pelo painel (single-tenant)

CREATE TABLE IF NOT EXISTS config_sistema (
    chave VARCHAR(80) NOT NULL,
    valor TEXT NOT NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (chave)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO config_sistema (chave, valor) VALUES
    ('cobranca.multa_tipo', 'PERCENTUAL'),
    ('cobranca.multa_taxa', '2.00'),
    ('cobranca.multa_valor', '0.00'),
    ('cobranca.mora_tipo', 'TAXAMENSAL'),
    ('cobranca.mora_taxa', '1.00'),
    ('cobranca.mora_valor', '0.00');
