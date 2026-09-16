-- Categorias adicionais da Central de Ajuda (conteúdo detalhado via seed_help_modulos.php)
SET NAMES utf8mb4;

INSERT INTO `help_categorias` (`titulo`, `slug`, `ordem`, `ativo`)
SELECT 'Cadastros', 'cadastros', 25, 1
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `help_categorias` WHERE `slug` = 'cadastros');

INSERT INTO `help_categorias` (`titulo`, `slug`, `ordem`, `ativo`)
SELECT 'Comercial', 'comercial', 28, 1
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `help_categorias` WHERE `slug` = 'comercial');

INSERT INTO `help_categorias` (`titulo`, `slug`, `ordem`, `ativo`)
SELECT 'Sistema', 'sistema', 45, 1
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `help_categorias` WHERE `slug` = 'sistema');
