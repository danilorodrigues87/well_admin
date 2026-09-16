-- Módulo contratos comerciais
SET NAMES utf8mb4;

INSERT INTO `modulos` (`slug`, `label`, `grupo`, `ordem`) VALUES
('contratos', 'Contratos', 'Comercial', 35)
ON DUPLICATE KEY UPDATE label = VALUES(label), grupo = VALUES(grupo), ordem = VALUES(ordem);

INSERT IGNORE INTO `funcao_modulos` (`funcao_id`, `modulo_id`)
SELECT f.id, m.id FROM funcoes f CROSS JOIN modulos m WHERE m.slug = 'contratos';
