-- Geolocalização de clientes (rotas Google Maps)
SET NAMES utf8mb4;

ALTER TABLE `clientes`
  ADD COLUMN `latitude` DECIMAL(10,7) DEFAULT NULL AFTER `uf`,
  ADD COLUMN `longitude` DECIMAL(10,7) DEFAULT NULL AFTER `latitude`,
  ADD COLUMN `maps_link` VARCHAR(500) DEFAULT NULL AFTER `longitude`,
  ADD COLUMN `geocoded_at` TIMESTAMP NULL DEFAULT NULL AFTER `maps_link`,
  ADD COLUMN `geocode_status` ENUM('ok','pendente','erro') NOT NULL DEFAULT 'pendente' AFTER `geocoded_at`;
