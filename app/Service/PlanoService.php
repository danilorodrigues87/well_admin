<?php

namespace App\Service;

use App\Common\Helpers\IbamaCodigoHelper;
use App\Common\Helpers\MoneyHelper;
use App\Model\Entity\PlanoItem as EntityPlanoItem;

class PlanoService
{
    /** Soma dos saldos inclusos do plano (referência operacional). */
    public static function totalSaldoIncluso(int $planoId): float
    {
        if ($planoId <= 0) {
            return 0.0;
        }
        $total = 0.0;
        foreach (EntityPlanoItem::getByPlanoId($planoId) as $item) {
            if (!$item->gera_credito) {
                $total += $item->saldo_incluso;
            }
        }

        return $total;
    }

    /** @return list<array<string,mixed>> */
    public static function serializeItensForForm(int $planoId): array
    {
        $out = [];
        foreach (EntityPlanoItem::getByPlanoId($planoId) as $item) {
            $out[] = [
                'tipo_residuo_id' => $item->tipo_residuo_id,
                'saldo_incluso' => $item->saldo_incluso,
                'unidade' => $item->unidade,
                'valor_excedente' => $item->valor_excedente,
                'saldo_compartilhado' => $item->saldo_compartilhado ? 1 : 0,
                'gera_credito' => $item->gera_credito ? 1 : 0,
            ];
        }

        return $out;
    }

    public static function renderResumoSaldo(int $planoId): string
    {
        $lines = [];
        foreach (EntityPlanoItem::getByPlanoId($planoId) as $item) {
            $label = IbamaCodigoHelper::label($item->tipo_cod_ibama, $item->tipo_nome);
            if ($item->gera_credito) {
                $lines[] = htmlspecialchars($label, ENT_QUOTES, 'UTF-8')
                    .' <span class="badge bg-success">crédito</span>';
                continue;
            }
            $share = $item->saldo_compartilhado ? ' <span class="badge bg-secondary">compartilhado</span>' : '';
            $unit = $item->unidade !== '' ? $item->unidade : 'kg';
            $lines[] = htmlspecialchars($label, ENT_QUOTES, 'UTF-8').$share
                .' <span class="text-muted">'.number_format($item->saldo_incluso, 2, ',', '.')
                .' '.htmlspecialchars($unit, ENT_QUOTES, 'UTF-8').'</span>';
        }

        return $lines === [] ? '<span class="text-muted">—</span>' : implode('<br>', $lines);
    }

    public static function renderResumoExcedente(int $planoId): string
    {
        $lines = [];
        foreach (EntityPlanoItem::getByPlanoId($planoId) as $item) {
            $label = IbamaCodigoHelper::label($item->tipo_cod_ibama, $item->tipo_nome);
            $suffix = $item->gera_credito ? ' desconto/kg' : '/'.htmlspecialchars($item->unidade, ENT_QUOTES, 'UTF-8');
            $lines[] = htmlspecialchars($label, ENT_QUOTES, 'UTF-8')
                .' <span class="text-muted">'.MoneyHelper::format($item->valor_excedente).$suffix.'</span>';
        }

        return $lines === [] ? '<span class="text-muted">—</span>' : implode('<br>', $lines);
    }

    /**
     * @param list<array<string,mixed>> $itens
     * @return list<array<string,mixed>>|string erro
     */
    public static function validateItens(array $itens): array|string
    {
        if ($itens === []) {
            return 'Adicione ao menos um resíduo ao plano.';
        }

        $seen = [];
        $sharedUnits = [];

        foreach ($itens as $item) {
            $tipoId = (int)($item['tipo_residuo_id'] ?? 0);
            if ($tipoId <= 0) {
                return 'Selecione o resíduo em todas as linhas.';
            }
            if (isset($seen[$tipoId])) {
                return 'Resíduo duplicado no mesmo plano.';
            }
            $seen[$tipoId] = true;

            $geraCredito = !empty($item['gera_credito']);
            if ($geraCredito && !empty($item['saldo_compartilhado'])) {
                return 'Resíduo com crédito não pode usar saldo compartilhado.';
            }
            if ($geraCredito && (float)($item['valor_excedente'] ?? 0) <= 0) {
                return 'Informe a tarifa de crédito (R$/kg) maior que zero para resíduos recicláveis.';
            }

            if (!empty($item['saldo_compartilhado'])) {
                $key = (string)round((float)($item['valor_excedente'] ?? 0), 2);
                $unit = (string)($item['unidade'] ?? 'kg');
                if (isset($sharedUnits[$key]) && $sharedUnits[$key] !== $unit) {
                    return 'Itens com saldo compartilhado e mesmo valor excedente devem usar a mesma unidade.';
                }
                $sharedUnits[$key] = $unit;
            }
        }

        return $itens;
    }

    /** @param mixed $raw */
    public static function decodeItensFromPost(mixed $raw): array
    {
        if (is_string($raw) && $raw !== '') {
            $decoded = json_decode($raw, true);

            return is_array($decoded) ? $decoded : [];
        }

        return is_array($raw) ? $raw : [];
    }
}
