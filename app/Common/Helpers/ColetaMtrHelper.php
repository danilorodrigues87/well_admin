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

    public static function numeroRelatorioExibicao(Coleta $c): ?string
    {
        if ($c->numero_relatorio) {
            return (string)(int)$c->numero_relatorio;
        }
        if ($c->status === 'finalizada' && $c->numero_mtr && !SinirConfig::isEnabled()) {
            return (string)(int)$c->numero_mtr;
        }

        return null;
    }

    public static function podeGerarMtr(Coleta $c): bool
    {
        if ($c->status !== 'finalizada' || !SinirConfig::isEnabled()) {
            return false;
        }
        if (self::temMtr($c)) {
            return false;
        }
        $st = $c->sinir_status ?? '';
        if ($st === 'cancelado') {
            return true;
        }

        return in_array($st, ['', null], true) || $st === 'erro' || $st === 'pendente';
    }

    /** Número MTR exibido em listagens, portal e impressão. */
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

    /** Relatório/MTR em PDF — permitido em rascunho e finalizada (não cancelada). */
    public static function podeImprimirRelatorio(Coleta $c): bool
    {
        return ($c->status ?? '') !== 'cancelada';
    }

    /** Campo MTRº na impressão (número oficial ou rótulo da fase atual). */
    public static function rotuloImpressao(Coleta $c): string
    {
        $num = self::numeroExibicao($c);
        if ($num !== null) {
            return $num;
        }
        if (($c->status ?? '') === 'rascunho') {
            return 'RASCUNHO · #'.$c->id;
        }

        $rel = self::numeroRelatorioExibicao($c);
        if ($rel !== null && !self::temMtr($c)) {
            return 'REL. '.$rel;
        }

        $sem = self::rotuloSemMtr($c);

        return $sem !== '—' ? mb_strtoupper($sem, 'UTF-8') : 'SEM NÚMERO MTR';
    }

    public static function rotuloSemMtr(Coleta $c): string
    {
        if ($c->status === 'rascunho') {
            return 'Rascunho';
        }
        if ($c->status !== 'finalizada') {
            return '—';
        }
        if (self::numeroRelatorioExibicao($c)) {
            return 'Só relatório';
        }
        if (!SinirConfig::isEnabled()) {
            return 'Sem número';
        }

        $st = $c->sinir_status ?? '';

        return match ($st) {
            'erro' => 'Erro SINIR',
            'cancelado' => 'Cancelado SINIR',
            'enviado' => '—',
            'pendente' => 'Aguard. SINIR',
            default => 'MTR pendente',
        };
    }
}
