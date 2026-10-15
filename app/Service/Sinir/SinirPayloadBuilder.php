<?php

namespace App\Service\Sinir;

use App\Common\Helpers\IbamaCodigoHelper;
use App\Model\Entity\Cliente as EntityCliente;
use App\Model\Entity\Destinador as EntityDestinador;
use App\Model\Entity\Transportadora as EntityTransportadora;
use App\Service\Sinir\SinirCredentialsResolver;
use App\Model\Entity\Coleta as EntityColeta;
use App\Model\Entity\ColetaItem as EntityColetaItem;
use App\Model\Entity\ColetaSnapshot as EntityColetaSnapshot;
use App\Model\Entity\TipoResiduo as EntityTipoResiduo;

/**
 * Monta payload para POST /salvarManifestoLote (schema MMA/SIGOR nacional).
 * Body = array JSON raiz. Gerador = unidade do token de acesso (não vai no JSON).
 */
class SinirPayloadBuilder
{
    /** Texto aceito pelo SINIR quando greCodigo=4 (sem classificação ONU de transporte). */
    private const PERIGOSO_ONU_NAO_APLICA = 'NAO SE APLICA';

    /** @return array{ok:bool,payload:?array,errors:string[],gerador_unidade?:int} */
    public function buildManifestoLote(int $coletaId): array
    {
        $coleta = EntityColeta::getById($coletaId);
        if (!$coleta || $coleta->status !== 'finalizada') {
            return ['ok' => false, 'payload' => null, 'errors' => ['Coleta não encontrada ou não finalizada.']];
        }

        $snapshot = EntityColetaSnapshot::getByColetaId($coletaId);
        if (!$snapshot) {
            return ['ok' => false, 'payload' => null, 'errors' => ['Snapshot da coleta ausente.']];
        }

        $cliente = EntityCliente::getById($coleta->cliente_id);
        if (!$cliente) {
            return ['ok' => false, 'payload' => null, 'errors' => ['Cliente da coleta não encontrado.']];
        }

        $geradorUnidade = (int)($cliente->sinir_cod_unidade ?? 0);
        if ($geradorUnidade <= 0) {
            return ['ok' => false, 'payload' => null, 'errors' => ['Cliente sem código unidade SINIR (cadastro do cliente).']];
        }

        $transportadorCnpj = preg_replace('/\D/', '', (string)$snapshot->transportador_cnpj);
        $destinadorCnpj = preg_replace('/\D/', '', (string)$snapshot->destinador_cnpj);
        if ($transportadorCnpj === '' || $destinadorCnpj === '') {
            return ['ok' => false, 'payload' => null, 'errors' => ['CNPJ transportador/destinador ausente no snapshot.']];
        }

        $itens = EntityColetaItem::getByColetaId($coletaId);
        if ($itens === []) {
            return ['ok' => false, 'payload' => null, 'errors' => ['Coleta sem itens de resíduo.']];
        }

        $errors = [];
        $listaResiduos = [];
        foreach ($itens as $item) {
            $tipo = $item->tipo_residuo_id ? EntityTipoResiduo::getById($item->tipo_residuo_id) : null;
            if (!$tipo || !$this->tipoTemCodigosSinir($tipo)) {
                $errors[] = 'Item "'.($item->nome ?: ('#'.$item->id)).'" sem mapeamento SINIR completo.';
                continue;
            }

            $codIbama = self::residuoCodigoSinir(
                (string)($item->cod_ibama ?: $tipo->cod_ibama),
                (int)($tipo->cla_codigo ?? 0)
            );
            if ($codIbama === '') {
                $errors[] = 'Item "'.($item->nome ?: ('#'.$item->id)).'" sem código IBAMA.';
                continue;
            }

            $residuo = [
                'marQuantidade' => round((float)$item->quantidade, 4),
                'resCodigoIbama' => $codIbama,
                'uniCodigo' => (int)$tipo->uni_codigo,
                'traCodigo' => (int)$tipo->tra_codigo,
                'tieCodigo' => (int)$tipo->tie_codigo,
                'tiaCodigo' => (int)$tipo->tia_codigo,
                'claCodigo' => (int)$tipo->cla_codigo,
            ];
            $residuo = self::anexarCamposTransportePerigoso($residuo, $tipo, $codIbama, (int)$tipo->cla_codigo);
            if (!empty($residuo['_sinir_erro'])) {
                $errors[] = (string)$residuo['_sinir_erro'];
                unset($residuo['_sinir_erro']);
                continue;
            }
            // Densidade só para Litro / m³ (manual SIGOR).
            if (in_array(strtolower(trim((string)$item->unidade)), ['l', 'm3', 'm³'], true)) {
                $residuo['marDensidade'] = 1;
            }
            $listaResiduos[] = $residuo;
        }

        if ($errors !== []) {
            return ['ok' => false, 'payload' => null, 'errors' => $errors];
        }

        $credentials = (new SinirCredentialsResolver())->forEmissaoManifesto($coletaId);
        // Unidades transportador/destinador bastam para montar o JSON; token do gerador
        // é exigido no envio (SinirManifestoService). Aqui só bloqueia se faltar unidade.
        $unitErrors = array_values(array_filter(
            $credentials['errors'] ?? [],
            static fn (string $e): bool => !str_contains($e, 'Token API WS')
        ));
        if ($credentials['transportador_unidade'] === null || $credentials['destinador_unidade'] === null) {
            return ['ok' => false, 'payload' => null, 'errors' => $unitErrors !== [] ? $unitErrors : $credentials['errors']];
        }

        $transportadora = $coleta->transportadora_id
            ? EntityTransportadora::getById((int)$coleta->transportadora_id)
            : EntityTransportadora::getPadrao();
        $destinadorEnt = $coleta->destinador_id
            ? EntityDestinador::getById((int)$coleta->destinador_id)
            : EntityDestinador::getPadrao();
        if (!$transportadora || !$destinadorEnt) {
            return ['ok' => false, 'payload' => null, 'errors' => ['Transportadora ou destinador não cadastrado na coleta.']];
        }

        // Manual MMA/SIGOR: dataExpedição não pode ser anterior a hoje (código 005).
        $dataExpedicaoMs = self::dataExpedicaoEpochMs($coleta->data_coleta);

        $refInterna = mb_substr((string)($coleta->numero_relatorio ?: $coleta->numero_mtr ?: $coleta->id), 0, 30);
        $placa = preg_replace('/[^A-Za-z0-9]/', '', (string)($snapshot->veiculo_placa ?? '')) ?: 'AAA0A00';
        $placa = mb_substr($placa, 0, 7);
        $motorista = trim((string)$snapshot->motorista_nome);
        $responsavel = trim((string)($snapshot->gerador_responsavel ?: $snapshot->gerador_nome_fantasia));
        if ($responsavel === '') {
            $responsavel = 'Responsavel';
        }

        // Schema nacional (admin.sinir.gov.br / MMA): gerador = unidade do JWT do token.
        // Token de integração deve ser gerado no portal logado como GERADOR (erro 002 se for só transportador).
        $manifesto = [
            'tipoManifesto' => 1,
            'nomeResponsavel' => mb_substr($responsavel, 0, 300),
            'seuCodigo' => $refInterna,
            'dataExpedicao' => $dataExpedicaoMs,
            'nomeMotorista' => mb_substr($motorista !== '' ? $motorista : 'Motorista', 0, 300),
            'placaVeiculo' => $placa,
            'observacoes' => mb_substr(trim((string)($coleta->relatorio ?? '')), 0, 4000),
            'transportador' => [
                'cpfCnpj' => $transportadorCnpj,
                'unidade' => (int)$credentials['transportador_unidade'],
            ],
            'destinador' => [
                'cpfCnpj' => $destinadorCnpj,
                'unidade' => (int)$credentials['destinador_unidade'],
            ],
            // Sem armazenador temporário: omitir o campo (manual CETESB/SIGOR).
            'listaManifestoResiduos' => $listaResiduos,
        ];

        return ['ok' => true, 'payload' => [$manifesto], 'errors' => [], 'gerador_unidade' => $geradorUnidade];
    }

