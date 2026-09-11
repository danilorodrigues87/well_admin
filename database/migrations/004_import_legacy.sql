-- Well Eco — Importação inicial de cadastros do banco legado (well_antigo)
-- Executar uma vez: mysql -u root well_admin < database/migrations/004_import_legacy.sql

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- Planos (preserva IDs para vínculo com clientes)
INSERT INTO `planos` (`id`, `nome`, `descricao`, `valor_mensal`, `coletas_mensais`, `tipo`, `ativo`)
SELECT
    p.ID,
    p.plano,
    NULLIF(TRIM(p.descricao), ''),
    p.valor_mes,
    0.00,
    NULLIF(TRIM(p.tipo), ''),
    1
FROM well_antigo.planos p
ON DUPLICATE KEY UPDATE
    nome = VALUES(nome),
    descricao = VALUES(descricao),
    valor_mensal = VALUES(valor_mensal),
    tipo = VALUES(tipo);

-- Tipos de resíduos
INSERT INTO `tipos_residuos` (`id`, `nome`, `classe`, `grupo`, `cod_ibama`, `ativo`)
SELECT
    t.id,
    t.nome,
    NULLIF(TRIM(t.classe), ''),
    NULLIF(TRIM(t.grupo), ''),
    NULLIF(TRIM(t.cod_ibama), ''),
    1
FROM well_antigo.tipos_de_residuos t
ON DUPLICATE KEY UPDATE
    nome = VALUES(nome),
    classe = VALUES(classe),
    grupo = VALUES(grupo),
    cod_ibama = VALUES(cod_ibama);

-- Veículos
INSERT INTO `veiculos` (`id`, `modelo`, `marca`, `ano`, `cor`, `placa`, `ativo`)
SELECT
    v.id_veiculo,
    v.modelo,
    v.marca,
    NULLIF(TRIM(v.ano), ''),
    NULLIF(TRIM(v.cor), ''),
    v.placa,
    1
FROM well_antigo.veiculos v
ON DUPLICATE KEY UPDATE
    modelo = VALUES(modelo),
    marca = VALUES(marca),
    ano = VALUES(ano),
    cor = VALUES(cor),
    placa = VALUES(placa);

-- Rotas (lista_rotas no legado)
INSERT INTO `rotas` (`id`, `nome`, `descricao`, `ativo`)
SELECT
    r.id,
    r.nome,
    NULL,
    1
FROM well_antigo.lista_rotas r
ON DUPLICATE KEY UPDATE nome = VALUES(nome);

-- Clientes
INSERT INTO `clientes` (
    `id`, `nome_fantasia`, `razao_social`, `cnpj`, `email`, `telefone`,
    `plano_id`, `status`, `logradouro`, `numero`, `bairro`, `cep`, `cidade`, `uf`,
    `responsavel`, `telefone_resp`, `proxima_coleta`, `saldo_residuo`, `prioridade`
)
SELECT
    c.ID,
    c.nome_fantasia,
    c.razao_social,
    NULLIF(TRIM(c.cnpj), ''),
    NULLIF(TRIM(c.email), ''),
    NULLIF(TRIM(c.contato_emp), ''),
    CASE WHEN c.id_plano > 0 THEN c.id_plano ELSE NULL END,
    CASE
        WHEN LOWER(TRIM(c.status)) = 'desativado' THEN 'inativo'
        ELSE 'ativo'
    END,
    NULLIF(TRIM(c.end_emp), ''),
    NULLIF(TRIM(c.num_emp), ''),
    NULLIF(TRIM(c.bairro_emp), ''),
    NULLIF(TRIM(c.cep_emp), ''),
    NULLIF(TRIM(c.cidade_emp), ''),
    NULLIF(TRIM(c.emp_uf), ''),
    NULLIF(TRIM(c.responsavel), ''),
    NULLIF(TRIM(c.contato_resp), ''),
    CASE WHEN c.data_coleta = '0000-00-00' THEN NULL ELSE c.data_coleta END,
    CAST(NULLIF(TRIM(c.saldo), '') AS DECIMAL(10,2)),
    CASE WHEN LOWER(TRIM(c.status)) = 'urgente' THEN 'urgente' ELSE 'normal' END
FROM well_antigo.clientes c
ON DUPLICATE KEY UPDATE
    nome_fantasia = VALUES(nome_fantasia),
    razao_social = VALUES(razao_social),
    cnpj = VALUES(cnpj),
    email = VALUES(email),
    telefone = VALUES(telefone),
    plano_id = VALUES(plano_id),
    status = VALUES(status),
    logradouro = VALUES(logradouro),
    numero = VALUES(numero),
    bairro = VALUES(bairro),
    cep = VALUES(cep),
    cidade = VALUES(cidade),
    uf = VALUES(uf),
    responsavel = VALUES(responsavel),
    telefone_resp = VALUES(telefone_resp),
    proxima_coleta = VALUES(proxima_coleta),
    saldo_residuo = VALUES(saldo_residuo),
    prioridade = VALUES(prioridade);

-- Funcionários legados como usuários (função Coletor), exceto e-mails já existentes
INSERT INTO `usuarios` (`nome`, `email`, `senha`, `funcao_id`, `ativo`, `must_reset_password`)
SELECT
    f.nome,
    f.email,
    f.senha,
    2,
    f.ativo,
    0
FROM well_antigo.funcionarios f
WHERE f.email NOT IN (SELECT email FROM usuarios)
  AND TRIM(f.email) != '';

-- Atribuições de rota (sem coletor — IDs de funcionário não batem 1:1 ainda)
INSERT IGNORE INTO `rota_atribuicoes` (`rota_id`, `cliente_id`, `coletor_id`)
SELECT
    r.id_rota,
    r.id_cliente,
    NULL
FROM well_antigo.rotas r
WHERE r.id_cliente IS NOT NULL
  AND r.id_rota IS NOT NULL
  AND EXISTS (SELECT 1 FROM clientes c WHERE c.id = r.id_cliente)
  AND EXISTS (SELECT 1 FROM rotas rt WHERE rt.id = r.id_rota);

ALTER TABLE `planos` AUTO_INCREMENT = 100;
ALTER TABLE `clientes` AUTO_INCREMENT = 1000;
ALTER TABLE `usuarios` AUTO_INCREMENT = 100;

SET FOREIGN_KEY_CHECKS = 1;
