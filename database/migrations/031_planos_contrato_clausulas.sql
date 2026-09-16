-- Cláusulas de contrato por plano de serviço
SET NAMES utf8mb4;

ALTER TABLE `planos`
  ADD COLUMN `contrato_clausula_1` mediumtext NULL AFTER `ativo`,
  ADD COLUMN `contrato_clausula_2` mediumtext NULL AFTER `contrato_clausula_1`,
  ADD COLUMN `contrato_clausula_3` mediumtext NULL AFTER `contrato_clausula_2`,
  ADD COLUMN `contrato_clausula_extra` mediumtext NULL AFTER `contrato_clausula_3`,
  ADD COLUMN `contrato_pagamento_parcelado` mediumtext NULL AFTER `contrato_clausula_extra`,
  ADD COLUMN `contrato_pagamento_vista` mediumtext NULL AFTER `contrato_pagamento_parcelado`,
  ADD COLUMN `contrato_obs_pontualidade` mediumtext NULL AFTER `contrato_pagamento_vista`;