    /** Epoch ms em America/Sao_Paulo; nunca anterior a hoje 00:00. */
    public static function dataExpedicaoEpochMs(?string $dataColeta): int
    {
        $tz = new \DateTimeZone('America/Sao_Paulo');
        $hoje = new \DateTimeImmutable('today', $tz);
        $base = $hoje;
        if ($dataColeta) {
            $ts = strtotime($dataColeta);
            if ($ts !== false) {
                $coletaDia = (new \DateTimeImmutable('@'.$ts))->setTimezone($tz)->setTime(0, 0);
                if ($coletaDia >= $hoje) {
                    $base = $coletaDia;
                }
            }
        }

        return (int)$base->setTime(12, 0)->format('U') * 1000;
    }

    /**
     * Payload POST /receberManifestoLote — array raiz MMA/SIGOR (nacional) ou FEAM legado.
     *
     * @return array{ok:bool,payload:?array,errors:string[],format?:string}
     */
    public function buildRecebimentoLote(int $coletaId, ?string $responsavel = null, ?string $cargo = null): array
    {
        $coleta = EntityColeta::getById($coletaId);
        if (!$coleta || $coleta->status !== 'finalizada') {
            return ['ok' => false, 'payload' => null, 'errors' => ['Coleta não encontrada ou não finalizada.']];
        }
        if (($coleta->sinir_status ?? '') !== 'enviado') {
            return ['ok' => false, 'payload' => null, 'errors' => ['Somente MTRs registrados no SINIR podem ser recebidos.']];
        }

        $manifestoCodigo = trim((string)($coleta->sinir_man_numero ?? ''));
        if ($manifestoCodigo === '' && $coleta->numero_mtr) {
            $manifestoCodigo = (string)(int)$coleta->numero_mtr;
        }
        if ($manifestoCodigo === '') {
            return ['ok' => false, 'payload' => null, 'errors' => ['Código do manifesto SINIR ausente.']];
        }

        $digits = preg_replace('/\D/', '', $manifestoCodigo) ?? '';
        if (strlen($digits) > 10) {
            return $this->buildRecebimentoLoteNacional($coletaId, $coleta, $manifestoCodigo, $responsavel, $cargo);
        }

        return $this->buildRecebimentoLoteLegado($coletaId, $coleta, $manifestoCodigo, $responsavel, $cargo);
    }

