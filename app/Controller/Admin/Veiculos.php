<?php

namespace App\Controller\Admin;

use App\Common\Helpers\CrudHelper;
use App\Model\Db\Pagination;
use App\Model\Entity\Usuario as EntityUsuario;
use App\Model\Entity\Veiculo as EntityVeiculo;
use App\Service\VeiculoFrotaService;
use App\Session\User\Login as SessionUser;
use App\Utils\View;

class Veiculos extends Page
{
    public static function index($request): string
    {
        $content = View::render('admin/modules/veiculos/index', [
            'csrf_field' => \App\Common\Helpers\CsrfHelper::field(),
        ]);

        return self::getPage('Veículos', $content, 'veiculos', self::crudScripts('/painel/veiculos'));
    }

    public static function list($request): string
    {
        $post = $request->getPostVars();
        $page = (int)($post['page'] ?? 1);
        $busca = trim((string)($post['busca'] ?? ''));

        $where = '1=1';
        $params = [];
        $ativo = trim((string)($post['ativo'] ?? ''));
        if ($ativo === '1' || $ativo === '0') {
            $where .= ' AND v.ativo = ?';
            $params[] = (int)$ativo;
        } else {
            $where .= ' AND v.ativo = 1';
        }
        $situacao = trim((string)($post['situacao'] ?? ''));
        if (in_array($situacao, ['disponivel', 'em_uso', 'manutencao'], true)) {
            $where .= ' AND v.situacao = ?';
            $params[] = $situacao;
        }
        if ($busca !== '') {
            $where .= ' AND (v.placa LIKE ? OR v.modelo LIKE ? OR v.marca LIKE ?)';
            $params = array_merge($params, array_fill(0, 3, '%'.$busca.'%'));
        }

        $pagination = new Pagination(EntityVeiculo::countPainel($where, $params), $page, 10);
        $rows = EntityVeiculo::listPainel($where, $params, $pagination->getLimit());

        $itens = '';
        foreach ($rows as $v) {
            $emUso = $v->em_uso_nome !== '' ? CrudHelper::e($v->em_uso_nome) : '—';
            $itens .= '<tr>
                <td>'.CrudHelper::e($v->placa).'</td>
                <td>'.CrudHelper::e(trim($v->marca.' '.$v->modelo)).'</td>
                <td>'.self::badgeSituacao($v->situacao).'</td>
                <td>'.self::km($v->km_atual).'</td>
                <td>'.$emUso.'</td>
                <td>
                    <a class="btn btn-sm btn-outline-secondary" href="'.URL.'/painel/veiculos/'.$v->id.'" title="Ficha"><i class="fas fa-clipboard-list"></i></a>
                    <button class="btn btn-sm btn-outline-primary" onclick="editar('.$v->id.')"><i class="fas fa-edit"></i></button>
                    '.CrudHelper::btnDesativar($v->id).'
                </td>
            </tr>';
        }
        if ($itens === '') {
            $itens = '<tr><td colspan="6" class="text-center text-muted">Nenhum veículo.</td></tr>';
        }

        return self::jsonLista(['success' => true, 'itens' => $itens, 'pagination' => Pagination::renderNav($pagination)]);
    }

    public static function get($request): string
    {
        $id = (int)($request->getPostVars()['id'] ?? 0);
        $v = EntityVeiculo::getById($id);
        if (!$v) {
            return CrudHelper::jsonError('Veículo não encontrado.');
        }

        return CrudHelper::jsonOk([
            'id' => $v->id,
            'placa' => $v->placa,
            'marca' => $v->marca,
            'modelo' => $v->modelo,
            'ano' => $v->ano,
            'cor' => $v->cor,
            'combustivel' => $v->combustivel,
            'ativo' => $v->ativo,
        ]);
    }

    public static function save($request): string
    {
        $post = $request->getPostVars();
        if ($err = CrudHelper::requireCsrf($post)) {
            return CrudHelper::jsonError($err);
        }
        $id = (int)($post['id'] ?? 0);
        try {
            VeiculoFrotaService::salvarCadastro($id, [
                'placa' => $post['placa'] ?? '',
                'marca' => $post['marca'] ?? '',
                'modelo' => $post['modelo'] ?? '',
                'ano' => $post['ano'] ?? '',
                'cor' => $post['cor'] ?? '',
                'combustivel' => $post['combustivel'] ?? 'diesel',
                'ativo' => CrudHelper::parseAtivo($post, $id <= 0),
            ]);
        } catch (\InvalidArgumentException $e) {
            return CrudHelper::jsonError($e->getMessage());
        } catch (\Throwable $e) {
            error_log('[Veiculos::save] '.$e->getMessage());

            return CrudHelper::jsonError('Não foi possível salvar o veículo.');
        }

        return CrudHelper::jsonOk(['message' => 'Salvo com sucesso.']);
    }

