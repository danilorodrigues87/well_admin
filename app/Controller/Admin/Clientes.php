<?php

namespace App\Controller\Admin;

use App\Common\Helpers\CrudHelper;
use App\Model\Db\Pagination;
use App\Model\Entity\Cliente as EntityCliente;
use App\Model\Entity\ClienteUsuario;
use App\Model\Entity\Plano as EntityPlano;
use App\Service\GoogleMapsService;
use App\Utils\View;

class Clientes extends Page
{
    private const SENHA_PORTAL_PADRAO = '12345678';

    private static function planosOptions(bool $comTodos = false): string
    {
        $html = $comTodos ? '' : '<option value="">— Sem plano —</option>';
        foreach (EntityPlano::getAllActive() as $p) {
            $html .= '<option value="'.$p->id.'">'.CrudHelper::e($p->nome).'</option>';
        }
        return $html;
    }

    public static function index($request): string
    {
        $content = View::render('admin/modules/clientes/index', [
            'csrf_field' => \App\Common\Helpers\CsrfHelper::field(),
            'planos_options' => self::planosOptions(true),
            'planos_options_modal' => self::planosOptions(false),
        ]);
        $scripts = self::crudScripts('/painel/clientes')
            .'<script src="'.URL.'/resources/js/crud-clientes.js?v=20260916h"></script>';

        return self::getPage('Clientes', $content, 'clientes', $scripts);
    }

    public static function list($request): string
    {
        $post = $request->getPostVars();
        $page = (int)($post['page'] ?? 1);
        $busca = trim((string)($post['busca'] ?? ''));

        $where = '1=1';
        $params = [];
        $statusFiltro = trim((string)($post['status'] ?? ''));
        if (in_array($statusFiltro, ['ativo', 'suspenso', 'inativo'], true)) {
            $where .= ' AND c.status = ?';
            $params[] = $statusFiltro;
        } else {
            $where .= " AND c.status != 'inativo'";
        }
        $planoId = (int)($post['plano_id'] ?? 0);
        if ($planoId > 0) {
            $where .= ' AND c.plano_id = ?';
            $params[] = $planoId;
        }
        $prioridade = trim((string)($post['prioridade'] ?? ''));
        if (in_array($prioridade, ['normal', 'urgente'], true)) {
            $where .= ' AND c.prioridade = ?';
            $params[] = $prioridade;
        }
        if ($busca !== '') {
            $where .= ' AND (c.nome_fantasia LIKE ? OR c.cnpj LIKE ? OR c.cidade LIKE ?)';
            $params = array_merge($params, array_fill(0, 3, '%'.$busca.'%'));
        }

        $pagination = new Pagination(EntityCliente::count($where, $params), $page, 10);
        $rows = EntityCliente::list($where, $params, $pagination->getLimit());
        $portalMap = ClienteUsuario::mapPortalStatus(array_map(fn ($c) => $c->id, $rows));

        $itens = '';
        foreach ($rows as $c) {
            $portalStatus = $portalMap[$c->id] ?? 'sem';
            $portalBadge = match ($portalStatus) {
                'ativo' => '<span class="badge bg-success" title="Portal ativo"><i class="fas fa-check"></i> Portal</span>',
                'inativo' => '<span class="badge bg-secondary" title="Acesso desativado">Inativo</span>',
                default => '<span class="text-muted small">—</span>',
            };

            $itens .= '<tr>
                <td>'.CrudHelper::e($c->nome_fantasia).'</td>
                <td>'.CrudHelper::e($c->cnpj).'</td>
                <td>'.CrudHelper::e($c->cidade).'/'.CrudHelper::e($c->uf).'</td>
                <td>'.CrudHelper::e($c->plano_nome).'</td>
                <td>'.CrudHelper::e($c->status).'</td>
                <td class="text-center">'.$portalBadge.'</td>
                <td>
                    <button class="btn btn-sm btn-outline-primary" onclick="editar('.$c->id.')" title="Editar"><i class="fas fa-edit"></i></button>
                    <a class="btn btn-sm btn-outline-info" href="'.URL.'/painel/clientes/'.$c->id.'/contratos" title="Contratos"><i class="fas fa-file-signature"></i></a>
                    <button type="button" class="btn btn-sm btn-outline-secondary btn-portal-acesso" data-cliente-id="'.$c->id.'" data-cliente-nome="'.CrudHelper::e($c->nome_fantasia).'" title="Acesso portal"><i class="fas fa-user-lock"></i></button>
                    '.CrudHelper::btnDesativar($c->id).'
                </td>
            </tr>';
        }
        if ($itens === '') {
            $itens = '<tr><td colspan="7" class="text-center text-muted">Nenhum cliente.</td></tr>';
        }

        return self::jsonLista(['success' => true, 'itens' => $itens, 'pagination' => Pagination::renderNav($pagination)]);
    }