    /**
     * @return array{ok:bool,payload:?array,errors:string[],format:string}
     */
    private function buildRecebimentoLoteNacional(
        int $coletaId,
        EntityColeta $coleta,
        string $manNumero,
        ?string $responsavel,
        ?string $cargo
    ): array {
        $snapshot = EntityColetaSnapshot::getByColetaId($coletaId);
        if (!$snapshot) {
            return ['ok' => false, 'payload' => null, 'errors' => ['Snapshot da coleta ausente.'], 'format' => 'nacional'];
        }

        $resp = trim((string)($responsavel ?? ''));
        if ($resp === '') {
            $destinador = $coleta->destinador_id
                ? EntityDestinador::getById((int)$coleta->destinador_id)
                : EntityDestinador::getPadrao();
            $resp = trim((string)($destinador->responsavel ?? $snapshot->destinador_responsavel ?? 'Responsável'));
        }

        $itens = EntityColetaItem::getByColetaId($coletaId);
        if ($itens === []) {
            return ['ok' => false, 'payload' => null, 'errors' => ['Coleta sem itens de resíduo.'], 'format' => 'nacional'];
        }

        $errors = [];
        $listaResiduos = [];
        foreach ($itens as $item) {
            $tipo = $item->tipo_residuo_id ? EntityTipoResiduo::getById($item->tipo_residuo_id) : null;
            if (!$tipo || !$this->tipoTemCodigosSinir($tipo)) {
                $errors[] = 'Item "'.($item->nome ?: ('#'.$item->id)).'" sem mapeamento SINIR.';
                continue;
            }
            $codIbama = self::residuoCodigoSinir(
                (string)($item->cod_ibama ?: $tipo->cod_ibama),
                (int)($tipo->cla_codigo ?? 0)
            );
            if ($codIbama === '') {
                $errors[] = 'Item "'.($item->nome ?: ('#'.$item->id)).'" sem código IBAMA.';
                continue;
            }

            $qtd = round((float)$item->quantidade, 4);
            $residuo = [
                'resCodigoIbama' => $codIbama,
                'marQuantidade' => $qtd,
                'marQuantidadeRecebida' => $qtd,
                'traCodigo' => (int)$tipo->tra_codigo,
                'uniCodigo' => (int)$tipo->uni_codigo,
                'tieCodigo' => (int)$tipo->tie_codigo,
                'tiaCodigo' => (int)$tipo->tia_codigo,
                'claCodigo' => (int)$tipo->cla_codigo,
            ];
            $residuo = self::anexarCamposTransportePerigoso($residuo, $tipo, $codIbama, (int)$tipo->cla_codigo);
            if (!empty($residuo['_sinir_erro'])) {
                $errors[] = (string)$residuo['_sinir_erro'];
                unset($residuo['_sinir_erro']);
                continue;
            }
            $listaResiduos[] = $residuo;
        }
        if ($errors !== []) {
            return ['ok' => false, 'payload' => null, 'errors' => $errors, 'format' => 'nacional'];
        }

        $motorista = trim((string)$snapshot->motorista_nome);
        $placa = preg_replace('/[^A-Za-z0-9]/', '', (string)($snapshot->veiculo_placa ?? '')) ?: 'AAA0A00';
        $placa = mb_substr($placa, 0, 7);

        $refDate = $coleta->data_recebimento ?: $coleta->data_coleta ?: date('Y-m-d');
        $dataRecebMs = self::dataRecebimentoEpochMs($refDate);

        $recebimento = [
            'manNumero' => $manNumero,
            'dataRecebimento' => $dataRecebMs,
            'nomeMotorista' => mb_substr($motorista !== '' ? $motorista : 'Motorista', 0, 300),
            'placaVeiculo' => $placa,
            'nomeResponsavelRecebimento' => mb_substr($resp, 0, 300),
            'observacoes' => mb_substr(trim((string)($coleta->relatorio ?? '')), 0, 4000),
            'listaManifestoResiduos' => $listaResiduos,
        ];

        return ['ok' => true, 'payload' => [$recebimento], 'errors' => [], 'format' => 'nacional'];
    }

