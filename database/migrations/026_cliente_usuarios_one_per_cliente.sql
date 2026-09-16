-- Um único login de portal por cliente (gerador)
SET NAMES utf8mb4;

DELETE cu1 FROM cliente_usuarios cu1
INNER JOIN cliente_usuarios cu2
  ON cu2.cliente_id = cu1.cliente_id
  AND cu2.operadora_id = cu1.operadora_id
  AND cu2.id < cu1.id;

ALTER TABLE `cliente_usuarios`
  ADD UNIQUE KEY `uk_cu_cliente` (`cliente_id`);
