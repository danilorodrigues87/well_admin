<?php

namespace App\Service;

use App\Common\ColetaDefaults;
use App\Model\Entity\Cliente as EntityCliente;
use App\Model\Entity\Coleta as EntityColeta;
use App\Model\Entity\ColetaEvidencia as EntityColetaEvidencia;
use App\Model\Entity\ColetaItem as EntityColetaItem;
use App\Model\Entity\ColetaSnapshot as EntityColetaSnapshot;
use App\Model\Entity\TipoResiduo as EntityTipoResiduo;
use App\Model\Entity\Operadora;
use App\Model\Entity\Usuario as EntityUsuario;
use App\Model\Entity\Veiculo as EntityVeiculo;

class ColetaApiPresenter
{
    /** @param array<string,mixed> $user */
    public static function usuario(array $user): array
    {
        $operadoraId = (int)($user['operadora_id'] ?? 1);
        $operadoraNome = self::operadoraNome($operadoraId);

        return [
            'id' => (int)($user['id'] ?? 0),
            'nome' => (string)($user['nome'] ?? ''),
            'email' => (string)($user['email'] ?? ''),
            'funcao_id' => (int)($user['funcao_id'] ?? 0),
            'funcao_nome' => (string)($user['funcao_nome'] ?? ''),
            'is_admin' => !empty($user['is_admin']),
            'operadora_id' => $operadoraId,
            'operadora_nome' => $operadoraNome,
            'modulos' => array_values($user['modulos'] ?? []),
            'modulos_csv' => implode(',', array_values($user['modulos'] ?? [])),
        ];
    }

    public static function coletor(EntityUsuario $u): array
    {
        return [
            'id' => $u->id,
            'nome' => $u->nome,
            'email' => $u->email,
        ];
    }

    private static function operadoraNome(int $operadoraId): string
    {
        $op = Operadora::getById($operadoraId);
        if ($op === null) {
            return '';
        }
        $nome = trim($op->nome_fantasia);
        if ($nome !== '') {
            return $nome;
        }
        $curto = trim($op->nome_curto);

        return $curto !== '' ? $curto : trim($op->nome);
    }

    public static function cliente(EntityCliente $c): array
    {
        return [
            'id' => $c->id,
            'nome_fantasia' => $c->nome_fantasia,
            'cidade' => $c->cidade,
            'uf' => $c->uf,
            'cnpj' => $c->cnpj,
            'plano_nome' => $c->plano_nome,
            'proxima_coleta' => $c->proxima_coleta,
            'prioridade' => $c->prioridade,
            'endereco' => trim(implode(', ', array_filter([
                $c->logradouro,
                $c->numero,
                $c->bairro,
                $c->cidade,
                $c->uf,
            ]))),
        ];
    }

    public static function veiculo(EntityVeiculo $v): array
    {
        return [
            'id' => $v->id,
            'marca' => $v->marca,
            'modelo' => $v->modelo,
            'placa' => $v->placa,
            'label' => trim($v->marca.' '.$v->modelo.' — '.$v->placa),
        ];
    }

    public static function tipoResiduo(EntityTipoResiduo $t): array
    {
        return [
            'id' => $t->id,
            'nome' => $t->nome,
            'classe_nome' => $t->classe_nome,
            'grupo_codigo' => $t->grupo_codigo,
            'cod_ibama' => $t->cod_ibama,
        ];
    }

    public static function item(EntityColetaItem $i): array
    {
        return [
            'id' => $i->id,
            'tipo_residuo_id' => $i->tipo_residuo_id,
            'nome' => $i->nome,
            'classe_nome' => $i->classe_nome,
            'grupo_codigo' => $i->grupo_codigo,
            'cod_ibama' => $i->cod_ibama,
            'quantidade' => $i->quantidade,
            'unidade' => $i->unidade,
        ];
    }

    public static function evidencia(EntityColetaEvidencia $e, int $coletaId): array
    {
        return [
            'id' => $e->id,
            'ordem' => $e->ordem,
            'mime' => $e->mime,
            'url' => URL.'/api/v1/coletas/'.$coletaId.'/evidencias/'.$e->ordem,
        ];
    }

    public static function coletaResumo(EntityColeta $c): array
    {
        return [
            'id' => $c->id,
            'numero_mtr' => $c->numero_mtr,
            'cliente_id' => $c->cliente_id,
            'cliente_nome' => $c->cliente_nome,
            'coletor_id' => $c->coletor_id,
            'coletor_nome' => $c->coletor_nome,
            'status' => $c->status,
            'data_coleta' => $c->data_coleta,
            'hora' => $c->hora,
        ];
    }

    /** @param array{coleta:EntityColeta,snapshot:?EntityColetaSnapshot,itens:EntityColetaItem[],evidencias:EntityColetaEvidencia[]} $detalhe */
    public static function coletaDetalhe(array $detalhe): array
    {
        $c = $detalhe['coleta'];
        $s = $detalhe['snapshot'];

        return [
            'coleta' => [
                'id' => $c->id,
                'numero_mtr' => $c->numero_mtr,
                'cliente_id' => $c->cliente_id,
                'cliente_nome' => $c->cliente_nome,
                'coletor_id' => $c->coletor_id,
                'veiculo_id' => $c->veiculo_id,
                'status' => $c->status,
                'doc_referencia' => $c->doc_referencia,
                'data_coleta' => $c->data_coleta,
                'hora' => $c->hora,
                'relatorio' => $c->relatorio,
                'tratamento' => $c->tratamento,
                'situacao_recebimento' => $c->situacao_recebimento,
                'data_recebimento' => $c->data_recebimento,
            ],
            'snapshot' => $s ? [
                'gerador_nome_fantasia' => $s->gerador_nome_fantasia,
                'gerador_endereco' => $s->gerador_endereco,
                'gerador_responsavel' => $s->gerador_responsavel,
                'gerador_plano' => $s->gerador_plano,
                'transportador_nome' => $s->transportador_nome,
                'transportador_cnpj' => $s->transportador_cnpj,
                'motorista_nome' => $s->motorista_nome,
                'veiculo_descricao' => $s->veiculo_descricao,
                'veiculo_placa' => $s->veiculo_placa,
                'destinador_nome' => $s->destinador_nome,
                'destinador_cnpj' => $s->destinador_cnpj,
                'destinador_endereco' => $s->destinador_endereco,
                'destinador_telefone' => $s->destinador_telefone,
                'destinador_responsavel' => $s->destinador_responsavel,
                'observacao_destinador' => $s->observacao_destinador,
            ] : null,
            'itens' => array_map(fn ($i) => self::item($i), $detalhe['itens']),
            'evidencias' => array_map(fn ($e) => self::evidencia($e, $c->id), $detalhe['evidencias']),
            'tratamentos' => ColetaDefaults::tratamentos(),
        ];
    }
}