    /** Epoch ms (meio-dia SP) para dataRecebimento nacional. */
    public static function dataRecebimentoEpochMs(?string $dataYmd): int
    {
        $tz = new \DateTimeZone('America/Sao_Paulo');
        $base = new \DateTimeImmutable('today', $tz);
        if ($dataYmd) {
            $ts = strtotime($dataYmd);
            if ($ts !== false) {
                $base = (new \DateTimeImmutable('@'.$ts))->setTimezone($tz)->setTime(12, 0);
            } else {
                $base = $base->setTime(12, 0);
            }
        } else {
            $base = $base->setTime(12, 0);
        }

        return (int)$base->format('U') * 1000;
    }

    /**
     * @return array{ok:bool,payload:?array,errors:string[],format:string}
     */
    private function buildRecebimentoLoteLegado(
        int $coletaId,
        EntityColeta $coleta,
        string $manifestoCodigo,
        ?string $responsavel,
        ?string $cargo
    ): array {
        $snapshot = EntityColetaSnapshot::getByColetaId($coletaId);
        if (!$snapshot) {
            return ['ok' => false, 'payload' => null, 'errors' => ['Snapshot da coleta ausente.'], 'format' => 'legado'];
        }

        $geradorCnpj = preg_replace('/\D/', '', (string)$snapshot->gerador_cnpj);
        $transportadorCnpj = preg_replace('/\D/', '', (string)$snapshot->transportador_cnpj);
        if ($geradorCnpj === '' || $transportadorCnpj === '') {
            return ['ok' => false, 'payload' => null, 'errors' => ['CNPJ gerador/transportador ausente no snapshot.'], 'format' => 'legado'];
        }

        $dataReceb = $coleta->data_recebimento
            ? date('Ymd', strtotime($coleta->data_recebimento))
            : date('Ymd');
        $dataTransporte = $coleta->data_coleta
            ? date('Ymd', strtotime($coleta->data_coleta))
            : $dataReceb;

        $resp = trim((string)($responsavel ?? ''));
        if ($resp === '') {
            $destinador = $coleta->destinador_id
                ? EntityDestinador::getById((int)$coleta->destinador_id)
                : EntityDestinador::getPadrao();
            $resp = trim((string)($destinador->responsavel ?? $snapshot->destinador_responsavel ?? 'Responsável'));
        }
        $cargoFinal = trim((string)($cargo ?? '')) !== '' ? trim((string)$cargo) : 'Responsável';

        $itens = EntityColetaItem::getByColetaId($coletaId);
        if ($itens === []) {
            return ['ok' => false, 'payload' => null, 'errors' => ['Coleta sem itens de resíduo.'], 'format' => 'legado'];
        }

        $errors = [];
        $itemRecebimento = [];
        $seq = 1;
        foreach ($itens as $item) {
            $tipo = $item->tipo_residuo_id ? EntityTipoResiduo::getById($item->tipo_residuo_id) : null;
            if (!$tipo || !$this->tipoTemCodigosSinir($tipo)) {
                $errors[] = 'Item "'.($item->nome ?: ('#'.$item->id)).'" sem mapeamento SINIR.';
                continue;
            }
            $codIbama = self::residuoCodigoSinir(
                (string)($item->cod_ibama ?: $tipo->cod_ibama),
                (int)($tipo->cla_codigo ?? 0)
            );
            if ($codIbama === '') {
                $errors[] = 'Item "'.($item->nome ?: ('#'.$item->id)).'" sem código IBAMA.';
                continue;
            }
            $itemRecebimento[] = [
                'codigoSequencial' => $seq,
                'justificativa' => null,
                'codigoInterno' => null,
                'qtdRecebida' => round((float)$item->quantidade, 4),
                'residuo' => $codIbama,
                'codigoTecnologia' => (int)$tipo->tra_codigo,
                'codigoTipoEstado' => (int)$tipo->tie_codigo,
            ];
            ++$seq;
        }
        if ($errors !== []) {
            return ['ok' => false, 'payload' => null, 'errors' => $errors, 'format' => 'legado'];
        }

        $motorista = mb_substr(trim((string)$snapshot->motorista_nome), 0, 100);
        $placa = mb_substr(trim((string)$snapshot->veiculo_placa), 0, 10);

        $payload = [
            'manifestoRecebimentoJSONs' => [
                [
                    'manifestoCodigo' => $manifestoCodigo,
                    'cnpGerador' => $geradorCnpj,
                    'cnpTransportador' => $transportadorCnpj,
                    'recebimentoMtrResponsavel' => mb_substr($resp, 0, 150),
                    'recebimentoMtrCargo' => mb_substr($cargoFinal, 0, 100),
                    'recebimentoMtrData' => $dataReceb,
                    'recebimentoMtrObs' => mb_substr(trim((string)($coleta->relatorio ?? '')), 0, 255),
                    'nomeMotorista' => $motorista !== '' ? $motorista : 'Motorista',
                    'placaVeiculo' => $placa !== '' ? $placa : 'S/PLACA',
                    'transporteMtrData' => $dataTransporte,
                    'itemManifestoRecebimentoJSONs' => $itemRecebimento,
                ],
            ],
        ];

        return ['ok' => true, 'payload' => $payload, 'errors' => [], 'format' => 'legado'];
    }