    public static function get($request): string
    {
        $id = (int)($request->getPostVars()['id'] ?? 0);
        $c = EntityCliente::getById($id);
        if (!$c) {
            return CrudHelper::jsonError('Cliente não encontrado.');
        }
        return CrudHelper::jsonOk([
            'id' => $c->id,
            'nome_fantasia' => $c->nome_fantasia,
            'razao_social' => $c->razao_social,
            'cnpj' => $c->cnpj,
            'sinir_cod_unidade' => $c->sinir_cod_unidade,
            'exige_mtr' => $c->exige_mtr,
            'email' => $c->email,
            'telefone' => $c->telefone,
            'plano_id' => $c->plano_id,
            'status' => $c->status,
            'logradouro' => $c->logradouro,
            'numero' => $c->numero,
            'bairro' => $c->bairro,
            'cep' => $c->cep,
            'cidade' => $c->cidade,
            'uf' => $c->uf,
            'responsavel' => $c->responsavel,
            'telefone_resp' => $c->telefone_resp,
            'maps_link' => $c->maps_link,
            'latitude' => $c->latitude,
            'longitude' => $c->longitude,
            'geocode_status' => $c->geocode_status,
        ]);
    }

    public static function save($request): string
    {
        $post = $request->getPostVars();
        if ($err = CrudHelper::requireCsrf($post)) {
            return CrudHelper::jsonError($err);
        }

        $id = (int)($post['id'] ?? 0);
        $planoId = (int)($post['plano_id'] ?? 0);

        $data = [
            'nome_fantasia' => trim((string)($post['nome_fantasia'] ?? '')),
            'razao_social' => trim((string)($post['razao_social'] ?? '')),
            'cnpj' => trim((string)($post['cnpj'] ?? '')),
            'sinir_cod_unidade' => ($v = (int)($post['sinir_cod_unidade'] ?? 0)) > 0 ? $v : null,
            'exige_mtr' => !empty($post['exige_mtr']) ? 1 : 0,
            'email' => trim((string)($post['email'] ?? '')),
            'telefone' => trim((string)($post['telefone'] ?? '')),
            'plano_id' => $planoId > 0 ? $planoId : null,
            'status' => in_array($post['status'] ?? '', ['ativo', 'suspenso', 'inativo'], true) ? $post['status'] : 'ativo',
            'logradouro' => trim((string)($post['logradouro'] ?? '')),
            'numero' => trim((string)($post['numero'] ?? '')),
            'bairro' => trim((string)($post['bairro'] ?? '')),
            'cep' => trim((string)($post['cep'] ?? '')),
            'cidade' => trim((string)($post['cidade'] ?? '')),
            'uf' => strtoupper(substr(trim((string)($post['uf'] ?? '')), 0, 2)),
            'responsavel' => trim((string)($post['responsavel'] ?? '')),
            'telefone_resp' => trim((string)($post['telefone_resp'] ?? '')),
            'maps_link' => trim((string)($post['maps_link'] ?? '')),
            'geocode_status' => 'pendente',
        ];

        if ($data['nome_fantasia'] === '' || $data['razao_social'] === '') {
            return CrudHelper::jsonError('Nome fantasia e razão social são obrigatórios.');
        }

        try {
            if ($id > 0) {
                EntityCliente::update($id, $data);
                $savedId = $id;
            } else {
                $savedId = EntityCliente::insert($data);
            }
            GoogleMapsService::geocodeCliente($savedId);
        } catch (\Throwable $e) {
            return CrudHelper::jsonError('Erro ao salvar cliente. Verifique os dados e tente novamente.');
        }

        return CrudHelper::jsonOk(['message' => 'Cliente salvo com sucesso.']);
    }

    public static function delete($request): string
    {
        $post = $request->getPostVars();
        if ($err = CrudHelper::requireCsrf($post)) {
            return CrudHelper::jsonError($err);
        }
        EntityCliente::delete((int)($post['id'] ?? 0));
        return CrudHelper::jsonOk(['message' => 'Cliente desativado.']);
    }

