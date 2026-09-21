<?php

namespace App\Common\Helpers;

class FormatHelper
{
    public static function dateBr(?string $ymd): string
    {
        if ($ymd === null || trim($ymd) === '') {
            return '—';
        }
        $ts = strtotime(substr($ymd, 0, 10));

        return $ts ? date('d/m/Y', $ts) : '—';
    }

    public static function dateTimeBr(?string $datetime): string
    {
        if ($datetime === null || trim($datetime) === '') {
            return '—';
        }
        $ts = strtotime($datetime);

        return $ts ? date('d/m/Y H:i', $ts) : '—';
    }

    public static function competenciaBr(?string $ym): string
    {
        if ($ym === null || trim($ym) === '') {
            return '—';
        }
        $ts = strtotime($ym.'-01');

        return $ts ? date('m/Y', $ts) : '—';
    }

    public static function horaBr(?string $hora): string
    {
        if ($hora === null || trim($hora) === '') {
            return '—';
        }

        return substr($hora, 0, 5);
    }

    /** Valor cobrado no boleto (ajuste admin = valor_nominal). */
    public static function valorBoleto(float $valorNominal, float $valorCalculado = 0.0): float
    {
        if ($valorNominal > 0) {
            return $valorNominal;
        }

        return $valorCalculado > 0 ? $valorCalculado : 0.0;
    }

    /** @return array{label:string,class:string} */
    public static function statusColeta(string $status): array
    {
        return match ($status) {
            'finalizada' => ['label' => 'Finalizada', 'class' => 'success'],
            'rascunho' => ['label' => 'Rascunho', 'class' => 'warning'],
            'cancelada' => ['label' => 'Cancelada', 'class' => 'secondary'],
            default => ['label' => ucfirst($status), 'class' => 'secondary'],
        };
    }

    /** @return array{label:string,class:string} */
    public static function statusBoleto(string $status): array
    {
        return match (strtoupper($status)) {
            'PAGO', 'RECEBIDO' => ['label' => 'Pago', 'class' => 'success'],
            'EMITIDA', 'A_RECEBER' => ['label' => 'Em aberto', 'class' => 'primary'],
            'VENCIDA', 'ATRASADO' => ['label' => 'Vencido', 'class' => 'danger'],
            'CANCELADA', 'CANCELADO' => ['label' => 'Cancelado', 'class' => 'secondary'],
            default => ['label' => $status, 'class' => 'secondary'],
        };
    }

    public static function badge(string $label, string $bootstrapClass = 'secondary'): string
    {
        return '<span class="badge bg-'.htmlspecialchars($bootstrapClass, ENT_QUOTES, 'UTF-8').'">'
            .htmlspecialchars($label, ENT_QUOTES, 'UTF-8').'</span>';
    }

    public static function statusColetaBadge(string $status): string
    {
        $s = self::statusColeta($status);

        return self::badge($s['label'], $s['class']);
    }

    public static function statusBoletoBadge(string $status): string
    {
        $s = self::statusBoleto($status);

        return self::badge($s['label'], $s['class']);
    }

    public static function statusSolicitacaoBadge(string $status): string
    {
        return match ($status) {
            'pendente' => self::badge('Pendente', 'primary'),
            'aprovada' => self::badge('Aprovada', 'success'),
            'recusada' => self::badge('Recusada', 'danger'),
            'cancelada' => self::badge('Cancelada', 'secondary'),
            default => self::badge(ucfirst($status), 'secondary'),
        };
    }

    public static function situacaoRecebimentoBadge(string $situacao): string
    {
        return $situacao === 'recebido'
            ? self::badge('Recebido', 'success')
            : self::badge('Pendente', 'warning');
    }

    public static function quantidade(float $qtd, string $unidade): string
    {
        return number_format($qtd, 3, ',', '.').' '.strtoupper($unidade);
    }

    public static function money(float $valor): string
    {
        return 'R$ '.number_format($valor, 2, ',', '.');
    }
}
