<?php

namespace App\Model\Entity;

use App\Model\Db\Database;
use PDO;

class ColetaSnapshot
{
    public int $coleta_id = 0;
    public string $gerador_nome_fantasia = '';
    public string $gerador_razao_social = '';
    public string $gerador_cnpj = '';
    public string $gerador_endereco = '';
    public string $gerador_responsavel = '';
    public string $gerador_plano = '';
    public string $transportador_nome = '';
    public string $transportador_cnpj = '';
    public string $motorista_nome = '';
    public string $veiculo_descricao = '';
    public string $veiculo_placa = '';
    public string $destinador_nome = '';
    public string $destinador_cnpj = '';
    public string $destinador_endereco = '';
    public string $destinador_telefone = '';
    public string $destinador_responsavel = '';
    public string $observacao_destinador = '';

    public static function getByColetaId(int $coletaId): ?self
    {
        $db = new Database();
        $stmt = $db->execute('SELECT * FROM coleta_snapshot WHERE coleta_id = ?', [$coletaId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? self::fromArray($row) : null;
    }

    public static function insert(array $data): void
    {
        (new Database('coleta_snapshot'))->insert($data);
    }

    public static function update(int $coletaId, array $data): void
    {
        if (empty($data)) {
            return;
        }
        $db = new Database();
        $fields = array_keys($data);
        $db->execute(
            'UPDATE coleta_snapshot SET '.implode('=?, ', $fields).'=? WHERE coleta_id = ?',
            [...array_values($data), $coletaId]
        );
    }

    private static function fromArray(array $row): self
    {
        $s = new self();
        $s->coleta_id = (int)$row['coleta_id'];
        foreach ($row as $k => $v) {
            if (property_exists($s, $k) && $k !== 'coleta_id') {
                $s->$k = (string)($v ?? '');
            }
        }
        return $s;
    }
}
