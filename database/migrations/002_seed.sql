-- Seed: módulos, funções, admin inicial
-- Senha padrão admin: WellEco@2026 (bcrypt abaixo — alterar após primeiro login)

INSERT INTO `funcoes` (`id`, `nome`, `slug`, `descricao`, `is_admin`) VALUES
(1, 'Administrador', 'admin', 'Acesso total ao sistema', 1),
(2, 'Coletor', 'coletor', 'Lançamento de coletas em campo', 0),
(3, 'Gestor de Coletas', 'gestor', 'Gestão de agendamentos e clientes', 0)
ON DUPLICATE KEY UPDATE nome = VALUES(nome);

INSERT INTO `modulos` (`slug`, `label`, `grupo`, `ordem`) VALUES
('dashboard', 'Dashboard', 'Principal', 10),
('coletas', 'Coletas / MTR', 'Operação', 20),
('coleta_nova', 'Lançar Coleta', 'Operação', 21),
('agendamentos', 'Agendamentos', 'Operação', 22),
('clientes', 'Clientes', 'Cadastros', 30),
('funcionarios', 'Funcionários', 'Cadastros', 31),
('veiculos', 'Veículos', 'Cadastros', 32),
('planos', 'Planos', 'Cadastros', 33),
('tipos_residuos', 'Tipos de Resíduos', 'Cadastros', 34),
('rotas', 'Rotas', 'Cadastros', 35),
('pagamentos', 'Pagamentos', 'Financeiro', 40),
('relatorios', 'Relatórios', 'Financeiro', 41),
('usuarios', 'Usuários', 'Sistema', 50),
('funcoes', 'Funções e Módulos', 'Sistema', 51),
('perfil', 'Perfil', 'Sistema', 99)
ON DUPLICATE KEY UPDATE label = VALUES(label);

-- Admin: todos módulos (is_admin=1 ignora lista, mas registramos mesmo assim)
INSERT IGNORE INTO `funcao_modulos` (`funcao_id`, `modulo_id`)
SELECT 1, id FROM modulos;

-- Coletor
INSERT IGNORE INTO `funcao_modulos` (`funcao_id`, `modulo_id`)
SELECT 2, id FROM modulos WHERE slug IN ('dashboard', 'coletas', 'coleta_nova', 'perfil');

-- Gestor
INSERT IGNORE INTO `funcao_modulos` (`funcao_id`, `modulo_id`)
SELECT 3, id FROM modulos WHERE slug IN (
  'dashboard', 'coletas', 'agendamentos', 'clientes', 'rotas', 'relatorios', 'perfil'
);

-- Admin user — senha: WellEco@2026
INSERT INTO `usuarios` (`nome`, `email`, `senha`, `funcao_id`, `ativo`, `must_reset_password`)
SELECT 'Administrador Well Eco', 'admin@well.eco',
  '$2y$10$Ve.Fq/gn/AurMwx8WIT0sutUaIs/95JNLJHPp8/FAAksko1Wzstxa',
  1, 's', 0
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM usuarios WHERE email = 'admin@well.eco');
