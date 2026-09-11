<?php

namespace App\Service;

use App\Common\Helpers\MoneyHelper;
use App\Model\Entity\PlanoItem as EntityPlanoItem;
use App\Model\Entity\TipoResiduo as EntityTipoResiduo;

class PlanoService
{
    /** @return list<array<string,mixed>> */
    public static function serializeItensForForm(int $planoId): array
    {
        $out = [];
        foreach (EntityPlanoItem::getByPlanoId($planoId) as $item) {
            $out[] = [
                'tipo_residuo_id' => $item->tipo_residuo_id,
                'nome' => $item->nome,
                'cod_ibama' => $item->cod_ibama,
                'saldo_incluso' => $item->saldo_incluso,
                'unidade' => $item->unidade,
                'valor_excedente' => $item->valor_excedente,
            ];
        }
        return $out;
    }

    /** Resumo HTML para listagem (colunas Saldo / Excedente). */
    public static function renderResumoSaldo(int $planoId): string
    {
        $lines = [];
        foreach (EntityPlanoItem::getByPlanoId($planoId) as $item) {
            $label = $item->cod_ibama ? $item->cod_ibama.' — '.$item->nome : $item->nome;
            $lines[] = htmlspecialchars($label, ENT_QUOTES, 'UTF-8')
                .' <span class="text-muted">Saldo '.number_format($item->saldo_incluso, 2, ',', '.')
                .' '.htmlspecialchars($item->unidade, ENT_QUOTES, 'UTF-8').'</span>';
        }
        return $lines === [] ? '<span class="text-muted">—</span>' : implode('<br>', $lines);
    }

    public static function renderResumoExcedente(int $planoId): string
    {
        $lines = [];
        foreach (EntityPlanoItem::getByPlanoId($planoId) as $item) {
            $label = $item->cod_ibama ? $item->cod_ibama.' — '.$item->nome : $item->nome;
            $lines[] = htmlspecialchars($label, ENT_QUOTES, 'UTF-8')
                .' <span class="text-muted">'.MoneyHelper::format($item->valor_excedente).'/'.htmlspecialchars($item->unidade, ENT_QUOTES, 'UTF-8').'</span>';
        }
        return $lines === [] ? '<span class="text-muted">—</span>' : implode('<br>', $lines);
    }

    /** @param list<array<string,mixed>> $itens */
    public static function enrichItensWithTipoResiduo(array $itens): array
    {
        foreach ($itens as &$item) {
            if (!empty($item['tipo_residuo_id'])) {
                continue;
            }
            $cod = trim((string)($item['cod_ibama'] ?? ''));
            $nome = trim((string)($item['nome'] ?? ''));
            $tipo = null;
            if ($cod !== '') {
                $tipo = EntityTipoResiduo::findByCodIbama($cod);
            }
            if (!$tipo && $nome !== '') {
                $tipo = EntityTipoResiduo::findByNome($nome);
            }
            if ($tipo) {
                $item['tipo_residuo_id'] = $tipo->id;
            }
        }
        unset($item);
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