    /** @return array{nome:string,email:string} */
    private static function portalDefaults(EntityCliente $cliente): array
    {
        $nome = trim((string)($cliente->responsavel ?: $cliente->nome_fantasia));

        return [
            'nome' => $nome,
            'email' => trim(strtolower((string)$cliente->email)),
        ];
    }

    public static function getPortalAcesso($request): string
    {
        $post = $request->getPostVars();
        if ($err = CrudHelper::requireCsrf($post)) {
            return CrudHelper::jsonError($err);
        }

        $clienteId = (int)($post['cliente_id'] ?? 0);
        $cliente = $clienteId > 0 ? EntityCliente::getById($clienteId) : null;
        if (!$cliente) {
            return CrudHelper::jsonError('Cliente não encontrado.');
        }

        try {
            $defaults = self::portalDefaults($cliente);
            $usuario = ClienteUsuario::getByClienteId($clienteId);

            return CrudHelper::jsonOk([
                'defaults' => $defaults,
                'usuario' => $usuario ? [
                    'id' => $usuario->id,
                    'nome' => $usuario->nome,
                    'email' => $usuario->email,
                    'ativo' => (bool)$usuario->ativo,
                    'ultimo_login' => $usuario->ultimo_login,
                ] : null,
                'portal_url' => URL.'/gerador/login',
            ]);
        } catch (\Throwable) {
            return CrudHelper::jsonError(
                'Tabela cliente_usuarios ausente. Execute: php database/scripts/apply_migration_025.php'
            );
        }
    }

    public static function savePortalUsuario($request): string
    {
        $post = $request->getPostVars();
        if ($err = CrudHelper::requireCsrf($post)) {
            return CrudHelper::jsonError($err);
        }

        $clienteId = (int)($post['cliente_id'] ?? 0);
        $cliente = $clienteId > 0 ? EntityCliente::getById($clienteId) : null;
        if (!$cliente) {
            return CrudHelper::jsonError('Cliente não encontrado.');
        }

        $defaults = self::portalDefaults($cliente);
        $nome = trim((string)($post['nome'] ?? $defaults['nome']));
        $email = trim(strtolower((string)($post['email'] ?? $defaults['email'])));
        $ativo = (int)($post['ativo'] ?? 1) ? 1 : 0;
        $senha = (string)($post['senha'] ?? '');

        if ($nome === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return CrudHelper::jsonError('Informe nome e e-mail válidos (ou cadastre e-mail no cliente).');
        }

        $existenteCliente = ClienteUsuario::getByClienteId($clienteId);
        $id = $existenteCliente?->id ?? (int)($post['id'] ?? 0);

        $emailEmUso = ClienteUsuario::getByEmail($email);
        if ($emailEmUso && $emailEmUso->cliente_id !== $clienteId) {
            return CrudHelper::jsonError('Este e-mail já está em uso no portal de outro cliente.');
        }

        if ($id > 0) {
            $usuario = ClienteUsuario::getById($id);
            if (!$usuario || $usuario->cliente_id !== $clienteId) {
                return CrudHelper::jsonError('Acesso do portal não encontrado.');
            }
            $data = ['nome' => $nome, 'email' => $email, 'ativo' => $ativo];
            if ($senha !== '') {
                $data['senha_hash'] = password_hash($senha, PASSWORD_DEFAULT);
            }
            ClienteUsuario::update($id, $data);
        } else {
            ClienteUsuario::insert([
                'cliente_id' => $clienteId,
                'nome' => $nome,
                'email' => $email,
                'senha_hash' => password_hash($senha !== '' ? $senha : self::SENHA_PORTAL_PADRAO, PASSWORD_DEFAULT),
                'ativo' => $ativo,
            ]);
        }

        return CrudHelper::jsonOk(['message' => 'Acesso ao portal salvo. Login em /gerador']);
    }

    public static function resetSenhaPortal($request): string
    {
        $post = $request->getPostVars();
        if ($err = CrudHelper::requireCsrf($post)) {
            return CrudHelper::jsonError($err);
        }

        $clienteId = (int)($post['cliente_id'] ?? 0);
        $usuario = $clienteId > 0 ? ClienteUsuario::getByClienteId($clienteId) : null;
        if (!$usuario) {
            return CrudHelper::jsonError('Este cliente ainda não tem acesso ao portal.');
        }

        ClienteUsuario::update($usuario->id, [
            'senha_hash' => password_hash(self::SENHA_PORTAL_PADRAO, PASSWORD_DEFAULT),
        ]);

        return CrudHelper::jsonOk([
            'message' => 'Senha redefinida para '.self::SENHA_PORTAL_PADRAO.'.',
        ]);
    }
}
