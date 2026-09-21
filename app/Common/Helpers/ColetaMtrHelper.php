<?php

namespace App\Common\Helpers;

use App\Common\SinirConfig;
use App\Model\Entity\Coleta;

class ColetaMtrHelper
{
    /**
     * MTR “existe” para o cliente/admin quando foi registrado no SINIR (ou legado / ambiente sem SINIR).
     */
    public static function temMtr(Coleta $c): bool
    {
        if ($c->status !== 'finalizada') {
            return false;
        }

        if (!SinirConfig::isEnabled()) {
            return $c->numero_mtr !== null && (int)$c->numero_mtr > 0;
        }

        if (($c->sinir_status ?? '') === 'cancelado') {
            return false;
        }

        if (($c->sinir_status ?? '') === 'enviado') {
            return true;
        }

        // ETL well_antigo: MTR nacional já existia; numero_mtr = manifesto legado
        if ($c->legacy_manifesto !== null && $c->numero_mtr) {
            return true;
        }

        // Outras finalizadas com número e sem pendência/erro SINIR
        if ($c->numero_mtr && !in_array($c->sinir_status ?? '', ['pendente', 'erro'], true)) {
            return true;
        }

        return false;
    }

    /** Número exibido em listagens, portal e impressão. */
    public static function numeroExibicao(Coleta $c): ?string
    {
        if (!self::temMtr($c)) {
            return null;
        }
        if ($c->numero_mtr) {
            return (string)(int)$c->numero_mtr;
        }
        if (!empty($c->sinir_man_numero)) {
            return (string)$c->sinir_man_numero;
        }

        return null;
    }

    public static function rotuloSemMtr(Coleta $c): string
    {
        if ($c->status === 'rascunho') {
            return 'Rascunho';
        }
        if ($c->status !== 'finalizada') {
            return '—';
        }
        if (!SinirConfig::isEnabled()) {
            return 'Sem número';
        }

        return match ($c->sinir_status ?? '') {
            'erro' => 'Erro SINIR',
            'cancelado' => 'Cancelado SINIR',
            'enviado' => '—',
            default => 'Aguard. SINIR',
        };
    }
}
