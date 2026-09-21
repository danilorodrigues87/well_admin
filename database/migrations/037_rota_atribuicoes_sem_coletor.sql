-- Rotas passam a vincular apenas clientes; coletor fica em coletas.coletor_id (escolha na operação).
UPDATE rota_atribuicoes SET coletor_id = NULL WHERE coletor_id IS NOT NULL;
