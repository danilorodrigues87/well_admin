<?php

namespace App\Service\Contrato;

use App\Common\Helpers\FormatHelper;

/** HTML dinâmico para cláusulas de preço (franquia, excedentes, RCC). */
final class ContratoPricingPartial
{
    /** @param array<string,mixed> $snapshot @param array<string,mixed> $vars */
    public static function linhasExcedenteHtml(array $snapshot, string $pricingVariant): string
    {
        if ($pricingVariant === 'TABELA_TONELADA_CACAMBA') {
            return self::tabelaRccHtml($snapshot);
        }

        $lines = [];
        foreach ($snapshot['itens'] ?? [] as $row) {
            if (!is_array($row) || !empty($row['gera_credito'])) {
                continue;
            }
            $nome = htmlspecialchars((string)($row['tipo_nome'] ?? ''), ENT_QUOTES, 'UTF-8');
            $saldo = (float)($row['saldo_incluso'] ?? 0);
            $unit = htmlspecialchars((string)($row['unidade'] ?? 'kg'), ENT_QUOTES, 'UTF-8');
            $valor = FormatHelper::money((float)($row['valor_excedente'] ?? 0));
            if ($nome === '') {
                continue;
            }
            $franquia = $saldo > 0
                ? number_format($saldo, 0, ',', '.').' '.$unit
                : 'conforme plano';
            $lines[] = '<li>'.$nome.': franquia '.$franquia.'; excedente '.$valor.'/'.$unit.'.</li>';
        }
        if ($lines === []) {
            return '';
        }

        return '<p>Havendo variações de volume ou tipos distintos de resíduos, serão aplicados os seguintes valores específicos:</p><ul>'
            .implode('', $lines).'</ul>';
    }

    /** @param array<string,mixed> $snapshot */
    public static function franquiaResumoTexto(array $snapshot): string
    {
        $parts = [];
        foreach ($snapshot['itens'] ?? [] as $row) {
            if (!is_array($row) || !empty($row['gera_credito'])) {
                continue;
            }
            $saldo = (float)($row['saldo_incluso'] ?? 0);
            $unit = (string)($row['unidade'] ?? 'kg');
            if ($saldo > 0) {
                $parts[] = number_format($saldo, 0, ',', '.').' '.$unit;
            }
        }
        if ($parts === []) {
            return 'conforme demanda acordada';
        }

        return implode(' / ', $parts);
    }

    /** @param array<string,mixed> $snapshot */
    private static function tabelaRccHtml(array $snapshot): string
    {
        $rows = '';
        foreach ($snapshot['itens'] ?? [] as $row) {
            if (!is_array($row)) {
                continue;
            }
            $nome = htmlspecialchars((string)($row['tipo_nome'] ?? ''), ENT_QUOTES, 'UTF-8');
            $preco = FormatHelper::money(abs((float)($row['valor_excedente'] ?? 0)));
            $unit = strtolower((string)($row['unidade'] ?? 'ton'));
            $rows .= '<tr><td>'.$nome.'</td><td>—</td><td>—</td><td>—</td><td>'.$preco.'/'.$unit.'</td><td>—</td></tr>';
        }
        if ($rows === '') {
            return '<p>Valores conforme proposta comercial aprovada entre as partes.</p>';
        }

        return '<table class="table"><thead><tr>'
            .'<th>Tipo</th><th>Vol. est. m³</th><th>Peso esp.</th><th>Ton est.</th><th>R$/un</th><th>Total</th>'
            .'</tr></thead><tbody>'.$rows.'</tbody></table>';
    }

    public static function taxaAdesaoBloco(?float $taxa, bool $aplicavel): string
    {
        if (!$aplicavel || $taxa === null || $taxa <= 0) {
            return '';
        }
        $v = FormatHelper::money($taxa);

        return '<p><strong>Parágrafo Segundo:</strong> Taxa de adesão valor único na abertura do cadastro '
            .$v.' (cento e oitenta reais quando aplicável). '
            .'A taxa de adesão destina-se a cobrir custos administrativos de abertura de cadastro e logística inicial.</p>';
    }
}
