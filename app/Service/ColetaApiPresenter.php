<?php

namespace App\Service;

use App\Common\ColetaDefaults;
use App\Common\Helpers\ColetaMtrHelper;
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
            'quantidade_label' => self::quantityLabel((float)$i->quantidade, (string)$i->unidade),
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
        $itens = array_map(fn ($i) => self::item($i), $detalhe['itens']);
        $totalKg = 0.0;
        foreach ($detalhe['itens'] as $item) {
            if (mb_strtolower(trim((string)$item->unidade), 'UTF-8') === 'kg') {
                $totalKg += (float)$item->quantidade;
            }
        }
        $numeroRelatorio = ColetaMtrHelper::numeroRelatorioExibicao($c);
        $numeroMtr = ColetaMtrHelper::numeroExibicao($c);
        $statusLabel = match ($c->status) {
            'rascunho' => 'Rascunho',
            'finalizada' => 'Finalizada',
            'cancelada' => 'Cancelada',
            default => ucfirst($c->status),
        };
        $recebimentoLabel = $c->situacao_recebimento === 'recebido'
            ? 'Recebido'.($c->data_recebimento ? ' em '.self::dateBr($c->data_recebimento) : '')
            : 'Pendente';
        $veiculoLabel = trim(implode(' — ', array_filter([
            trim((string)($s?->veiculo_descricao ?? '')),
            trim((string)($s?->veiculo_placa ?? '')),
        ])));
        $titulo = $numeroRelatorio !== null ? 'Relatório #'.$numeroRelatorio : 'Coleta #'.$c->id;

        return [
            'coleta' => [
                'id' => $c->id,
                'numero_mtr' => $c->numero_mtr,
                'numero_relatorio' => $c->numero_relatorio,
                'cliente_id' => $c->cliente_id,
                'cliente_nome' => $c->cliente_nome,
                'coletor_id' => $c->coletor_id,
                'coletor_nome' => $c->coletor_nome,
                'veiculo_id' => $c->veiculo_id,
                'status' => $c->status,
                'doc_referencia' => $c->doc_referencia,
                'data_coleta' => $c->data_coleta,
                'hora' => $c->hora,
                'relatorio' => $c->relatorio,
                'tratamento' => $c->tratamento,
                'situacao_recebimento' => $c->situacao_recebimento,
                'data_recebimento' => $c->data_recebimento,
                'sinir_status' => $c->sinir_status,
                'sinir_man_numero' => $c->sinir_man_numero,
            ],
            'snapshot' => $s ? [
                'gerador_nome_fantasia' => $s->gerador_nome_fantasia,
                'gerador_razao_social' => $s->gerador_razao_social,
                'gerador_cnpj' => $s->gerador_cnpj,
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
            'itens' => $itens,
            'evidencias' => array_map(fn ($e) => self::evidencia($e, $c->id), $detalhe['evidencias']),
            'tratamentos' => ColetaDefaults::tratamentos(),
            'resumo' => [
                'titulo' => implode(' • ', array_filter([$titulo, $c->cliente_nome, $statusLabel])),
                'cliente' => $c->cliente_nome,
                'status_label' => $statusLabel,
                'numero_relatorio_label' => $numeroRelatorio !== null ? '#'.$numeroRelatorio : '—',
                'numero_mtr_label' => $numeroMtr !== null ? '#'.$numeroMtr : ColetaMtrHelper::rotuloSemMtr($c),
                'data_hora_label' => trim(implode(' ', array_filter([
                    self::dateBr($c->data_coleta),
                    $c->hora ? substr((string)$c->hora, 0, 5) : '',
                ]))),
                'peso_total_kg' => $totalKg,
                'peso_total_label' => self::quantityLabel($totalKg, 'kg'),
                'transportador_label' => trim((string)($s?->transportador_nome ?? '')),
                'veiculo_label' => $veiculoLabel !== '' ? $veiculoLabel : 'Não informado',
                'motorista_label' => trim((string)($s?->motorista_nome ?? '')) ?: 'Não informado',
                'destinador_label' => trim((string)($s?->destinador_nome ?? '')) ?: 'Não informado',
                'recebimento_label' => $recebimentoLabel,
                'tratamento_label' => trim((string)($c->tratamento ?? '')) ?: 'Não informado',
                'relatorio_label' => trim((string)($c->relatorio ?? '')) ?: 'Sem observações.',
                'itens_count' => count($itens),
                'evidencias_count' => count($detalhe['evidencias']),
                'pode_imprimir' => ColetaMtrHelper::podeImprimirRelatorio($c),
                'pode_gerar_mtr' => ColetaMtrHelper::podeGerarMtr($c),
            ],
        ];
    }

    private static function dateBr(?string $date): string
    {
        if ($date === null || trim($date) === '') {
            return '';
        }
        $timestamp = strtotime($date);

        return $timestamp !== false ? date('d/m/Y', $timestamp) : $date;
    }

    private static function quantityLabel(float $quantity, string $unit): string
    {
        return number_format($quantity, 3, ',', '.').' '.mb_strtoupper($unit, 'UTF-8');
    }
}
