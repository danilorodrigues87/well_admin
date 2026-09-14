<?php

namespace App\Service\Sinir;

use App\Common\Helpers\CrudHelper;
use App\Common\SinirConfig;
use App\Model\Db\Database;
use PDO;

class SinirService
{
    /**
     * Badge HTML para listagem de coletas.
     */
    public static function renderStatusBadge(?string $sinirStatus, string $coletaStatus): string
    {
        if ($coletaStatus !== 'finalizada') {
            return '<span class="text-muted">—</span>';
        }

        $status = $sinirStatus ?: 'pendente';
        [$label, $css] = match ($status) {
            'enviado' => ['Enviado SINIR', 'success'],
            'erro' => ['Erro SINIR', 'danger'],
            default => ['Pendente SINIR', 'warning'],
        };

        return '<span class="badge bg-'.$css.'">'.CrudHelper::e($label).'</span>';
    }

    /** @return array{total:int,com_ibama:int,sem_ibama:int,com_sinir:int,sem_sinir:int} */
    public static function auditCatalogoResiduos(): array
    {
        $db = new Database();
        $row = $db->execute(
            "SELECT
                COUNT(*) AS total,
                SUM(CASE WHEN cod_ibama IS NOT NULL AND cod_ibama != '' THEN 1 ELSE 0 END) AS com_ibama,
                SUM(CASE WHEN COALESCE(tra_codigo, 0) > 0
                          AND COALESCE(tie_codigo, 0) > 0
                          AND COALESCE(tia_codigo, 0) > 0
                          AND COALESCE(cla_codigo, 0) > 0
                          AND COALESCE(uni_codigo, 0) > 0 THEN 1 ELSE 0 END) AS com_sinir
             FROM tipos_residuos WHERE ativo = 1"
        )->fetch(PDO::FETCH_ASSOC);

        $total = (int)($row['total'] ?? 0);
        $comIbama = (int)($row['com_ibama'] ?? 0);
        $comSinir = (int)($row['com_sinir'] ?? 0);

        return [
            'total' => $total,
            'com_ibama' => $comIbama,
            'sem_ibama' => $total - $comIbama,
            'com_sinir' => $comSinir,
            'sem_sinir' => $total - $comSinir,
        ];
    }

    public static function isReady(): bool
    {
        return SinirConfig::isEnabled() && SinirConfig::isConfigured();
    }

    /**
     * @return array{ok:bool,skipped?:bool,message:string,details?:array<string,mixed>}
     */
    public static function enviarColeta(int $coletaId, bool $force = false): array
    {
        return (new SinirManifestoService())->enviarColeta($coletaId, $force);
    }

    /**
     * Smoke test: valida POST /token (Fase A).
     *
     * @return array{ok:bool,message:string,details:array<string,mixed>}
     */
    public static function smokeTestToken(): array
    {
        if (!SinirConfig::isConfigured()) {
            return [
                'ok' => false,
                'message' => 'Configure SINIR_INTEGRATION_TOKEN, SINIR_UNIDADE e SINIR_CNPJ no .env',
                'details' => ['configured' => false],
            ];
        }

        $auth = new SinirAuthService();
        SinirAuthService::clearCache();
        $result = $auth->obtainAccessToken(true);

        return [
            'ok' => $result['ok'],
            'message' => $result['ok']
                ? 'Token de acesso obtido com sucesso.'
                : ('Falha na autenticação: '.($result['error'] ?? 'erro desconhecido')),
            'details' => [
                'configured' => true,
                'enabled' => SinirConfig::isEnabled(),
                'base_url' => SinirConfig::baseUrl(),
                'unidade' => SinirConfig::unidade(),
                'http_status' => $result['raw_status'],
                'expires_in' => $result['expires_in'],
                'token_preview' => $result['token']
                    ? substr($result['token'], 0, 8).'…'
                    : null,
            ],
        ];
    }
}
