-- Tipos de resíduo complementares (catálogo compartilhado nacional)
SET NAMES utf8mb4;

-- Embalagens de agrotóxicos (substitui registro excluído #73, agora com SINIR correto)
INSERT INTO `tipos_residuos` (
  `nome`, `classe_id`, `grupo_id`, `cod_ibama`, `tra_codigo`, `tie_codigo`, `tia_codigo`, `cla_codigo`, `uni_codigo`, `ativo`
)
SELECT
  'Embalagens de agrotóxicos e afins',
  (SELECT id FROM residuo_classes WHERE nome LIKE '%Perigoso%' OR nome LIKE '%Classe I%' LIMIT 1),
  NULL,
  '02.01.09',
  73,
  4,
  21,
  1,
  2,
  1
FROM DUAL
WHERE NOT EXISTS (
  SELECT 1 FROM `tipos_residuos` WHERE LOWER(`nome`) LIKE '%agrotóxic%' OR LOWER(`nome`) LIKE '%agrotoxic%'
);

-- Efluentes líquidos (substitui #76 excluído)
INSERT INTO `tipos_residuos` (
  `nome`, `cod_ibama`, `tra_codigo`, `tie_codigo`, `tia_codigo`, `cla_codigo`, `uni_codigo`, `ativo`
)
SELECT
  'Drenagem de efluentes com óleo',
  '13.05.07',
  73,
  2,
  11,
  1,
  2,
  1
FROM DUAL
WHERE NOT EXISTS (
  SELECT 1 FROM `tipos_residuos` WHERE LOWER(`nome`) LIKE '%drenagem de efluente%'
);

-- Misturas de gorduras e óleos (substitui #55 excluído)
INSERT INTO `tipos_residuos` (
  `nome`, `cod_ibama`, `tra_codigo`, `tie_codigo`, `tia_codigo`, `cla_codigo`, `uni_codigo`, `ativo`
)
SELECT
  'Misturas de gorduras e óleos alimentares',
  '19.08.09',
  44,
  2,
  9,
  43,
  2,
  1
FROM DUAL
WHERE NOT EXISTS (
  SELECT 1 FROM `tipos_residuos` WHERE LOWER(`nome`) LIKE '%misturas de gorduras%'
);
