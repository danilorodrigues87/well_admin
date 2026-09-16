<?php

/**
 * Seed / atualização dos artigos da Central de Ajuda (um por módulo do painel).
 * Uso: php database/scripts/seed_help_modulos.php
 */

if (!class_exists(\App\Model\Db\Database::class)) {
    require __DIR__.'/../../includes/app.php';
}

use App\Model\Db\Database;

$db = new Database();

$categorias = [
    ['titulo' => 'Cadastros', 'slug' => 'cadastros', 'ordem' => 25],
    ['titulo' => 'Comercial', 'slug' => 'comercial', 'ordem' => 28],
    ['titulo' => 'Sistema', 'slug' => 'sistema', 'ordem' => 45],
];

foreach ($categorias as $cat) {
    $db->execute(
        'INSERT INTO help_categorias (titulo, slug, ordem, ativo)
         SELECT ?, ?, ?, 1 FROM DUAL
         WHERE NOT EXISTS (SELECT 1 FROM help_categorias WHERE slug = ?)',
        [$cat['titulo'], $cat['slug'], $cat['ordem'], $cat['slug']]
    );
}

$catId = static function (string $slug) use ($db): int {
    $row = $db->execute('SELECT id FROM help_categorias WHERE slug = ? LIMIT 1', [$slug])->fetch(PDO::FETCH_ASSOC);

    return (int)($row['id'] ?? 0);
};

$upsert = static function (array $art) use ($db, $catId): void {
    $idCat = $catId($art['categoria']);
    if ($idCat <= 0) {
        echo "Categoria não encontrada: {$art['categoria']}\n";
        return;
    }
    $exists = $db->execute('SELECT id FROM help_artigos WHERE slug = ? LIMIT 1', [$art['slug']])->fetch(PDO::FETCH_ASSOC);
    if ($exists) {
        $db->execute(
            'UPDATE help_artigos SET id_categoria = ?, titulo = ?, resumo = ?, corpo = ?, ordem = ?, publicado = 1 WHERE slug = ?',
            [$idCat, $art['titulo'], $art['resumo'], $art['corpo'], $art['ordem'], $art['slug']]
        );
        echo "Atualizado: {$art['slug']}\n";
        return;
    }
    $db->execute(
        'INSERT INTO help_artigos (id_categoria, titulo, slug, resumo, corpo, ordem, publicado)
         VALUES (?, ?, ?, ?, ?, ?, 1)',
        [$idCat, $art['titulo'], $art['slug'], $art['resumo'], $art['corpo'], $art['ordem']]
    );
    echo "Inserido: {$art['slug']}\n";
};