    public static function delete($request): string
    {
        $post = $request->getPostVars();
        if ($err = CrudHelper::requireCsrf($post)) {
            return CrudHelper::jsonError($err);
        }
        try {
            VeiculoFrotaService::desativar((int)($post['id'] ?? 0));
        } catch (\InvalidArgumentException $e) {
            return CrudHelper::jsonError($e->getMessage());
        } catch (\Throwable $e) {
            error_log('[Veiculos::delete] '.$e->getMessage());

            return CrudHelper::jsonError('Não foi possível desativar o veículo.');
        }

        return CrudHelper::jsonOk(['message' => 'Veículo desativado.']);
    }

    public static function ficha($request, int $id): string
    {
        $v = EntityVeiculo::getById($id);
        if (!$v) {
            return self::getPage('Veículos', '<div class="alert alert-warning mt-3">Veículo não encontrado.</div>', 'veiculos');
        }
        $h = VeiculoFrotaService::historico($id);
        $aberta = $h['manutencao_aberta'];
        $podeOperar = $v->ativo === 1;
        $nomeUso = '';
        if ($v->em_uso_usuario_id) {
            $nomeUso = EntityUsuario::getById($v->em_uso_usuario_id)?->nome ?? '';
        }
        $content = View::render('admin/modules/veiculos/ficha', [
            'id' => (string)$v->id,
            'csrf_field' => \App\Common\Helpers\CsrfHelper::field(),
            'titulo' => CrudHelper::e(trim($v->marca.' '.$v->modelo.' — '.$v->placa)),
            'placa' => CrudHelper::e($v->placa),
            'ano_cor' => CrudHelper::e(trim($v->ano.' '.$v->cor)),
            'situacao_badge' => self::badgeSituacao($v->situacao),
            'km_atual' => self::km($v->km_atual),
            'combustivel' => CrudHelper::e(VeiculoFrotaService::combustivelLabel($v->combustivel)),
            'em_uso' => $v->situacao === 'em_uso'
                ? CrudHelper::e($nomeUso !== '' ? $nomeUso : 'Coletor').' desde '.self::quando($v->em_uso_desde)
                : '—',
            'na_frota' => $v->ativo === 1 ? 'Sim' : 'Não',
            'coletores_options' => self::opcoesColetores(),
            'abastecido_padrao' => date('Y-m-d\TH:i'),
            'km_input' => (string)$v->km_atual,
            'mostrar_iniciar' => $podeOperar && $v->situacao === 'disponivel' ? '' : 'd-none',
            'mostrar_encerrar' => $podeOperar && $v->situacao === 'em_uso' ? '' : 'd-none',
            'mostrar_abrir' => $podeOperar && $aberta === null ? '' : 'd-none',
            'mostrar_fechar' => $aberta !== null ? '' : 'd-none',
            'manutencao_id' => (string)(int)($aberta['id'] ?? 0),
            'manutencao_resumo' => $aberta
                ? CrudHelper::e(($aberta['tipo'] === 'corretiva' ? 'Corretiva' : 'Preventiva').' — '.(string)$aberta['descricao'])
                : '',
            'usos_html' => self::tabelaUsos($h['usos']),
            'kms_html' => self::tabelaKm($h['kms']),
            'abastecimentos_html' => self::tabelaAbastecimentos($h['abastecimentos']),
            'manutencoes_html' => self::tabelaManutencoes($h['manutencoes']),
        ]);

        return self::getPage('Veículo '.$v->placa, $content, 'veiculos');
    }

    public static function fichaAcao($request, int $id): string
    {
        $post = $request->getPostVars();
        if ($err = CrudHelper::requireCsrf($post)) {
            return CrudHelper::jsonError($err);
        }
        $acao = (string)($post['acao'] ?? '');
        $usuarioId = (int)(SessionUser::getUserLogedData()['usuario']['id'] ?? 0);
        try {
            match ($acao) {
                'iniciar_uso' => VeiculoFrotaService::iniciarUso(
                    $id,
                    (int)($post['coletor_id'] ?? 0),
                    (int)($post['km'] ?? 0),
                    'painel'
                ),
                'registrar_km' => VeiculoFrotaService::registrarKm(
                    $id,
                    $usuarioId,
                    (int)($post['km'] ?? 0),
                    'painel',
                    (string)($post['observacao'] ?? '')
                ),
                'abastecer' => VeiculoFrotaService::abastecer($id, $usuarioId, $post, 'painel'),
                'encerrar_uso' => VeiculoFrotaService::encerrarUso(
                    $id,
                    $usuarioId,
                    (int)($post['km'] ?? 0),
                    'painel'
                ),
                'abrir_manutencao' => VeiculoFrotaService::abrirManutencao(
                    $id,
                    $usuarioId,
                    (string)($post['tipo'] ?? ''),
                    (string)($post['descricao'] ?? '')
                ),
                'concluir_manutencao' => VeiculoFrotaService::concluirManutencao(
                    $id,
                    (int)($post['manutencao_id'] ?? 0),
                    (string)($post['custo'] ?? ''),
                    (string)($post['observacao'] ?? '')
                ),
                'cancelar_manutencao' => VeiculoFrotaService::cancelarManutencao(
                    $id,
                    (int)($post['manutencao_id'] ?? 0),
                    (string)($post['observacao'] ?? '')
                ),
                default => throw new \InvalidArgumentException('Ação inválida.'),
            };
        } catch (\InvalidArgumentException $e) {
            return CrudHelper::jsonError($e->getMessage());
        } catch (\Throwable $e) {
            error_log('[Veiculos::ficha] '.$e->getMessage());

            return CrudHelper::jsonError('Não foi possível concluir. Tente de novo.');
        }

        return CrudHelper::jsonOk(['message' => 'Registrado.']);
    }

