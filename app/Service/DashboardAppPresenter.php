<?php

namespace App\Service;

use App\Common\Helpers\ColetorSelectHelper;
use App\Common\Helpers\ModuleGateHelper;
use App\Model\Entity\Coleta as EntityColeta;

/**
 * Payload do dashboard mobile (Well Coletas) — alinhado ao painel admin Operação.
 */
class DashboardAppPresenter
{
    /** @param array<string,mixed> $user sessão API (id, is_admin, funcao_nome, modulos) */
    public static function resumo(array $user): array
    {
        $userId = (int)($user['id'] ?? 0);
        $isAdmin = !empty($user['is_admin']);
        $kpis = self::kpis($user);
        $graficos = [
            'coletas_por_mes' => DashboardService::coletasPorMesColetor($userId, $isAdmin, 6),
        ];
        if (self::canModule($user, 'coletas')) {
            $graficos['coletas_por_status'] = DashboardService::coletasPorStatusColetor($userId, $isAdmin);
        }

        return [
            'meta' => [
                'schema_version' => 2,
                'hoje' => date('Y-m-d'),
            ],
            'kpis' => $kpis,
            'graficos' => $graficos,
            'cards' => self::cards($user, $kpis),
            'atalhos' => self::atalhos($user),
            'atividades' => self::atividadesRecentes($user),
        ];
    }

    /** @param array<string,mixed> $user */
    private static function kpis(array $user): array
    {
        $panel = DashboardService::kpisPanel($user);
        $userId = (int)($user['id'] ?? 0);
        $isAdmin = !empty($user['is_admin']);
        $urgentesAtrasados = RotaScopeService::countUrgentesAtrasados($userId, $isAdmin);

        return [
            'hoje' => date('Y-m-d'),
            'coletas_hoje' => (int)($panel['coletas_hoje'] ?? 0),
            'coletas_mes' => (int)($panel['coletas_mes'] ?? 0),
            'rascunhos' => (int)($panel['rascunhos'] ?? 0),
            'paradas_hoje' => (int)($panel['paradas_hoje'] ?? 0),
            'urgentes' => (int)($urgentesAtrasados['urgentes'] ?? 0),
            'atrasados' => (int)($urgentesAtrasados['atrasados'] ?? 0),
            'clientes_ativos' => (int)($panel['clientes_ativos'] ?? 0),
            'solicitacoes_pendentes' => (int)($panel['solicitacoes_pendentes'] ?? 0),
            'mtr_sinir_pendente' => (int)($panel['mtr_sinir_pendente'] ?? 0),
            'rotas_com_coleta_hoje' => (int)($panel['rotas_com_coleta_hoje'] ?? 0),
        ];
    }

