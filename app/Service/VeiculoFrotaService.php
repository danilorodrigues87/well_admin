<?php

namespace App\Service;

use App\Common\OperadoraScope;
use App\Model\Db\Database;
use App\Model\Entity\Veiculo as EntityVeiculo;
use PDO;

class VeiculoFrotaService
{
    /** @var list<string> */
    public const COMBUSTIVEIS = ['diesel', 'gasolina', 'etanol', 'flex', 'gnv'];

    public static function situacaoLabel(string $situacao): string
    {
        return match ($situacao) {
            'em_uso' => 'Em uso',
            'manutencao' => 'Manutenção',
            default => 'Disponível',
        };
    }

    public static function combustivelLabel(string $combustivel): string
    {
        return match ($combustivel) {
            'gasolina' => 'Gasolina',
            'etanol' => 'Etanol',
            'flex' => 'Flex',
            'gnv' => 'GNV',
            default => 'Diesel',
        };
    }

    /** @param array<string, mixed> $dados */
    public static function salvarCadastro(int $id, array $dados): void
    {
        $placa = strtoupper(trim((string)($dados['placa'] ?? '')));
        $marca = trim((string)($dados['marca'] ?? ''));
        $modelo = trim((string)($dados['modelo'] ?? ''));
        $combustivel = (string)($dados['combustivel'] ?? 'diesel');
        $ativo = (int)($dados['ativo'] ?? 1) === 0 ? 0 : 1;
        if ($placa === '' || $marca === '' || $modelo === '') {
            throw new \InvalidArgumentException('Placa, marca e modelo são obrigatórios.');
        }
        if (!in_array($combustivel, self::COMBUSTIVEIS, true)) {
            throw new \InvalidArgumentException('Combustível inválido.');
        }
        if ($id > 0 && $ativo === 0) {
            self::assertPodeDesativar($id);
        }

        $db = new Database();
        $dup = $db->execute(
            'SELECT id FROM veiculos WHERE placa = ? AND id <> ? LIMIT 1',
            [$placa, max(0, $id)]
        )->fetch(PDO::FETCH_ASSOC);
        if ($dup) {
            throw new \InvalidArgumentException('Já existe um veículo com esta placa.');
        }

        $data = [
            'placa' => $placa,
            'marca' => $marca,
            'modelo' => $modelo,
            'ano' => trim((string)($dados['ano'] ?? '')),
            'cor' => trim((string)($dados['cor'] ?? '')),
            'combustivel' => $combustivel,
            'ativo' => $ativo,
        ];
        if ($id > 0) {
            EntityVeiculo::update($id, $data);
            return;
        }
        EntityVeiculo::insert($data);
    }

    public static function desativar(int $id): void
    {
        self::assertPodeDesativar($id);
        EntityVeiculo::update($id, ['ativo' => 0]);
    }

    public static function iniciarUso(int $veiculoId, int $coletorId, int $kmInicio, string $origem = 'painel'): void
    {
        $origem = self::origem($origem);
        self::transacao(function (Database $db) use ($veiculoId, $coletorId, $kmInicio, $origem): void {
            $veiculo = self::lockVeiculo($db, $veiculoId);
            self::assertUsuario($db, $coletorId);
            if ((int)$veiculo['ativo'] !== 1) {
                throw new \InvalidArgumentException('Veículo desativado.');
            }
            if ((string)$veiculo['situacao'] !== 'disponivel') {
                throw new \InvalidArgumentException('Este veículo não está disponível.');
            }
            if (self::usoAbertoVeiculo($db, $veiculoId) !== null) {
                throw new \InvalidArgumentException('Este veículo já está em uso.');
            }
            if (self::usoAbertoUsuario($db, $coletorId) !== null) {
                throw new \InvalidArgumentException('Este coletor já está com outro veículo em uso.');
            }
            $kmInicio = self::km($kmInicio);
            $atual = (int)$veiculo['km_atual'];
            self::validarKm($atual, $kmInicio, 'app', '', false);
            $op = OperadoraScope::getOperadoraId();
            $db->execute(
                'INSERT INTO veiculo_usos (operadora_id, veiculo_id, usuario_id, km_inicio) VALUES (?,?,?,?)',
                [$op, $veiculoId, $coletorId, $kmInicio]
            );
            $usoId = (int)$db->lastInsertId();
            if ($kmInicio !== $atual) {
                self::gravarKm($db, $veiculoId, $coletorId, $usoId, $kmInicio, $origem, '');
            }
            $db->execute(
                'UPDATE veiculos SET situacao = ?, km_atual = ?, em_uso_usuario_id = ?, em_uso_desde = NOW()
                 WHERE id = ? AND operadora_id = ?',
                ['em_uso', $kmInicio, $coletorId, $veiculoId, $op]
            );
        });
    }

