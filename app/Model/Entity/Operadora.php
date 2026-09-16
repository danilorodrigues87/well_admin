<?php

namespace App\Model\Entity;

use App\Model\Db\Database;
use PDO;

class Operadora
{
    public int $id = 0;
    public string $nome = '';
    public string $razao_social = '';
    public string $cnpj = '';
    public string $nome_fantasia = '';
    public string $nome_curto = '';
    public int $ativo = 1;
    public ?string $modulos_liberados = null;
    public string $transportador_nome = '';
    public string $transportador_cnpj = '';
    public string $destinador_nome = '';
    public string $destinador_cnpj = '';
    public string $destinador_endereco = '';
    public string $destinador_telefone = '';
    public string $destinador_responsavel = '';
    public string $sinir_integration_token = '';
    public string $sinir_unidade = '';
    public string $sinir_unidade_destinador = '';
    public string $sinir_cnpj = '';

    public static function getById(int $id): ?self
    {
        $db = new Database();
        $stmt = $db->execute('SELECT * FROM operadoras WHERE id = ? LIMIT 1', [$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? self::fromArray($row) : null;
    }

    /** @return string[]|null NULL = todos os módulos */
    public function getModulosLiberadosSlugs(): ?array
    {
        if ($this->modulos_liberados === null || trim($this->modulos_liberados) === '') {
            return null;
        }
        $decoded = json_decode($this->modulos_liberados, true);
        if (!is_array($decoded)) {
            return null;
        }

        return array_values(array_filter(array_map('strval', $decoded)));
    }

    /** @return array<string, mixed> */
    public function toSessionSnapshot(): array
    {
        return [
            'id' => $this->id,
            'nome' => $this->nome_fantasia !== '' ? $this->nome_fantasia : $this->nome,
            'nome_curto' => $this->nome_curto,
            'cnpj' => $this->cnpj,
        ];
    }

    public static function update(int $id, array $data): void
    {
        if ($id <= 0 || $data === []) {
            return;
        }
        $db = new Database();
        $fields = array_keys($data);
        $sql = 'UPDATE operadoras SET '.implode('=?, ', $fields).'=? WHERE id = ?';
        $db->execute($sql, [...array_values($data), $id]);
    }

    /** @param array<string, mixed> $row */
    private static function fromArray(array $row): self
    {
        $o = new self();
        $o->id = (int)$row['id'];
        $o->nome = (string)($row['nome'] ?? '');
        $o->razao_social = (string)($row['razao_social'] ?? '');
        $o->cnpj = (string)($row['cnpj'] ?? '');
        $o->nome_fantasia = (string)($row['nome_fantasia'] ?? '');
        $o->nome_curto = (string)($row['nome_curto'] ?? '');
        $o->ativo = (int)($row['ativo'] ?? 1);
        $o->modulos_liberados = isset($row['modulos_liberados']) ? (string)$row['modulos_liberados'] : null;
        $o->transportador_nome = (string)($row['transportador_nome'] ?? '');
        $o->transportador_cnpj = (string)($row['transportador_cnpj'] ?? '');
        $o->destinador_nome = (string)($row['destinador_nome'] ?? '');
        $o->destinador_cnpj = (string)($row['destinador_cnpj'] ?? '');
        $o->destinador_endereco = (string)($row['destinador_endereco'] ?? '');
        $o->destinador_telefone = (string)($row['destinador_telefone'] ?? '');
        $o->destinador_responsavel = (string)($row['destinador_responsavel'] ?? '');
        $o->sinir_integration_token = (string)($row['sinir_integration_token'] ?? '');
        $o->sinir_unidade = (string)($row['sinir_unidade'] ?? '');
        $o->sinir_unidade_destinador = (string)($row['sinir_unidade_destinador'] ?? '');
        $o->sinir_cnpj = (string)($row['sinir_cnpj'] ?? '');

        return $o;
    }
}