    private static function badgeSituacao(string $situacao): string
    {
        $cls = match ($situacao) {
            'em_uso' => 'text-bg-primary',
            'manutencao' => 'text-bg-warning',
            default => 'text-bg-success',
        };

        return '<span class="badge '.$cls.'">'.CrudHelper::e(VeiculoFrotaService::situacaoLabel($situacao)).'</span>';
    }

    private static function opcoesColetores(): string
    {
        $html = '<option value="">— Selecione —</option>';
        foreach (EntityUsuario::list("u.ativo = 's'", [], '300') as $u) {
            $html .= '<option value="'.$u->id.'">'.CrudHelper::e($u->nome).'</option>';
        }

        return $html;
    }

    /** @param list<array<string, mixed>> $rows */
    private static function tabelaUsos(array $rows): string
    {
        if ($rows === []) {
            return self::vazio(6);
        }
        $html = '';
        foreach ($rows as $r) {
            $fim = $r['encerrado_em'] ? self::quando((string)$r['encerrado_em']) : 'Em aberto';
            $por = match ((string)($r['encerrado_por'] ?? '')) {
                'coletor' => 'Coletor',
                'painel' => 'Painel',
                default => '—',
            };
            $html .= '<tr>
                <td>'.CrudHelper::e((string)$r['usuario_nome']).'</td>
                <td>'.self::quando((string)$r['iniciado_em']).'</td>
                <td>'.$fim.'</td>
                <td>'.self::km((int)$r['km_inicio']).'</td>
                <td>'.($r['km_fim'] !== null ? self::km((int)$r['km_fim']) : '—').'</td>
                <td>'.CrudHelper::e($por).'</td>
            </tr>';
        }

        return $html;
    }

    /** @param list<array<string, mixed>> $rows */
    private static function tabelaKm(array $rows): string
    {
        if ($rows === []) {
            return self::vazio(4);
        }
        $html = '';
        foreach ($rows as $r) {
            $html .= '<tr>
                <td>'.self::quando((string)$r['registrado_em']).'</td>
                <td>'.self::km((int)$r['km']).'</td>
                <td>'.CrudHelper::e((string)$r['usuario_nome']).'</td>
                <td>'.CrudHelper::e((string)($r['observacao'] ?? '')).'</td>
            </tr>';
        }

        return $html;
    }

    /** @param list<array<string, mixed>> $rows */
    private static function tabelaAbastecimentos(array $rows): string
    {
        if ($rows === []) {
            return self::vazio(6);
        }
        $html = '';
        foreach ($rows as $r) {
            $valor = $r['valor'] !== null && $r['valor'] !== ''
                ? 'R$ '.number_format((float)$r['valor'], 2, ',', '.')
                : '—';
            $html .= '<tr>
                <td>'.self::quando((string)$r['abastecido_em']).'</td>
                <td>'.number_format((float)$r['litros'], 3, ',', '.').'</td>
                <td>'.$valor.'</td>
                <td>'.self::km((int)$r['km']).'</td>
                <td>'.CrudHelper::e((string)($r['posto'] ?? '')).'</td>
                <td>'.CrudHelper::e((string)$r['usuario_nome']).'</td>
            </tr>';
        }

        return $html;
    }

    /** @param list<array<string, mixed>> $rows */
    private static function tabelaManutencoes(array $rows): string
    {
        if ($rows === []) {
            return self::vazio(6);
        }
        $html = '';
        foreach ($rows as $r) {
            $status = match ((string)$r['status']) {
                'aberta' => 'Aberta',
                'cancelada' => 'Cancelada',
                default => 'Concluída',
            };
            $custo = $r['custo'] !== null && $r['custo'] !== ''
                ? 'R$ '.number_format((float)$r['custo'], 2, ',', '.')
                : '—';
            $html .= '<tr>
                <td>'.self::quando((string)$r['aberta_em']).'</td>
                <td>'.CrudHelper::e((string)$r['tipo'] === 'corretiva' ? 'Corretiva' : 'Preventiva').'</td>
                <td>'.CrudHelper::e($status).'</td>
                <td>'.CrudHelper::e((string)$r['descricao']).'</td>
                <td>'.$custo.'</td>
                <td>'.CrudHelper::e((string)$r['usuario_nome']).'</td>
            </tr>';
        }

        return $html;
    }

    private static function vazio(int $cols): string
    {
        return '<tr><td colspan="'.$cols.'" class="text-center text-muted">Nenhum registro.</td></tr>';
    }

    private static function km(int $km): string
    {
        return number_format($km, 0, ',', '.');
    }

    private static function quando(?string $dt): string
    {
        if ($dt === null || $dt === '') {
            return '—';
        }
        $t = strtotime($dt);

        return $t ? date('d/m/Y H:i', $t) : '—';
    }
}
