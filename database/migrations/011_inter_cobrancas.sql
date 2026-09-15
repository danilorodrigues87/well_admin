-- Cobranças BolePix emitidas via API Banco Inter

CREATE TABLE IF NOT EXISTS inter_cobrancas (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    cliente_id INT UNSIGNED NULL,
    codigo_solicitacao VARCHAR(100) NOT NULL,
    seu_numero VARCHAR(60) NULL,
    valor_nominal DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    data_vencimento DATE NOT NULL,
    status VARCHAR(40) NOT NULL DEFAULT 'EMITIDA',
    linha_digitavel VARCHAR(80) NULL,
    pix_copia_cola TEXT NULL,
    pdf_path VARCHAR(255) NULL,
    payload_request JSON NULL,
    payload_response JSON NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uk_inter_codigo (codigo_solicitacao),
    KEY idx_inter_cliente (cliente_id),
    KEY idx_inter_status (status),
    CONSTRAINT fk_inter_cliente FOREIGN KEY (cliente_id) REFERENCES clientes (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