    public static function registrarKm(
        int $veiculoId,
        int $usuarioId,
        int $km,
        string $origem,
        string $observacao = '',
        bool $somenteTitular = false
    ): void {
        $origem = self::origem($origem);
        self::transacao(function (Database $db) use ($veiculoId, $usuarioId, $km, $origem, $observacao, $somenteTitular): void {
            $veiculo = self::lockVeiculo($db, $veiculoId);
            self::assertUsuario($db, $usuarioId);
            $uso = self::exigirUsoSeTitular($db, $veiculo, $usuarioId, $somenteTitular);
            $km = self::km($km);
            self::validarKm((int)$veiculo['km_atual'], $km, $origem, $observacao, $origem === 'painel');
            self::gravarKm($db, $veiculoId, $usuarioId, (int)($uso['id'] ?? 0), $km, $origem, $observacao);
            self::atualizarKmAtual($db, $veiculoId, $km);
        });
    }

    /** @param array<string, mixed> $dados */
    public static function abastecer(
        int $veiculoId,
        int $usuarioId,
        array $dados,
        string $origem,
        bool $somenteTitular = false
    ): void {
        $origem = self::origem($origem);
        self::transacao(function (Database $db) use ($veiculoId, $usuarioId, $dados, $origem, $somenteTitular): void {
            $veiculo = self::lockVeiculo($db, $veiculoId);
            self::assertUsuario($db, $usuarioId);
            $uso = self::exigirUsoSeTitular($db, $veiculo, $usuarioId, $somenteTitular);
            $km = self::km($dados['km'] ?? 0);
            $observacao = trim((string)($dados['observacao'] ?? ''));
            self::validarKm((int)$veiculo['km_atual'], $km, $origem, $observacao, $origem === 'painel');
            $litros = self::decimalPositivo((string)($dados['litros'] ?? ''), 3, 'Informe os litros abastecidos.');
            $valor = self::decimalOpcional((string)($dados['valor'] ?? ''), 2);
            $posto = self::texto((string)($dados['posto'] ?? ''), 120);
            $quando = self::dataHora((string)($dados['abastecido_em'] ?? ''));
            $op = OperadoraScope::getOperadoraId();
            $db->execute(
                'INSERT INTO veiculo_abastecimentos
                    (operadora_id, veiculo_id, usuario_id, uso_id, abastecido_em, litros, valor, km, posto, observacao, origem)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?)',
                [
                    $op,
                    $veiculoId,
                    $usuarioId,
                    ($uso['id'] ?? null) ?: null,
                    $quando,
                    $litros,
                    $valor,
                    $km,
                    $posto !== '' ? $posto : null,
                    $observacao !== '' ? self::texto($observacao, 500) : null,
                    $origem,
                ]
            );
            if ($km !== (int)$veiculo['km_atual']) {
                self::gravarKm($db, $veiculoId, $usuarioId, (int)($uso['id'] ?? 0), $km, $origem, $observacao !== '' ? $observacao : 'Abastecimento');
                self::atualizarKmAtual($db, $veiculoId, $km);
            }
        });
    }

    public static function encerrarUso(
        int $veiculoId,
        int $usuarioId,
        int $kmFim,
        string $encerradoPor,
        bool $somenteTitular = false
    ): void {
        if (!in_array($encerradoPor, ['coletor', 'painel'], true)) {
            throw new \InvalidArgumentException('Encerramento inválido.');
        }
        self::transacao(function (Database $db) use ($veiculoId, $usuarioId, $kmFim, $encerradoPor, $somenteTitular): void {
            $veiculo = self::lockVeiculo($db, $veiculoId);
            self::assertUsuario($db, $usuarioId);
            $uso = self::usoAbertoVeiculo($db, $veiculoId);
            if ($uso === null) {
                throw new \InvalidArgumentException('Este veículo não está em uso.');
            }
            if ($somenteTitular && (int)$uso['usuario_id'] !== $usuarioId) {
                throw new \InvalidArgumentException('Só quem está com o veículo pode encerrar o uso.');
            }
            $kmFim = self::km($kmFim);
            self::validarKm((int)$veiculo['km_atual'], $kmFim, 'app', '', false);
            $op = OperadoraScope::getOperadoraId();
            if ($kmFim !== (int)$veiculo['km_atual']) {
                self::gravarKm($db, $veiculoId, $usuarioId, (int)$uso['id'], $kmFim, $encerradoPor === 'painel' ? 'painel' : 'app', '');
            }
            $db->execute(
                'UPDATE veiculo_usos SET km_fim = ?, encerrado_em = NOW(), encerrado_por = ? WHERE id = ? AND operadora_id = ?',
                [$kmFim, $encerradoPor, (int)$uso['id'], $op]
            );
            $db->execute(
                'UPDATE veiculos SET situacao = ?, km_atual = ?, em_uso_usuario_id = NULL, em_uso_desde = NULL
                 WHERE id = ? AND operadora_id = ?',
                ['disponivel', $kmFim, $veiculoId, $op]
            );
        });
    }

    public static function abrirManutencao(int $veiculoId, int $usuarioId, string $tipo, string $descricao): void
    {
        if (!in_array($tipo, ['preventiva', 'corretiva'], true)) {
            throw new \InvalidArgumentException('Tipo de manutenção inválido.');
        }
        $descricao = self::texto($descricao, 500);
        if ($descricao === '') {
            throw new \InvalidArgumentException('Descreva a manutenção.');
        }
        self::transacao(function (Database $db) use ($veiculoId, $usuarioId, $tipo, $descricao): void {
            $veiculo = self::lockVeiculo($db, $veiculoId);
            self::assertUsuario($db, $usuarioId);
            if (self::manutencaoAberta($db, $veiculoId) !== null) {
                throw new \InvalidArgumentException('Já existe uma manutenção aberta para este veículo.');
            }
            $op = OperadoraScope::getOperadoraId();
            $uso = self::usoAbertoVeiculo($db, $veiculoId);
            if ($uso !== null) {
                $db->execute(
                    'UPDATE veiculo_usos
                     SET km_fim = ?, encerrado_em = NOW(), encerrado_por = ?, observacao = ?
                     WHERE id = ? AND operadora_id = ?',
                    [(int)$veiculo['km_atual'], 'painel', 'Encerrado para manutenção', (int)$uso['id'], $op]
                );
            }
            $db->execute(
                'UPDATE veiculos SET situacao = ?, em_uso_usuario_id = NULL, em_uso_desde = NULL
                 WHERE id = ? AND operadora_id = ?',
                ['manutencao', $veiculoId, $op]
            );
            $db->execute(
                'INSERT INTO veiculo_manutencoes (operadora_id, veiculo_id, usuario_id, tipo, descricao, km)
                 VALUES (?,?,?,?,?,?)',
                [$op, $veiculoId, $usuarioId, $tipo, $descricao, (int)$veiculo['km_atual']]
            );
        });
    }

    public static function concluirManutencao(int $veiculoId, int $manutencaoId, ?string $custo, string $observacao): void
    {
        self::fecharManutencao($veiculoId, $manutencaoId, 'concluida', $custo, $observacao);
    }

    public static function cancelarManutencao(int $veiculoId, int $manutencaoId, string $observacao): void
    {
        self::fecharManutencao($veiculoId, $manutencaoId, 'cancelada', null, $observacao);
    }

    /**
     * @return array{
     *   usos: list<array<string, mixed>>,
     *   kms: list<array<string, mixed>>,
     *   abastecimentos: list<array<string, mixed>>,
     *   manutencoes: list<array<string, mixed>>,
     *   manutencao_aberta: array<string, mixed>|null
     * }
     */
    public static function historico(int $veiculoId): array
    {
        $op = OperadoraScope::getOperadoraId();
        $db = new Database();

        return [
            'usos' => self::linhas($db,
                'SELECT vu.*, u.nome AS usuario_nome
                 FROM veiculo_usos vu
                 INNER JOIN usuarios u ON u.id = vu.usuario_id
                 WHERE vu.operadora_id = ? AND vu.veiculo_id = ?
                 ORDER BY vu.id DESC LIMIT 40',
                [$op, $veiculoId]
            ),
            'kms' => self::linhas($db,
                'SELECT vk.*, u.nome AS usuario_nome
                 FROM veiculo_km vk
                 INNER JOIN usuarios u ON u.id = vk.usuario_id
                 WHERE vk.operadora_id = ? AND vk.veiculo_id = ?
                 ORDER BY vk.id DESC LIMIT 40',
                [$op, $veiculoId]
            ),
            'abastecimentos' => self::linhas($db,
                'SELECT va.*, u.nome AS usuario_nome
                 FROM veiculo_abastecimentos va
                 INNER JOIN usuarios u ON u.id = va.usuario_id
                 WHERE va.operadora_id = ? AND va.veiculo_id = ?
                 ORDER BY va.id DESC LIMIT 40',
                [$op, $veiculoId]
            ),
            'manutencoes' => self::linhas($db,
                'SELECT vm.*, u.nome AS usuario_nome
                 FROM veiculo_manutencoes vm
                 INNER JOIN usuarios u ON u.id = vm.usuario_id
                 WHERE vm.operadora_id = ? AND vm.veiculo_id = ?
                 ORDER BY vm.id DESC LIMIT 40',
                [$op, $veiculoId]
            ),
            'manutencao_aberta' => self::manutencaoAberta($db, $veiculoId),
        ];
    }

    private static function fecharManutencao(int $veiculoId, int $manutencaoId, string $status, ?string $custo, string $observacao): void
    {
        self::transacao(function (Database $db) use ($veiculoId, $manutencaoId, $status, $custo, $observacao): void {
            self::lockVeiculo($db, $veiculoId);
            $op = OperadoraScope::getOperadoraId();
            $row = $db->execute(
                'SELECT * FROM veiculo_manutencoes
                 WHERE id = ? AND veiculo_id = ? AND operadora_id = ? AND status = ? LIMIT 1 FOR UPDATE',
                [$manutencaoId, $veiculoId, $op, 'aberta']
            )->fetch(PDO::FETCH_ASSOC);
            if (!$row) {
                throw new \InvalidArgumentException('Manutenção aberta não encontrada.');
            }
            $custoSql = $custo === null || trim($custo) === '' ? null : self::decimalOpcional($custo, 2);
            $obs = self::texto($observacao, 500);
            $db->execute(
                'UPDATE veiculo_manutencoes
                 SET status = ?, concluida_em = NOW(), custo = ?, observacao = ?
                 WHERE id = ? AND operadora_id = ?',
                [$status, $custoSql, $obs !== '' ? $obs : null, $manutencaoId, $op]
            );
            $db->execute(
                'UPDATE veiculos SET situacao = ? WHERE id = ? AND operadora_id = ? AND situacao = ?',
                ['disponivel', $veiculoId, $op, 'manutencao']
            );
        });
    }

    private static function assertPodeDesativar(int $id): void
    {
        $v = EntityVeiculo::getById($id);
        if (!$v) {
            throw new \InvalidArgumentException('Veículo não encontrado.');
        }
        if ($v->situacao !== 'disponivel') {
            throw new \InvalidArgumentException('Encerre o uso ou a manutenção antes de desativar o veículo.');
        }
    }

    /** @param callable(Database): void $fn */
    private static function transacao(callable $fn): void
    {
        $db = new Database();
        $db->beginTransaction();
        try {
            $fn($db);
            $db->commit();
        } catch (\Throwable $e) {
            $db->rollBack();
            throw $e;
        }
    }

    /** @return array<string, mixed> */
    private static function lockVeiculo(Database $db, int $id): array
    {
        $row = $db->execute(
            'SELECT * FROM veiculos WHERE id = ? AND operadora_id = ? FOR UPDATE',
            [$id, OperadoraScope::getOperadoraId()]
        )->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            throw new \InvalidArgumentException('Veículo não encontrado.');
        }

        return $row;
    }

    private static function assertUsuario(Database $db, int $usuarioId): void
    {
        if ($usuarioId <= 0) {
            throw new \InvalidArgumentException('Usuário inválido.');
        }
        $row = $db->execute(
            'SELECT id FROM usuarios WHERE id = ? AND operadora_id = ? LIMIT 1 FOR UPDATE',
            [$usuarioId, OperadoraScope::getOperadoraId()]
        )->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            throw new \InvalidArgumentException('Usuário não encontrado.');
        }
    }

    /** @return array<string, mixed>|null */
    private static function usoAbertoVeiculo(Database $db, int $veiculoId): ?array
    {
        $row = $db->execute(
            'SELECT * FROM veiculo_usos
             WHERE operadora_id = ? AND veiculo_id = ? AND encerrado_em IS NULL
             LIMIT 1 FOR UPDATE',
            [OperadoraScope::getOperadoraId(), $veiculoId]
        )->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /** @return array<string, mixed>|null */
    private static function usoAbertoUsuario(Database $db, int $usuarioId): ?array
    {
        $row = $db->execute(
            'SELECT id FROM veiculo_usos
             WHERE operadora_id = ? AND usuario_id = ? AND encerrado_em IS NULL
             LIMIT 1',
            [OperadoraScope::getOperadoraId(), $usuarioId]
        )->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /**
     * @param array<string, mixed> $veiculo
     * @return array<string, mixed>|null
     */
    private static function exigirUsoSeTitular(Database $db, array $veiculo, int $usuarioId, bool $somenteTitular): ?array
    {
        $uso = self::usoAbertoVeiculo($db, (int)$veiculo['id']);
        if (!$somenteTitular) {
            return $uso;
        }
        if ($uso === null || (int)$uso['usuario_id'] !== $usuarioId) {
            throw new \InvalidArgumentException('Assuma o veículo antes de registrar.');
        }

        return $uso;
    }

    /** @return array<string, mixed>|null */
    private static function manutencaoAberta(Database $db, int $veiculoId): ?array
    {
        $row = $db->execute(
            'SELECT * FROM veiculo_manutencoes
             WHERE operadora_id = ? AND veiculo_id = ? AND status = ?
             ORDER BY id DESC LIMIT 1',
            [OperadoraScope::getOperadoraId(), $veiculoId, 'aberta']
        )->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    private static function gravarKm(Database $db, int $veiculoId, int $usuarioId, int $usoId, int $km, string $origem, string $observacao): void
    {
        $obs = self::texto($observacao, 500);
        $db->execute(
            'INSERT INTO veiculo_km (operadora_id, veiculo_id, usuario_id, uso_id, km, origem, observacao)
             VALUES (?,?,?,?,?,?,?)',
            [
                OperadoraScope::getOperadoraId(),
                $veiculoId,
                $usuarioId,
                $usoId > 0 ? $usoId : null,
                $km,
                $origem,
                $obs !== '' ? $obs : null,
            ]
        );
    }

    private static function atualizarKmAtual(Database $db, int $veiculoId, int $km): void
    {
        $db->execute(
            'UPDATE veiculos SET km_atual = ? WHERE id = ? AND operadora_id = ?',
            [$km, $veiculoId, OperadoraScope::getOperadoraId()]
        );
    }

    private static function validarKm(int $atual, int $novo, string $origem, string $observacao, bool $permiteQueda): void
    {
        if ($novo >= $atual) {
            return;
        }
        if (!$permiteQueda || $origem !== 'painel') {
            throw new \InvalidArgumentException('A quilometragem não pode ser menor que a atual ('.$atual.').');
        }
        if (trim($observacao) === '') {
            throw new \InvalidArgumentException('Informe a observação para corrigir a quilometragem para baixo.');
        }
    }

    private static function km(mixed $valor): int
    {
        $km = (int)$valor;
        if ($km < 0) {
            throw new \InvalidArgumentException('Quilometragem inválida.');
        }

        return $km;
    }

    private static function origem(string $origem): string
    {
        if (!in_array($origem, ['app', 'painel'], true)) {
            throw new \InvalidArgumentException('Origem inválida.');
        }

        return $origem;
    }

    private static function decimalPositivo(string $raw, int $casas, string $erro): string
    {
        $n = self::numero($raw);
        if ($n === null || $n <= 0) {
            throw new \InvalidArgumentException($erro);
        }

        return number_format($n, $casas, '.', '');
    }

    private static function decimalOpcional(string $raw, int $casas): ?string
    {
        if (trim($raw) === '') {
            return null;
        }
        $n = self::numero($raw);
        if ($n === null || $n < 0) {
            throw new \InvalidArgumentException('Valor inválido.');
        }

        return number_format($n, $casas, '.', '');
    }

    private static function numero(string $raw): ?float
    {
        $raw = trim(str_replace(',', '.', $raw));
        if ($raw === '' || !is_numeric($raw)) {
            return null;
        }

        return (float)$raw;
    }

    private static function dataHora(string $raw): string
    {
        $raw = trim(str_replace('T', ' ', $raw));
        if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/', $raw)) {
            $raw .= ':00';
        }
        $t = strtotime($raw);
        if ($t === false) {
            throw new \InvalidArgumentException('Data do abastecimento inválida.');
        }

        return date('Y-m-d H:i:s', $t);
    }

    private static function texto(string $valor, int $max): string
    {
        $valor = trim($valor);
        if (mb_strlen($valor) <= $max) {
            return $valor;
        }

        return mb_substr($valor, 0, $max);
    }

    /** @param list<int|string|null> $params
     *  @return list<array<string, mixed>>
     */
    private static function linhas(Database $db, string $sql, array $params): array
    {
        return $db->execute($sql, $params)->fetchAll(PDO::FETCH_ASSOC);
    }
}
