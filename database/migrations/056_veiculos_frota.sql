-- Frota operacional: situacao, km, combustivel e historico
SET NAMES utf8mb4;

ALTER TABLE `veiculos`
  ADD COLUMN `situacao` enum('disponivel','em_uso','manutencao') NOT NULL DEFAULT 'disponivel' AFTER `ativo`,
  ADD COLUMN `km_atual` int unsigned NOT NULL DEFAULT 0 AFTER `situacao`,
  ADD COLUMN `combustivel` enum('diesel','gasolina','etanol','flex','gnv') NOT NULL DEFAULT 'diesel' AFTER `km_atual`,
  ADD COLUMN `em_uso_usuario_id` int unsigned DEFAULT NULL AFTER `combustivel`,
  ADD COLUMN `em_uso_desde` datetime DEFAULT NULL AFTER `em_uso_usuario_id`,
  ADD KEY `idx_veiculos_situacao` (`operadora_id`, `situacao`),
  ADD CONSTRAINT `fk_veiculos_em_uso_usuario` FOREIGN KEY (`em_uso_usuario_id`) REFERENCES `usuarios` (`id`);

CREATE TABLE IF NOT EXISTS `veiculo_usos` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `operadora_id` int unsigned NOT NULL DEFAULT 1,
  `veiculo_id` int unsigned NOT NULL,
  `usuario_id` int unsigned NOT NULL,
  `km_inicio` int unsigned NOT NULL,
  `km_fim` int unsigned DEFAULT NULL,
  `iniciado_em` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `encerrado_em` datetime DEFAULT NULL,
  `encerrado_por` enum('coletor','painel') DEFAULT NULL,
  `observacao` varchar(500) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_vu_veiculo` (`operadora_id`, `veiculo_id`, `encerrado_em`),
  KEY `idx_vu_usuario` (`operadora_id`, `usuario_id`, `encerrado_em`),
  CONSTRAINT `fk_vu_operadora` FOREIGN KEY (`operadora_id`) REFERENCES `operadoras` (`id`),
  CONSTRAINT `fk_vu_veiculo` FOREIGN KEY (`veiculo_id`) REFERENCES `veiculos` (`id`),
  CONSTRAINT `fk_vu_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `veiculo_km` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `operadora_id` int unsigned NOT NULL DEFAULT 1,
  `veiculo_id` int unsigned NOT NULL,
  `usuario_id` int unsigned NOT NULL,
  `uso_id` int unsigned DEFAULT NULL,
  `km` int unsigned NOT NULL,
  `origem` enum('app','painel') NOT NULL DEFAULT 'painel',
  `observacao` varchar(500) DEFAULT NULL,
  `registrado_em` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_vk_veiculo` (`operadora_id`, `veiculo_id`, `registrado_em`),
  CONSTRAINT `fk_vk_operadora` FOREIGN KEY (`operadora_id`) REFERENCES `operadoras` (`id`),
  CONSTRAINT `fk_vk_veiculo` FOREIGN KEY (`veiculo_id`) REFERENCES `veiculos` (`id`),
  CONSTRAINT `fk_vk_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`),
  CONSTRAINT `fk_vk_uso` FOREIGN KEY (`uso_id`) REFERENCES `veiculo_usos` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `veiculo_abastecimentos` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `operadora_id` int unsigned NOT NULL DEFAULT 1,
  `veiculo_id` int unsigned NOT NULL,
  `usuario_id` int unsigned NOT NULL,
  `uso_id` int unsigned DEFAULT NULL,
  `abastecido_em` datetime NOT NULL,
  `litros` decimal(8,3) NOT NULL,
  `valor` decimal(10,2) DEFAULT NULL,
  `km` int unsigned NOT NULL,
  `posto` varchar(120) DEFAULT NULL,
  `observacao` varchar(500) DEFAULT NULL,
  `origem` enum('app','painel') NOT NULL DEFAULT 'painel',
  PRIMARY KEY (`id`),
  KEY `idx_va_veiculo` (`operadora_id`, `veiculo_id`, `abastecido_em`),
  CONSTRAINT `fk_va_operadora` FOREIGN KEY (`operadora_id`) REFERENCES `operadoras` (`id`),
  CONSTRAINT `fk_va_veiculo` FOREIGN KEY (`veiculo_id`) REFERENCES `veiculos` (`id`),
  CONSTRAINT `fk_va_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`),
  CONSTRAINT `fk_va_uso` FOREIGN KEY (`uso_id`) REFERENCES `veiculo_usos` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `veiculo_manutencoes` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `operadora_id` int unsigned NOT NULL DEFAULT 1,
  `veiculo_id` int unsigned NOT NULL,
  `usuario_id` int unsigned NOT NULL,
  `tipo` enum('preventiva','corretiva') NOT NULL,
  `status` enum('aberta','concluida','cancelada') NOT NULL DEFAULT 'aberta',
  `descricao` varchar(500) NOT NULL,
  `km` int unsigned DEFAULT NULL,
  `custo` decimal(10,2) DEFAULT NULL,
  `aberta_em` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `concluida_em` datetime DEFAULT NULL,
  `observacao` varchar(500) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_vm_veiculo` (`operadora_id`, `veiculo_id`, `status`),
  CONSTRAINT `fk_vm_operadora` FOREIGN KEY (`operadora_id`) REFERENCES `operadoras` (`id`),
  CONSTRAINT `fk_vm_veiculo` FOREIGN KEY (`veiculo_id`) REFERENCES `veiculos` (`id`),
  CONSTRAINT `fk_vm_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
