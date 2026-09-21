-- Coletas importadas do well_antigo: MTR já existia no SINIR (lançamento manual histórico).
-- Não altera coletas novas (legacy_manifesto IS NULL).
SET NAMES utf8mb4;

UPDATE coletas
SET
  sinir_status = 'enviado',
  sinir_enviado_em = CASE
    WHEN sinir_enviado_em IS NOT NULL AND sinir_enviado_em > '1000-01-01' THEN sinir_enviado_em
    WHEN finalized_at IS NOT NULL AND finalized_at > '1000-01-01' THEN finalized_at
    WHEN data_coleta IS NOT NULL AND data_coleta > '1000-01-01' THEN TIMESTAMP(data_coleta, '12:00:00')
    ELSE NOW()
  END,
  sinir_man_numero = COALESCE(NULLIF(sinir_man_numero, ''), CAST(numero_mtr AS CHAR))
WHERE legacy_manifesto IS NOT NULL
  AND status = 'finalizada'
  AND (sinir_status IS NULL OR sinir_status IN ('pendente', 'erro'));
