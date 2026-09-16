-- Módulo Rota do dia (painel + app)
SET NAMES utf8mb4;

INSERT INTO `modulos` (`slug`, `label`, `grupo`, `ordem`) VALUES
('rota_dia', 'Rota do dia', 'Operação', 23)
ON DUPLICATE KEY UPDATE label = VALUES(label), grupo = VALUES(grupo), ordem = VALUES(ordem);

INSERT IGNORE INTO `funcao_modulos` (`funcao_id`, `modulo_id`)
SELECT 2, id FROM modulos WHERE slug = 'rota_dia';

INSERT IGNORE INTO `funcao_modulos` (`funcao_id`, `modulo_id`)
SELECT 3, id FROM modulos WHERE slug = 'rota_dia';
