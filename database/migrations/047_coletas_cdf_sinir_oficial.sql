-- CDF oficial SINIR (emiteCDF) vs PDF MTR / manual
SET NAMES utf8mb4;

ALTER TABLE `coletas`
  ADD COLUMN `sinir_cdf_codigo` varchar(30) DEFAULT NULL AFTER `cdf_obtido_em`,
  ADD COLUMN `cdf_tipo` enum('mtr_pdf','manual','sinir_cdf') DEFAULT NULL AFTER `sinir_cdf_codigo`;
