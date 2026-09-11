-- Well Eco — Corrige encoding corrompido + normaliza classes/grupos de resíduos
-- Executar UMA vez: mysql -u root --default-character-set=utf8mb4 well_admin < database/migrations/005_encoding_residuos_normalizados.sql

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ── 1. Corrige labels/grupos corrompidos ──
UPDATE `modulos` SET `label` = 'Lançar Coleta', `grupo` = 'Operação' WHERE `slug` = 'coleta_nova';
UPDATE `modulos` SET `label` = 'Coletas / MTR', `grupo` = 'Operação' WHERE `slug` = 'coletas';
UPDATE `modulos` SET `label` = 'Agendamentos', `grupo` = 'Operação' WHERE `slug` = 'agendamentos';
UPDATE `modulos` SET `label` = 'Funcionários', `grupo` = 'Cadastros' WHERE `slug` = 'funcionarios';
UPDATE `modulos` SET `label` = 'Veículos', `grupo` = 'Cadastros' WHERE `slug` = 'veiculos';
UPDATE `modulos` SET `label` = 'Tipos de Resíduos', `grupo` = 'Cadastros' WHERE `slug` = 'tipos_residuos';
UPDATE `modulos` SET `label` = 'Relatórios', `grupo` = 'Financeiro' WHERE `slug` = 'relatorios';
UPDATE `modulos` SET `label` = 'Usuários', `grupo` = 'Sistema' WHERE `slug` = 'usuarios';
UPDATE `modulos` SET `label` = 'Funções e Módulos', `grupo` = 'Sistema' WHERE `slug` = 'funcoes';

INSERT INTO `modulos` (`slug`, `label`, `grupo`, `ordem`) VALUES
('residuo_classes', 'Classes de Resíduo', 'Cadastros', 33),
('residuo_grupos', 'Grupos de Resíduo', 'Cadastros', 34)
ON DUPLICATE KEY UPDATE `label` = VALUES(`label`), `grupo` = VALUES(`grupo`), `ordem` = VALUES(`ordem`);

UPDATE `modulos` SET `ordem` = 36 WHERE `slug` = 'tipos_residuos';
UPDATE `modulos` SET `ordem` = 37 WHERE `slug` = 'rotas';

INSERT IGNORE INTO `funcao_modulos` (`funcao_id`, `modulo_id`)
SELECT 1, id FROM `modulos` WHERE `slug` IN ('residuo_classes', 'residuo_grupos');

INSERT IGNORE INTO `funcao_modulos` (`funcao_id`, `modulo_id`)
SELECT 3, id FROM `modulos` WHERE `slug` IN ('residuo_classes', 'residuo_grupos', 'tipos_residuos');

-- ── 2. Tabelas normalizadas ──
CREATE TABLE IF NOT EXISTS `residuo_classes` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `nome` varchar(120) NOT NULL,
  `slug` varchar(60) NOT NULL,
  `descricao` varchar(255) DEFAULT NULL,
  `ordem` int NOT NULL DEFAULT 0,
  `ativo` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_residuo_classes_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `residuo_grupos` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `classe_id` int unsigned NOT NULL,
  `codigo` varchar(10) NOT NULL,
  `nome` varchar(120) DEFAULT NULL,
  `descricao` varchar(255) DEFAULT NULL,
  `ativo` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_residuo_grupos_classe_codigo` (`classe_id`, `codigo`),
  KEY `fk_residuo_grupos_classe` (`classe_id`),
  CONSTRAINT `fk_residuo_grupos_classe` FOREIGN KEY (`classe_id`) REFERENCES `residuo_classes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `tipos_residuos`
  ADD COLUMN `classe_id` int unsigned DEFAULT NULL AFTER `nome`,
  ADD COLUMN `grupo_id` int unsigned DEFAULT NULL AFTER `classe_id`;

-- ── 3. Classes canônicas ──
INSERT INTO `residuo_classes` (`id`, `nome`, `slug`, `ordem`) VALUES
(1, 'Classe I — Saúde', 'classe-i-saude', 10),
(2, 'Classe I — Industrial', 'classe-i-industrial', 20),
(3, 'Classe I — Eletrônicos', 'classe-i-eletronicos', 30),
(4, 'Classe II', 'classe-ii', 40),
(5, 'Classe I — Geral / Perigoso', 'classe-i-geral', 50)
ON DUPLICATE KEY UPDATE `nome` = VALUES(`nome`), `ordem` = VALUES(`ordem`);

-- ── 4. Grupos a partir do legado ──
INSERT IGNORE INTO `residuo_grupos` (`classe_id`, `codigo`, `nome`)
SELECT DISTINCT
  CASE TRIM(t.classe)
    WHEN 'Classe I (Saúde)' THEN 1
    WHEN 'Classe I (Industrial)' THEN 2
    WHEN 'Classe I (Eletrônicos)' THEN 3
    WHEN 'Classe II' THEN 4
    WHEN 'Classe II (Industrial)' THEN 4
    WHEN 'CLASSE II' THEN 4
    WHEN 'II' THEN 4
    WHEN 'I - PERIGOSO' THEN 5
    WHEN 'CLASSE I' THEN 5
    ELSE 4
  END,
  TRIM(t.grupo),
  CONCAT('Grupo ', TRIM(t.grupo))
FROM `tipos_residuos` t
WHERE TRIM(t.grupo) <> '';

-- ── 5. Vincula tipos_residuos ──
UPDATE `tipos_residuos` t
INNER JOIN `residuo_grupos` rg ON rg.codigo = TRIM(t.grupo)
  AND rg.classe_id = CASE TRIM(t.classe)
    WHEN 'Classe I (Saúde)' THEN 1
    WHEN 'Classe I (Industrial)' THEN 2
    WHEN 'Classe I (Eletrônicos)' THEN 3
    WHEN 'Classe II' THEN 4
    WHEN 'Classe II (Industrial)' THEN 4
    WHEN 'CLASSE II' THEN 4
    WHEN 'II' THEN 4
    WHEN 'I - PERIGOSO' THEN 5
    WHEN 'CLASSE I' THEN 5
    ELSE 4
  END
SET t.classe_id = rg.classe_id, t.grupo_id = rg.id;

-- ── 6. Remove colunas texto legado ──
ALTER TABLE `tipos_residuos`
  DROP COLUMN `classe`,
  DROP COLUMN `grupo`;

ALTER TABLE `tipos_residuos`
  ADD KEY `fk_tipos_residuos_classe` (`classe_id`),
  ADD KEY `fk_tipos_residuos_grupo` (`grupo_id`),
  ADD CONSTRAINT `fk_tipos_residuos_classe` FOREIGN KEY (`classe_id`) REFERENCES `residuo_classes` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_tipos_residuos_grupo` FOREIGN KEY (`grupo_id`) REFERENCES `residuo_grupos` (`id`) ON DELETE SET NULL;

SET FOREIGN_KEY_CHECKS = 1;