    /**
     * Código IBAMA para a API: 6 dígitos + "(*)" se perigoso (ex.: 200121(*)).
     * Manuais MTR usam sem pontos; o asterisco deve ser preservado.
     * Classe SINIR 1 (Classe I) também força "(*)" quando o cadastro omitiu.
     */
    public static function residuoCodigoSinir(string $codIbama, int $claCodigo = 0): string
    {
        $raw = trim($codIbama);
        if ($raw === '') {
            return '';
        }
        $hazardous = str_contains($raw, '*') || $claCodigo === 1;
        $normalized = IbamaCodigoHelper::normalize($raw);
        if ($normalized === '') {
            $digits = preg_replace('/\D/', '', $raw) ?? '';
            if ($digits === '') {
                return '';
            }
            $digits = str_pad(substr($digits, -6), 6, '0', STR_PAD_LEFT);

            return $hazardous ? $digits.'(*)' : $digits;
        }
        $digits = str_replace('.', '', $normalized);

        return $hazardous ? $digits.'(*)' : $digits;
    }

    /** @return array{0:int,1:int} */
    private function densidadeFromUnidade(string $unidade): array
    {
        return match (strtolower(trim($unidade))) {
            'l' => [1, 2],
            default => [1, 1],
        };
    }

    private function tipoTemCodigosSinir(EntityTipoResiduo $tipo): bool
    {
        return (int)($tipo->tra_codigo ?? 0) > 0
            && (int)($tipo->tie_codigo ?? 0) > 0
            && (int)($tipo->tia_codigo ?? 0) > 0
            && (int)($tipo->cla_codigo ?? 0) > 0
            && (int)($tipo->uni_codigo ?? 0) > 0;
    }

