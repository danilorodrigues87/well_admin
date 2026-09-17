-- Módulo mapa da frota (gestor/admin)
SET NAMES utf8mb4;

INSERT INTO `modulos` (`slug`, `label`, `grupo`, `ordem`) VALUES
('frota_mapa', 'Mapa da frota', 'Operação', 24)
ON DUPLICATE KEY UPDATE label = VALUES(label), grupo = VALUES(grupo), ordem = VALUES(ordem);

INSERT IGNORE INTO `funcao_modulos` (`funcao_id`, `modulo_id`)
SELECT 1, id FROM modulos WHERE slug = 'frota_mapa';

INSERT IGNORE INTO `funcao_modulos` (`funcao_id`, `modulo_id`)
SELECT 3, id FROM modulos WHERE slug = 'frota_mapa';
