-- Central de ajuda + módulos suporte/ajuda/termos
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS `help_categorias` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `titulo` varchar(120) NOT NULL,
  `slug` varchar(120) NOT NULL,
  `ordem` int NOT NULL DEFAULT 0,
  `ativo` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_help_cat_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `help_artigos` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `id_categoria` int unsigned NOT NULL,
  `titulo` varchar(200) NOT NULL,
  `slug` varchar(200) NOT NULL,
  `resumo` varchar(500) DEFAULT NULL,
  `corpo` mediumtext,
  `video_url` varchar(1000) DEFAULT NULL,
  `video_titulo` varchar(200) DEFAULT NULL,
  `ordem` int NOT NULL DEFAULT 0,
  `publicado` tinyint(1) NOT NULL DEFAULT 0,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_help_art_slug` (`slug`),
  KEY `idx_help_art_cat` (`id_categoria`, `publicado`, `ordem`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `modulos` (`slug`, `label`, `grupo`, `ordem`) VALUES
('suporte', 'Suporte', 'Suporte', 60),
('ajuda', 'Ajuda', 'Suporte', 61),
('termos_de_uso', 'Termos de Uso', 'Sistema', 98)
ON DUPLICATE KEY UPDATE label = VALUES(label), grupo = VALUES(grupo), ordem = VALUES(ordem);

INSERT IGNORE INTO `funcao_modulos` (`funcao_id`, `modulo_id`)
SELECT f.id, m.id FROM funcoes f CROSS JOIN modulos m
WHERE m.slug IN ('suporte', 'ajuda', 'termos_de_uso');

INSERT INTO `help_categorias` (`titulo`, `slug`, `ordem`, `ativo`)
SELECT 'Primeiros passos', 'primeiros-passos', 10, 1
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `help_categorias` WHERE `slug` = 'primeiros-passos');

INSERT INTO `help_categorias` (`titulo`, `slug`, `ordem`, `ativo`)
SELECT 'Operação', 'operacao', 20, 1
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `help_categorias` WHERE `slug` = 'operacao');

INSERT INTO `help_categorias` (`titulo`, `slug`, `ordem`, `ativo`)
SELECT 'Financeiro', 'financeiro', 30, 1
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `help_categorias` WHERE `slug` = 'financeiro');

INSERT INTO `help_artigos` (`id_categoria`, `titulo`, `slug`, `resumo`, `corpo`, `ordem`, `publicado`)
SELECT c.id, 'Visão geral do painel', 'visao-geral-painel',
  'Conheça o dashboard e a navegação do painel Well.',
  '<p>O painel Well permite gerenciar coletas, clientes geradores, rotas, pagamentos e relatórios ambientais.</p><p>Use o menu lateral para acessar cada módulo conforme sua função.</p>',
  10, 1
FROM `help_categorias` c WHERE c.slug = 'primeiros-passos'
AND NOT EXISTS (SELECT 1 FROM `help_artigos` WHERE `slug` = 'visao-geral-painel');

INSERT INTO `help_artigos` (`id_categoria`, `titulo`, `slug`, `resumo`, `corpo`, `ordem`, `publicado`)
SELECT c.id, 'Lançar coleta e MTR', 'lancar-coleta-mtr',
  'Como registrar uma coleta e emitir MTR.',
  '<p>Acesse <strong>Operação → Lançar Coleta</strong> e siga o assistente: cliente, resíduos, transportador e destinador.</p><p>Após salvar, a coleta pode ser consultada em <strong>Coletas / MTR</strong>.</p>',
  10, 1
FROM `help_categorias` c WHERE c.slug = 'operacao'
AND NOT EXISTS (SELECT 1 FROM `help_artigos` WHERE `slug` = 'lancar-coleta-mtr');

INSERT INTO `help_artigos` (`id_categoria`, `titulo`, `slug`, `resumo`, `corpo`, `ordem`, `publicado`)
SELECT c.id, 'Pagamentos e boletos Inter', 'pagamentos-boletos',
  'Geração de cobranças mensais via Banco Inter.',
  '<p>Em <strong>Financeiro → Pagamentos</strong>, selecione a competência e gere boletos para clientes com plano ativo.</p><p>Valores consideram mensalidade do plano e excedentes de resíduos.</p>',
  10, 1
FROM `help_categorias` c WHERE c.slug = 'financeiro'
AND NOT EXISTS (SELECT 1 FROM `help_artigos` WHERE `slug` = 'pagamentos-boletos');