    /**
     * Grupo de embalagem ONU (greCodigo): obrigatório para perigosos (*) e grupos RSS/RDC.
     * Valores SINIR: 1=I, 2=II, 3=III, 4=N/A.
     */
    public static function itemExigeGrupoEmbalagem(string $codIbamaSinir, int $claCodigo): bool
    {
        $raw = strtoupper(trim($codIbamaSinir));
        if (str_contains($raw, '(*)')) {
            return true;
        }
        if ($raw === '200132' || str_starts_with($raw, 'GRUPO ')) {
            return true;
        }

        return in_array($claCodigo, [21, 22, 23, 24, 25, 41, 32, 33, 34, 35], true);
    }

    /** @return int 1–4 */
    public static function greCodigoParaItem(EntityTipoResiduo $tipo): int
    {
        $gre = (int)($tipo->sinir_gre_codigo ?? 0);
        if ($gre >= 1 && $gre <= 4) {
            return $gre;
        }

        return 4;
    }

    /**
     * greCodigo + campos ONU exigidos para perigosos (*) / RSS (manual API nacional).
     *
     * @param array<string,mixed> $residuo
     * @return array<string,mixed>
     */
    public static function anexarCamposTransportePerigoso(
        array $residuo,
        EntityTipoResiduo $tipo,
        string $codIbamaSinir,
        int $claCodigo
    ): array {
        if (!self::itemExigeGrupoEmbalagem($codIbamaSinir, $claCodigo)) {
            return $residuo;
        }

        $gre = self::greCodigoParaItem($tipo);
        $residuo['greCodigo'] = $gre;

        $onu = trim((string)($tipo->sinir_mar_numero_onu ?? ''));
        $risco = trim((string)($tipo->sinir_mar_classe_risco ?? ''));
        $embarque = trim((string)($tipo->sinir_mar_nome_embarque ?? ''));

        if ($gre === 4 && $onu === '' && $risco === '' && $embarque === '') {
            $residuo['marNumeroONU'] = self::PERIGOSO_ONU_NAO_APLICA;
            $residuo['marClasseRisco'] = self::PERIGOSO_ONU_NAO_APLICA;
            $residuo['marNomeEmbarque'] = self::PERIGOSO_ONU_NAO_APLICA;

            return $residuo;
        }

        if ($gre !== 4 && ($onu === '' || $risco === '' || $embarque === '')) {
            $residuo['_sinir_erro'] = 'Item "'.($tipo->nome ?: ('#'.$tipo->id))
                .'" perigoso (greCodigo '.$gre.'): cadastre Nº ONU, classe de risco e nome de embarque no tipo de resíduo.';

            return $residuo;
        }

        $residuo['marNumeroONU'] = mb_substr($onu, 0, 20);
        $residuo['marClasseRisco'] = mb_substr($risco, 0, 20);
        $residuo['marNomeEmbarque'] = mb_substr($embarque, 0, 300);

        return $residuo;
    }
}
