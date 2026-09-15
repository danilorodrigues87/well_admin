-- Remove saldo manual por cliente (saldo vem do plano / plano_itens)
SET NAMES utf8mb4;

ALTER TABLE `clientes` DROP COLUMN `saldo_residuo`;
