<?php

namespace App\Controller\Api;

use App\Common\Helpers\ApiHelper;
use App\Http\ApiContext;
use App\Model\Entity\Veiculo as EntityVeiculo;
use App\Service\ColetaApiPresenter;
use App\Service\VeiculoFrotaService;

class Veiculos extends BaseApi
{
    public static function index($request): \App\Http\Response
    {
        $usuarioId = ApiContext::userId();
        $items = array_map(
            fn ($v) => ColetaApiPresenter::veiculoFrota($v, $usuarioId),
            EntityVeiculo::listPainel('v.ativo = 1', [], '300')
        );
        usort($items, static function (array $a, array $b): int {
            $rank = static function (array $item): int {
                if (!empty($item['meu_uso'])) {
                    return 0;
                }

                return match ($item['situacao'] ?? '') {
                    'disponivel' => 1,
                    'em_uso' => 2,
                    'manutencao' => 3,
                    default => 9,
                };
            };
            $cmp = $rank($a) <=> $rank($b);

            return $cmp !== 0 ? $cmp : strcmp((string)$a['placa'], (string)$b['placa']);
        });
        $meuId = 0;
        foreach ($items as $item) {
            if (!empty($item['meu_uso'])) {
                $meuId = (int)$item['id'];
                break;
            }
        }

        return ApiHelper::ok([
            'meu_veiculo_id' => $meuId,
            'items' => $items,
        ]);
    }

    public static function show($request, int $id): \App\Http\Response
    {
        $item = self::item($id);
        if ($item === null) {
            return ApiHelper::fail('not_found', 'Veículo não encontrado.', 404);
        }
        $historico = VeiculoFrotaService::historico($id);

        return ApiHelper::ok([
            'veiculo' => $item,
            'quilometragens' => array_map(static function (array $row): array {
                return [
                    'km' => (int)$row['km'],
                    'km_label' => number_format((int)$row['km'], 0, ',', '.').' km',
                    'quando_label' => ColetaApiPresenter::dataHoraCurta((string)$row['registrado_em']),
                    'observacao' => (string)($row['observacao'] ?? ''),
                    'quem' => (string)($row['usuario_nome'] ?? ''),
                ];
            }, array_slice($historico['kms'], 0, 15)),
            'abastecimentos' => array_map(static function (array $row): array {
                $valor = $row['valor'];

                return [
                    'quando_label' => ColetaApiPresenter::dataHoraCurta((string)$row['abastecido_em']),
                    'litros' => (float)$row['litros'],
                    'litros_label' => number_format((float)$row['litros'], 3, ',', '.').' L',
                    'valor_label' => $valor !== null && $valor !== ''
                        ? 'R$ '.number_format((float)$valor, 2, ',', '.')
                        : '',
                    'km_label' => number_format((int)$row['km'], 0, ',', '.').' km',
                    'posto' => (string)($row['posto'] ?? ''),
                    'quem' => (string)($row['usuario_nome'] ?? ''),
                ];
            }, array_slice($historico['abastecimentos'], 0, 15)),
            'manutencao_aberta' => $historico['manutencao_aberta'] === null ? null : [
                'tipo_label' => ($historico['manutencao_aberta']['tipo'] ?? '') === 'corretiva' ? 'Corretiva' : 'Preventiva',
                'descricao' => (string)($historico['manutencao_aberta']['descricao'] ?? ''),
                'aberta_label' => ColetaApiPresenter::dataHoraCurta((string)($historico['manutencao_aberta']['aberta_em'] ?? '')),
            ],
        ]);
    }

    public static function usar($request, int $id): \App\Http\Response
    {
        $body = $request->getPostVars();

        return self::agir(function () use ($id, $body): void {
            VeiculoFrotaService::iniciarUso($id, ApiContext::userId(), (int)($body['km'] ?? 0), 'app');
        }, $id);
    }

    public static function km($request, int $id): \App\Http\Response
    {
        $body = $request->getPostVars();

        return self::agir(function () use ($id, $body): void {
            VeiculoFrotaService::registrarKm(
                $id,
                ApiContext::userId(),
                (int)($body['km'] ?? 0),
                'app',
                (string)($body['observacao'] ?? ''),
                true
            );
        }, $id);
    }

    public static function abastecer($request, int $id): \App\Http\Response
    {
        $body = $request->getPostVars();
        if (trim((string)($body['abastecido_em'] ?? '')) === '') {
            $body['abastecido_em'] = date('Y-m-d H:i:s');
        }

        return self::agir(function () use ($id, $body): void {
            VeiculoFrotaService::abastecer($id, ApiContext::userId(), $body, 'app', true);
        }, $id);
    }

    public static function encerrar($request, int $id): \App\Http\Response
    {
        $body = $request->getPostVars();

        return self::agir(function () use ($id, $body): void {
            VeiculoFrotaService::encerrarUso(
                $id,
                ApiContext::userId(),
                (int)($body['km'] ?? 0),
                'coletor',
                true
            );
        }, $id);
    }

    /** @param callable(): void $acao */
    private static function agir(callable $acao, int $id): \App\Http\Response
    {
        try {
            $acao();
        } catch (\InvalidArgumentException $e) {
            return self::handleInvalidArgument($e);
        } catch (\Throwable $e) {
            return self::handleThrowable($e, 'ApiVeiculos');
        }
        $item = self::item($id);
        if ($item === null) {
            return ApiHelper::fail('not_found', 'Veículo não encontrado.', 404);
        }

        return ApiHelper::ok(['veiculo' => $item]);
    }

    /** @return array<string, mixed>|null */
    private static function item(int $id): ?array
    {
        $rows = EntityVeiculo::listPainel('v.id = ? AND v.ativo = 1', [$id], '1');
        if ($rows === []) {
            return null;
        }

        return ColetaApiPresenter::veiculoFrota($rows[0], ApiContext::userId());
    }
}