    /**
     * @param array<string,int|string> $kpis
     * @return list<array{key:string,module:string,label:string,hint:string,value:int,accent:string}>
     */
    private static function cards(array $user, array $kpis): array
    {
        $defs = [
            [
                'key' => 'coletas_hoje',
                'module' => 'coletas',
                'label' => 'Coletas finalizadas hoje',
                'hint' => 'Ver coletas de hoje',
                'accent' => 'primary',
            ],
            [
                'key' => 'rascunhos',
                'module' => 'coletas',
                'label' => 'Rascunhos abertos',
                'hint' => 'Continuar lançamento',
                'accent' => 'warning',
            ],
            [
                'key' => 'paradas_hoje',
                'module' => 'rota_dia',
                'label' => 'Paradas agendadas hoje',
                'hint' => 'Abrir rota do dia',
                'accent' => 'info',
            ],
            [
                'key' => 'urgentes',
                'module' => 'rota_dia',
                'label' => 'Clientes urgentes',
                'hint' => 'Prioridade na rota',
                'accent' => 'warning',
            ],
            [
                'key' => 'atrasados',
                'module' => 'rota_dia',
                'label' => 'Clientes atrasados',
                'hint' => 'Prioridade na rota',
                'accent' => 'danger',
            ],
            [
                'key' => 'clientes_ativos',
                'module' => 'clientes',
                'label' => 'Clientes ativos',
                'hint' => 'Base cadastral',
                'accent' => 'success',
            ],
            [
                'key' => 'solicitacoes_pendentes',
                'module' => 'agendamentos',
                'label' => 'Solicitações portal',
                'hint' => 'Pendentes de aprovação',
                'accent' => 'danger',
            ],
            [
                'key' => 'mtr_sinir_pendente',
                'module' => 'coletas',
                'label' => 'MTR SINIR pendente',
                'hint' => 'Coletas que exigem MTR',
                'accent' => 'secondary',
            ],
            [
                'key' => 'rotas_com_coleta_hoje',
                'module' => 'rota_dia',
                'label' => 'Rotas com coleta hoje',
                'hint' => 'Rotas cadastrais',
                'accent' => 'secondary',
            ],
            [
                'key' => 'coletas_mes',
                'module' => 'coletas',
                'label' => 'Coletas no mês',
                'hint' => 'Finalizadas no mês',
                'accent' => 'primary',
            ],
        ];

        $out = [];
        foreach ($defs as $def) {
            if (!self::canModule($user, $def['module'])) {
                continue;
            }
            $key = $def['key'];
            $out[] = [
                'key' => $key,
                'module' => $def['module'],
                'label' => $def['label'],
                'hint' => $def['hint'],
                'value' => (int)($kpis[$key] ?? 0),
                'accent' => $def['accent'],
            ];
        }

        return $out;
    }

    /** @return list<array{slug:string,label:string,target:string}> */
    private static function atalhos(array $user): array
    {
        $defs = [
            ['slug' => 'coleta_nova', 'label' => 'Nova coleta', 'target' => 'NewCollectionClientSelection'],
            ['slug' => 'coletas', 'label' => 'Minhas coletas', 'target' => 'CollectionsList'],
            ['slug' => 'rota_dia', 'label' => 'Ver rota', 'target' => 'RouteOfTheDay'],
            ['slug' => 'agendamentos', 'label' => 'Agendamentos', 'target' => 'AgendamentosList'],
            ['slug' => 'frota_mapa', 'label' => 'Mapa frota', 'target' => 'FleetMap'],
        ];
        $out = [];
        foreach ($defs as $def) {
            if (!self::canModule($user, $def['slug'])) {
                continue;
            }
            $out[] = $def;
        }

        return $out;
    }

    /**
     * @return list<array{tipo:string,titulo:string,subtitulo:string,data_hora:?string,ref_id:int}>
     */
    private static function atividadesRecentes(array $user): array
    {
        if (!self::canModule($user, 'coletas')) {
            return [];
        }

        $userId = (int)($user['id'] ?? 0);
        $isAdmin = !empty($user['is_admin']);
        $isColetor = ColetorSelectHelper::isColetorSession($user);

        $where = "c.status = 'finalizada'";
        $params = [];
        if ($isColetor && !$isAdmin) {
            $where .= ' AND c.coletor_id = ?';
            $params[] = $userId;
        }

        $out = [];
        foreach (EntityColeta::list($where, $params, '5') as $c) {
            $dataHora = trim((string)($c->data_coleta ?? ''));
            if ($dataHora !== '' && trim((string)($c->hora ?? '')) !== '') {
                $dataHora .= ' '.trim((string)$c->hora);
            }
            $out[] = [
                'tipo' => 'coleta',
                'titulo' => trim($c->cliente_nome) !== '' ? $c->cliente_nome : 'Coleta #'.$c->id,
                'subtitulo' => 'Coleta finalizada',
                'data_hora' => $dataHora !== '' ? $dataHora : null,
                'ref_id' => $c->id,
            ];
        }

        return $out;
    }

    private static function canModule(array $user, string $slug): bool
    {
        return ModuleGateHelper::podeAcessar($slug, $user);
    }
}
