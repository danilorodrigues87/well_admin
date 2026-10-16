<?php

namespace App\Service;

use App\Model\Entity\Coleta as EntityColeta;
use App\Model\Entity\InterCobranca as EntityInterCobranca;

/**
 * Lista coletas para o certificado do período e controla a liberação no portal.
 * Gerar o CDF não publica o arquivo.
 */
class CdfLoteService
{
    /** @return EntityColeta[] */
    public static function listar(int $clienteId, string $inicio, string $fim): array
    {
        return EntityColeta::list(
            "c.status = 'finalizada' AND c.cliente_id = ? AND COALESCE(c.data_coleta, c.data_recebimento) BETWEEN ? AND ?",
            [$clienteId, $inicio, $fim],
            '300'
        );
    }

    public static function avisoMensalidade(int $clienteId, string $inicio): string
    {
        $competencia = substr($inicio, 0, 7);
        $cobranca = EntityInterCobranca::getByClienteCompetencia($clienteId, $competencia);
        if (!$cobranca) {
            return 'Competência '.$competencia.': sem cobrança emitida. A retenção não é automática — use Liberar ou Reter.';
        }

        $status = strtoupper(trim($cobranca->status));
        $pago = in_array($status, ['PAGO', 'RECEBIDO', 'MARCADO_RECEBIDO', 'LIQUIDADO'], true);
        if ($pago) {
            return 'Competência '.$competencia.': cobrança paga. A liberação no portal continua manual.';
        }

        return 'Competência '.$competencia.': cobrança '.$status.'. O portal só mostra o documento depois de Liberar.';
    }

    /**
     * @param list<int> $ids
     * @return array{ok:bool,message:string}
     */
    public static function definirLiberacao(array $ids, string $documento, bool $liberar): array
    {
        $campo = match ($documento) {
            'mtr' => 'mtr_oficial_liberado_em',
            'cdf' => 'cdf_liberado_em',
            default => '',
        };
        if ($campo === '') {
            return ['ok' => false, 'message' => 'Documento inválido.'];
        }

        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), static fn (int $id): bool => $id > 0)));
        if ($ids === []) {
            return ['ok' => false, 'message' => 'Selecione ao menos uma coleta.'];
        }

        $valor = $liberar ? date('Y-m-d H:i:s') : null;
        $atualizados = 0;
        foreach ($ids as $id) {
            $coleta = EntityColeta::getById($id);
            if (!$coleta || $coleta->status !== 'finalizada') {
                continue;
            }
            if ($documento === 'mtr' && trim((string)($coleta->sinir_man_numero ?? '')) === '') {
                continue;
            }
            if ($documento === 'cdf' && trim((string)($coleta->sinir_cdf_codigo ?? '')) === '' && !ColetaCdfService::temCdf($coleta)) {
                continue;
            }
            EntityColeta::update($id, [$campo => $valor]);
            $atualizados++;
        }

        if ($atualizados === 0) {
            $falta = $documento === 'mtr' ? 'MTR nacional' : 'CDF';

            return ['ok' => false, 'message' => 'Nenhuma coleta selecionada tem '.$falta.' para ' . ($liberar ? 'liberar.' : 'reter.')];
        }

        $acao = $liberar ? 'liberado' : 'retido';
        $rotulo = $documento === 'mtr' ? 'MTR oficial' : 'CDF';

        return ['ok' => true, 'message' => $rotulo.' '.$acao.' em '.$atualizados.' coleta'.($atualizados === 1 ? '' : 's').'.'];
    }
}