$artigos = [
    [
        'categoria' => 'primeiros-passos',
        'slug' => 'mod-dashboard',
        'titulo' => 'Dashboard',
        'ordem' => 11,
        'resumo' => 'Indicadores operacionais e financeiros na tela inicial.',
        'corpo' => <<<'HTML'
<h4>O que é</h4>
<p>O <strong>Dashboard</strong> reúne os principais números da operação: coletas do período, clientes ativos, receita prevista e alertas recentes.</p>
<h4>Como acessar</h4>
<p>Menu lateral → <strong>Dashboard</strong> (primeiro item) ou <code>/painel</code>.</p>
<h4>O que você encontra</h4>
<ul>
<li>Gráficos de coletas e volume por tipo de resíduo</li>
<li>Resumo de clientes e situação de pagamentos</li>
<li>Atalhos visuais para módulos mais usados</li>
</ul>
<h4>Dicas</h4>
<ul>
<li>Use o dashboard no início do dia para priorizar rotas e cobranças em aberto.</li>
<li>Se algum gráfico estiver vazio, confira filtros de data e se há coletas lançadas no período.</li>
</ul>
HTML,
    ],
    [
        'categoria' => 'primeiros-passos',
        'slug' => 'mod-perfil',
        'titulo' => 'Perfil do usuário',
        'ordem' => 12,
        'resumo' => 'Altere sua senha e dados básicos de acesso.',
        'corpo' => <<<'HTML'
<h4>O que é</h4>
<p>Em <strong>Perfil</strong> você gerencia seus dados de login no painel administrativo.</p>
<h4>Como acessar</h4>
<p>Menu <strong>Sistema → Perfil</strong>.</p>
<h4>Passo a passo</h4>
<ol>
<li>Abra o perfil e confira nome e e-mail exibidos.</li>
<li>Para trocar a senha, informe a senha atual e a nova senha (mínimo recomendado: 8 caracteres).</li>
<li>Salve e faça login novamente se solicitado.</li>
</ol>
HTML,
    ],
    [
        'categoria' => 'operacao',
        'slug' => 'mod-coletas',
        'titulo' => 'Coletas / MTR',
        'ordem' => 11,
        'resumo' => 'Consulta, filtros e detalhes das coletas registradas.',
        'corpo' => <<<'HTML'
<h4>O que é</h4>
<p>Listagem de todas as coletas e MTRs emitidos, com status, cliente, resíduos e datas.</p>
<h4>Como acessar</h4>
<p>Menu <strong>Operação → Coletas / MTR</strong>.</p>
<h4>Passo a passo</h4>
<ol>
<li>Use a busca por cliente, CNPJ ou número do MTR.</li>
<li>Filtre por status (rascunho, finalizada, cancelada) e período.</li>
<li>Clique em uma linha ou no botão de detalhes para ver evidências, pesos e histórico.</li>
</ol>
<h4>Status comuns</h4>
<ul>
<li><strong>Rascunho:</strong> coleta iniciada, ainda editável.</li>
<li><strong>Finalizada:</strong> coleta concluída com MTR válido.</li>
<li><strong>Cancelada:</strong> coleta invalidada (mantida apenas para auditoria).</li>
</ul>
HTML,
    ],
    [
        'categoria' => 'operacao',
        'slug' => 'mod-coleta-nova',
        'titulo' => 'Lançar Coleta',
        'ordem' => 12,
        'resumo' => 'Assistente para registrar coleta e gerar MTR.',
        'corpo' => <<<'HTML'
<h4>O que é</h4>
<p>Fluxo guiado em etapas para registrar uma nova coleta ambiental.</p>
<h4>Como acessar</h4>
<p>Menu <strong>Operação → Lançar Coleta</strong>.</p>
<h4>Etapas do assistente</h4>
<ol>
<li><strong>Cliente:</strong> selecione o gerador; endereço e plano são carregados automaticamente.</li>
<li><strong>Resíduos:</strong> informe tipos, quantidades e unidades conforme o plano/contrato.</li>
<li><strong>Transporte:</strong> veículo, motorista e rota quando aplicável.</li>
<li><strong>Destinação:</strong> destinador final e observações legais.</li>
<li><strong>Revisão:</strong> confira totais e finalize para emitir o MTR.</li>
</ol>
<h4>Dicas</h4>
<ul>
<li>Clientes, tipos de resíduo e veículos precisam estar cadastrados antes.</li>
<li>Coletas em rascunho podem ser retomadas pela listagem em Coletas / MTR.</li>
</ul>
HTML,
    ],
    [
        'categoria' => 'operacao',
        'slug' => 'mod-agendamentos',
        'titulo' => 'Agendamentos',
        'ordem' => 13,
        'resumo' => 'Planeje coletas futuras por cliente e rota.',
        'corpo' => <<<'HTML'
<h4>O que é</h4>
<p>Calendário operacional para programar visitas de coleta antes do lançamento efetivo.</p>
<h4>Como acessar</h4>
<p>Menu <strong>Operação → Agendamentos</strong>.</p>
<h4>Passo a passo</h4>
<ol>
<li>Selecione data e cliente (ou rota).</li>
<li>Defina horário previsto e observações para a equipe de campo.</li>
<li>No dia, converta o agendamento em coleta via Lançar Coleta ou app de rota.</li>
</ol>
HTML,
    ],
    [
        'categoria' => 'operacao',
        'slug' => 'mod-rota-dia',
        'titulo' => 'Rota do dia',
        'ordem' => 14,
        'resumo' => 'Ordem de visitas, mapa e execução da rota diária.',
        'corpo' => <<<'HTML'
<h4>O que é</h4>
<p>Visão da rota operacional do dia: sequência de clientes, mapa e status de cada parada.</p>
<h4>Como acessar</h4>
<p>Menu <strong>Operação → Rota do dia</strong>.</p>
<h4>Funcionalidades</h4>
<ul>
<li>Lista ordenada de clientes da rota atribuída ao veículo/motorista</li>
<li>Mapa com geolocalização (quando o cliente possui coordenadas)</li>
<li>Reordenação manual da sequência de paradas</li>
</ul>
<h4>Pré-requisitos</h4>
<p>Cadastre <strong>Rotas</strong>, <strong>Veículos</strong>, <strong>Funcionários</strong> e geolocalize clientes quando possível.</p>
HTML,
    ],
    [
        'categoria' => 'cadastros',
        'slug' => 'mod-clientes',
        'titulo' => 'Clientes (geradores)',
        'ordem' => 10,
        'resumo' => 'Cadastro de geradores, plano, portal e contratos.',
        'corpo' => <<<'HTML'
<h4>O que é</h4>
<p>Cadastro central dos clientes geradores de resíduo atendidos pela operadora.</p>
<h4>Como acessar</h4>
<p>Menu <strong>Cadastros → Clientes</strong>.</p>
<h4>Passo a passo — novo cliente</h4>
<ol>
<li>Clique em <strong>Novo</strong> e preencha razão social, CNPJ, endereço e contatos.</li>
<li>Associe um <strong>plano</strong> comercial (mensalidade e franquia de resíduos).</li>
<li>Salve e, se necessário, configure o <strong>acesso ao portal</strong> (ícone de cadeado).</li>
</ol>
<h4>Ações na listagem</h4>
<ul>
<li><strong>Editar:</strong> dados cadastrais e plano.</li>
<li><strong>Contratos:</strong> ícone de documento — lista e criação de contratos comerciais.</li>
<li><strong>Portal:</strong> cria usuário do gerador para boletos, coletas e assinatura de contrato.</li>
</ul>
HTML,
    ],
    [
        'categoria' => 'cadastros',
        'slug' => 'mod-funcionarios',
        'titulo' => 'Funcionários',
        'ordem' => 11,
        'resumo' => 'Motoristas e equipe operacional.',
        'corpo' => <<<'HTML'
<h4>O que é</h4>
<p>Cadastro de colaboradores vinculados à operação (motoristas, auxiliares).</p>
<h4>Como acessar</h4>
<p>Menu <strong>Cadastros → Funcionários</strong>.</p>
<h4>Passo a passo</h4>
<ol>
<li>Cadastre nome, documento e função.</li>
<li>Mantenha status <strong>Ativo</strong> para aparecer em rotas e coletas.</li>
<li>Desative em vez de excluir quando o colaborador sair da empresa.</li>
</ol>
HTML,
    ],
    [
        'categoria' => 'cadastros',
        'slug' => 'mod-veiculos',
        'titulo' => 'Veículos',
        'ordem' => 12,
        'resumo' => 'Frota utilizada nas coletas e rotas.',
        'corpo' => <<<'HTML'
<h4>O que é</h4>
<p>Cadastro da frota (placa, tipo, capacidade) usada em coletas e rotas do dia.</p>
<h4>Como acessar</h4>
<p>Menu <strong>Cadastros → Veículos</strong>.</p>
<h4>Dicas</h4>
<ul>
<li>Placa deve ser única por operadora.</li>
<li>Veículos inativos não aparecem em novas atribuições de rota.</li>
</ul>
HTML,
    ],
    [
        'categoria' => 'cadastros',
        'slug' => 'mod-planos',
        'titulo' => 'Planos comerciais',
        'ordem' => 13,
        'resumo' => 'Mensalidades, franquias e cláusulas para contratos.',
        'corpo' => <<<'HTML'
<h4>O que é</h4>
<p>Planos definem valor mensal, limites de resíduos e textos que entram nos contratos com clientes.</p>
<h4>Como acessar</h4>
<p>Menu <strong>Cadastros → Planos</strong>.</p>
<h4>Passo a passo</h4>
<ol>
<li>Crie o plano com nome, valor mensal e itens de resíduo incluídos.</li>
<li>Preencha cláusulas de pagamento (parcelado, à vista, pontualidade) — usadas na geração do contrato.</li>
<li>Mantenha o plano <strong>Ativo</strong> para associar a clientes e novos contratos.</li>
</ol>
<p><strong>Importante:</strong> sem plano ativo não é possível gerar contrato comercial.</p>
HTML,
    ],
    [
        'categoria' => 'cadastros',
        'slug' => 'mod-residuo-classes',
        'titulo' => 'Classes de resíduo',
        'ordem' => 14,
        'resumo' => 'Classificação legal (Classe I, II, etc.).',
        'corpo' => <<<'HTML'
<h4>O que é</h4>
<p>Classes de resíduo conforme normas ambientais — base da hierarquia de tipos.</p>
<h4>Como acessar</h4>
<p>Menu <strong>Cadastros → Classes de Resíduo</strong>.</p>
<p>Cadastre antes dos grupos e tipos. Use nomenclatura alinhada ao MTR/SINIR da operadora.</p>
HTML,
    ],
    [
        'categoria' => 'cadastros',
        'slug' => 'mod-residuo-grupos',
        'titulo' => 'Grupos de resíduo',
        'ordem' => 15,
        'resumo' => 'Agrupamento intermediário entre classe e tipo.',
        'corpo' => <<<'HTML'
<h4>O que é</h4>
<p>Grupos organizam tipos de resíduo dentro de uma classe (ex.: serviços de saúde, recicláveis).</p>
<h4>Como acessar</h4>
<p>Menu <strong>Cadastros → Grupos de Resíduo</strong>.</p>
HTML,
    ],
    [
        'categoria' => 'cadastros',
        'slug' => 'mod-tipos-residuos',
        'titulo' => 'Tipos de resíduos',
        'ordem' => 16,
        'resumo' => 'Itens lançados nas coletas e planos.',
        'corpo' => <<<'HTML'
<h4>O que é</h4>
<p>Tipos específicos selecionados no lançamento de coleta e nos itens do plano comercial.</p>
<h4>Como acessar</h4>
<p>Menu <strong>Cadastros → Tipos de Resíduos</strong>.</p>
<h4>Passo a passo</h4>
<ol>
<li>Escolha classe e grupo.</li>
<li>Informe descrição, código interno e unidade padrão (kg, un, L).</li>
<li>Tipos inativos não aparecem em novas coletas.</li>
</ol>
HTML,
    ],
    [
        'categoria' => 'cadastros',
        'slug' => 'mod-rotas',
        'titulo' => 'Rotas',
        'ordem' => 17,
        'resumo' => 'Rotas fixas, clientes e atribuições por veículo.',
        'corpo' => <<<'HTML'
<h4>O que é</h4>
<p>Rotas agrupam clientes em sequência lógica de coleta (ex.: Zona Sul — terças e quintas).</p>
<h4>Como acessar</h4>
<p>Menu <strong>Cadastros → Rotas</strong>.</p>
<h4>Passo a passo</h4>
<ol>
<li>Crie a rota com nome e dias da semana.</li>
<li>Adicione clientes à rota na aba de atribuições.</li>
<li>Vincule veículo e motorista para alimentar a <strong>Rota do dia</strong>.</li>
</ol>
HTML,
    ],
    [
        'categoria' => 'financeiro',
        'slug' => 'mod-pagamentos',
        'titulo' => 'Pagamentos e boletos',
        'ordem' => 11,
        'resumo' => 'Cobrança mensal via Banco Inter.',
        'corpo' => <<<'HTML'
<h4>O que é</h4>
<p>Geração e acompanhamento de boletos mensais dos clientes com plano/contrato ativo.</p>
<h4>Como acessar</h4>
<p>Menu <strong>Financeiro → Pagamentos</strong>.</p>
<h4>Passo a passo</h4>
<ol>
<li>Selecione a <strong>competência</strong> (mês/ano).</li>
<li>Revise valores: mensalidade do plano + excedentes de coletas no período.</li>
<li>Gere boletos em lote ou individualmente.</li>
<li>Acompanhe status: em aberto, pago, vencido ou cancelado.</li>
</ol>
<h4>Integração</h4>
<p>Requer credenciais do Banco Inter configuradas na operadora. Boletos também aparecem no portal do gerador.</p>
HTML,
    ],
    [
        'categoria' => 'financeiro',
        'slug' => 'mod-relatorios',
        'titulo' => 'Relatórios',
        'ordem' => 12,
        'resumo' => 'Exportações e visões analíticas.',
        'corpo' => <<<'HTML'
<h4>O que é</h4>
<p>Relatórios de coletas, faturamento e indicadores para gestão e compliance.</p>
<h4>Como acessar</h4>
<p>Menu <strong>Financeiro → Relatórios</strong>.</p>
<h4>Uso típico</h4>
<ul>
<li>Volume coletado por cliente ou tipo de resíduo</li>
<li>Receita x competência</li>
<li>Exportação CSV/planilha para contabilidade</li>
</ul>
HTML,
    ],
    [
        'categoria' => 'comercial',
        'slug' => 'mod-contratos',
        'titulo' => 'Contratos comerciais',
        'ordem' => 10,
        'resumo' => 'Geração, envio e assinatura de contratos operadora ↔ gerador.',
        'corpo' => <<<'HTML'
<h4>O que é</h4>
<p>Contratos formais entre a operadora e o cliente gerador, com plano, valores, vigência e cláusulas legais.</p>
<h4>Como acessar</h4>
<p>Menu <strong>Comercial → Contratos</strong> ou ícone de documento na listagem de <strong>Clientes</strong>.</p>
<h4>Passo a passo — gerar contrato</h4>
<ol>
<li>Certifique-se de ter <strong>plano ativo</strong> e <strong>modelo de contrato</strong> (Comercial → Modelo de contrato).</li>
<li>Em Contratos, selecione o cliente e clique em <strong>Criar</strong>, ou use Clientes → ícone de contrato → <strong>Novo contrato</strong>.</li>
<li>Escolha plano, duração, valor mensal (opcional — padrão vem do plano), vencimento e 1ª competência.</li>
<li>Salve como <strong>rascunho</strong> e revise a pré-visualização.</li>
<li>Clique em <strong>Enviar para assinatura</strong> — status passa a <em>Aguardando assinatura</em>.</li>
<li>O gerador assina em <strong>Portal do Gerador → Contrato</strong>. Após assinar, status fica <strong>Ativo</strong> e o plano do cliente é atualizado.</li>
</ol>
<h4>Status</h4>
<ul>
<li><strong>Rascunho:</strong> editável, ainda não visível para assinatura.</li>
<li><strong>Aguardando assinatura:</strong> disponível no portal do gerador.</li>
<li><strong>Ativo:</strong> assinado e vigente.</li>
</ul>
HTML,
    ],
    [
        'categoria' => 'sistema',
        'slug' => 'mod-usuarios',
        'titulo' => 'Usuários do painel',
        'ordem' => 10,
        'resumo' => 'Contas de acesso ao admin.',
        'corpo' => <<<'HTML'
<h4>O que é</h4>
<p>Usuários internos que acessam este painel administrativo.</p>
<h4>Como acessar</h4>
<p>Menu <strong>Sistema → Usuários</strong> (somente perfis com permissão).</p>
<h4>Passo a passo</h4>
<ol>
<li>Crie usuário com e-mail, nome e função (perfil RBAC).</li>
<li>Envie credenciais iniciais por canal seguro.</li>
<li>Desative usuários que não devem mais acessar o sistema.</li>
</ol>
HTML,
    ],
    [
        'categoria' => 'sistema',
        'slug' => 'mod-funcoes',
        'titulo' => 'Funções e módulos (RBAC)',
        'ordem' => 11,
        'resumo' => 'Controle de permissões por função.',
        'corpo' => <<<'HTML'
<h4>O que é</h4>
<p>Define quais módulos do menu cada função pode acessar (ex.: operação sem financeiro).</p>
<h4>Como acessar</h4>
<p>Menu <strong>Sistema → Funções e Módulos</strong> — restrito a administradores.</p>
<h4>Passo a passo</h4>
<ol>
<li>Crie ou edite uma função.</li>
<li>Marque os módulos permitidos (Coletas, Clientes, Pagamentos, etc.).</li>
<li>Associe a função aos usuários em Usuários.</li>
</ol>
<p>Usuários com flag <strong>Admin</strong> ignoram restrições e veem todos os módulos.</p>
HTML,
    ],
    [
        'categoria' => 'sistema',
        'slug' => 'mod-operadora',
        'titulo' => 'Operadora',
        'ordem' => 12,
        'resumo' => 'Dados da empresa e configurações gerais.',
        'corpo' => <<<'HTML'
<h4>O que é</h4>
<p>Configurações da operadora Well (razão social, CNPJ, contatos, integrações).</p>
<h4>Como acessar</h4>
<p>Menu <strong>Sistema → Operadora</strong>.</p>
<h4>Itens comuns</h4>
<ul>
<li>Dados cadastrais exibidos em documentos e boletos</li>
<li>Parâmetros de cobrança (multa, mora)</li>
<li>Chaves de integração (maps, banco)</li>
</ul>
<p>O <strong>modelo HTML do contrato</strong> fica em Comercial → Modelo de contrato.</p>
HTML,
    ],
    [
        'categoria' => 'sistema',
        'slug' => 'mod-termos',
        'titulo' => 'Termos de uso',
        'ordem' => 13,
        'resumo' => 'Aceite obrigatório na primeira entrada.',
        'corpo' => <<<'HTML'
<h4>O que é</h4>
<p>Termos legais de uso do sistema. Novos usuários devem aceitar antes de navegar no painel.</p>
<h4>Como acessar</h4>
<p>Redirecionamento automático na login ou menu quando pendente.</p>
<p>Após aceitar, o registro fica vinculado ao usuário com data e versão dos termos.</p>
HTML,
    ],
];

// Atualiza artigos genéricos iniciais
$db->execute(
    'UPDATE help_artigos SET corpo = ?, resumo = ?, publicado = 1 WHERE slug = ?',
    [
        '<p>Bem-vindo ao painel Well Soluções Ambientais. Use o menu lateral — os itens visíveis dependem da sua função.</p>'
        .'<p>Consulte esta Central de Ajuda: cada módulo do menu possui um artigo com passo a passo.</p>'
        .'<p>Atalho: <strong>Dashboard</strong> para indicadores; <strong>Operação</strong> para coletas; <strong>Cadastros</strong> para clientes e planos.</p>',
        'Navegação, permissões e visão geral do painel administrativo.',
        'visao-geral-painel',
    ]
);

foreach ($artigos as $art) {
    $upsert($art);
}

echo "Seed de ajuda concluído.\n";
