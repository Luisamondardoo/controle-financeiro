
<?php

require __DIR__ . '/../vendor/autoload.php';

use Slim\Factory\AppFactory;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

$app = AppFactory::create();

$app->addBodyParsingMiddleware();
$app->addRoutingMiddleware();
$app->addErrorMiddleware(true, true, true);

// ========== DADOS EM MEMÓRIA ==========

$categorias = [
    ['id' => 1, 'nome' => 'Salário', 'tipo' => 'receita'],
    ['id' => 2, 'nome' => 'Alimentação', 'tipo' => 'despesa'],
];

$lancamentos = [
    ['id' => 1, 'descricao' => 'Salário de maio', 'valor' => 3000.00, 'data' => '2026-05-05', 'tipo' => 'receita', 'categoria_id' => 1],
    ['id' => 2, 'descricao' => 'Supermercado', 'valor' => 450.00, 'data' => '2026-05-10', 'tipo' => 'despesa', 'categoria_id' => 2],
];

// ========== FUNÇÕES AUXILIARES ==========

// Resposta JSON
if (!function_exists('jsonResponse')) {
    function jsonResponse(Response $response, $data, int $status = 200): Response
    {
        $response->getBody()->write(
            json_encode($data, JSON_UNESCAPED_UNICODE)
        );

        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus($status);
    }
}

// Validação de data
if (!function_exists('dataValida')) {
    function dataValida($data): bool
    {
        if (!is_string($data)) {
            return false;
        }

        $dataObj = DateTime::createFromFormat('!Y-m-d', $data);

        return $dataObj !== false
            && $dataObj->format('Y-m-d') === $data;
    }
}

// ========== CATEGORIAS (RF01 A RF05) ==========

// RF01: Cadastrar categoria
$app->post('/categorias', function (Request $request, Response $response) use (&$categorias) {

    $dados = (array) ($request->getParsedBody() ?? []);

    if (empty($dados['nome']) || empty($dados['tipo'])) {
        return jsonResponse($response, [
            'erro' => 'Nome e tipo são obrigatórios'
        ], 400);
    }

    if (!in_array($dados['tipo'], ['receita', 'despesa'], true)) {
        return jsonResponse($response, [
            'erro' => 'Tipo deve ser receita ou despesa'
        ], 400);
    }

    // RN03: Nome único
    foreach ($categorias as $cat) {
        if (strtolower($cat['nome']) === strtolower(trim($dados['nome']))) {
            return jsonResponse($response, [
                'erro' => 'Já existe categoria com esse nome'
            ], 422);
        }
    }

    $novaCategoria = [
        'id' => empty($categorias) ? 1 : max(array_column($categorias, 'id')) + 1,
        'nome' => trim($dados['nome']),
        'tipo' => $dados['tipo']
    ];

    $categorias[] = $novaCategoria;

    return jsonResponse($response, $novaCategoria, 201);
});

// RF02: Listar categorias
$app->get('/categorias', function (Request $request, Response $response) use ($categorias) {

    return jsonResponse($response, $categorias);
});

// RF03: Consultar categoria por ID
$app->get('/categorias/{id}', function (Request $request, Response $response, array $args) use ($categorias) {

    $id = (int) $args['id'];

    $categoria = current(array_filter(
        $categorias,
        fn($c) => $c['id'] === $id
    )) ?: null;

    if (!$categoria) {
        return jsonResponse($response, [
            'erro' => 'Categoria não encontrada'
        ], 404);
    }

    return jsonResponse($response, $categoria);
});

// RF04: Atualizar categoria
$app->put('/categorias/{id}', function (Request $request, Response $response, array $args) use (&$categorias, $lancamentos) {

    $id = (int) $args['id'];
    $dados = (array) ($request->getParsedBody() ?? []);

    $indice = null;

    foreach ($categorias as $i => $cat) {
        if ($cat['id'] === $id) {
            $indice = $i;
            break;
        }
    }

    if ($indice === null) {
        return jsonResponse($response, [
            'erro' => 'Categoria não encontrada'
        ], 404);
    }

    if (isset($dados['nome'])) {
        if (trim($dados['nome']) === '') {
            return jsonResponse($response, [
                'erro' => 'Nome não pode ser vazio'
            ], 400);
        }

        foreach ($categorias as $cat) {
            if (
                $cat['id'] !== $id &&
                strtolower($cat['nome']) === strtolower(trim($dados['nome']))
            ) {
                return jsonResponse($response, [
                    'erro' => 'Já existe categoria com esse nome'
                ], 422);
            }
        }
    }

    if (
        isset($dados['tipo']) &&
        !in_array($dados['tipo'], ['receita', 'despesa'], true)
    ) {
        return jsonResponse($response, [
            'erro' => 'Tipo deve ser receita ou despesa'
        ], 400);
    }

    // Não permite alterar o tipo se houver lançamento incompatível
    $novoTipo = $dados['tipo'] ?? $categorias[$indice]['tipo'];

    foreach ($lancamentos as $lanc) {
        if (
            $lanc['categoria_id'] === $id &&
            $lanc['tipo'] !== $novoTipo
        ) {
            return jsonResponse($response, [
                'erro' => 'Categoria possui lançamentos vinculados com outro tipo'
            ], 422);
        }
    }

    if (isset($dados['nome'])) {
        $categorias[$indice]['nome'] = trim($dados['nome']);
    }

    if (isset($dados['tipo'])) {
        $categorias[$indice]['tipo'] = $dados['tipo'];
    }

    return jsonResponse($response, $categorias[$indice]);
});

// RF05: Excluir categoria
$app->delete('/categorias/{id}', function (Request $request, Response $response, array $args) use (&$categorias, $lancamentos) {

    $id = (int) $args['id'];
    $indice = null;

    foreach ($categorias as $i => $cat) {
        if ($cat['id'] === $id) {
            $indice = $i;
            break;
        }
    }

    if ($indice === null) {
        return jsonResponse($response, [
            'erro' => 'Categoria não encontrada'
        ], 404);
    }

    foreach ($lancamentos as $lanc) {
        if ($lanc['categoria_id'] === $id) {
            return jsonResponse($response, [
                'erro' => 'Categoria possui lançamentos vinculados'
            ], 422);
        }
    }

    array_splice($categorias, $indice, 1);

    return $response->withStatus(204);
});

// ========== LANÇAMENTOS (RF06 A RF13) ==========

// RF06: Cadastrar lançamento
$app->post('/lancamentos', function (Request $request, Response $response) use (&$lancamentos, $categorias) {

    $dados = (array) ($request->getParsedBody() ?? []);

    // RN01: Valor maior que zero
    if (
        !isset($dados['valor']) ||
        !is_numeric($dados['valor']) ||
        $dados['valor'] <= 0
    ) {
        return jsonResponse($response, [
            'erro' => 'Valor deve ser maior que zero'
        ], 422);
    }

    // RN05: Data obrigatória e válida
    if (empty($dados['data']) || !dataValida($dados['data'])) {
        return jsonResponse($response, [
            'erro' => 'Data inválida ou ausente'
        ], 422);
    }

    if (
        empty($dados['tipo']) ||
        !in_array($dados['tipo'], ['receita', 'despesa'], true)
    ) {
        return jsonResponse($response, [
            'erro' => 'Tipo deve ser receita ou despesa'
        ], 422);
    }

    // RN04: Categoria deve existir
    $categoria = null;

    foreach ($categorias as $cat) {
        if ($cat['id'] === (int) ($dados['categoria_id'] ?? 0)) {
            $categoria = $cat;
            break;
        }
    }

    if (!$categoria) {
        return jsonResponse($response, [
            'erro' => 'Categoria não encontrada'
        ], 422);
    }

    // RN02: Tipo compatível com a categoria
    if ($dados['tipo'] !== $categoria['tipo']) {
        return jsonResponse($response, [
            'erro' => 'Tipo do lançamento incompatível com a categoria'
        ], 422);
    }

    $novoLancamento = [
        'id' => empty($lancamentos) ? 1 : max(array_column($lancamentos, 'id')) + 1,
        'descricao' => $dados['descricao'] ?? '',
        'valor' => (float) $dados['valor'],
        'data' => $dados['data'],
        'tipo' => $dados['tipo'],
        'categoria_id' => (int) $dados['categoria_id']
    ];

    $lancamentos[] = $novoLancamento;

    return jsonResponse($response, $novoLancamento, 201);
});

// RF07: Listar lançamentos
$app->get('/lancamentos', function (Request $request, Response $response) use ($lancamentos) {

    $params = $request->getQueryParams();
    $resultado = $lancamentos;

    // RF11: Filtro por período
    if (!empty($params['data_inicio']) && !empty($params['data_fim'])) {

        if (
            !dataValida($params['data_inicio']) ||
            !dataValida($params['data_fim']) ||
            $params['data_inicio'] > $params['data_fim']
        ) {
            return jsonResponse($response, [
                'erro' => 'Período de datas inválido'
            ], 400);
        }

        $resultado = array_filter($resultado, fn($l) =>
            $l['data'] >= $params['data_inicio'] &&
            $l['data'] <= $params['data_fim']
        );
    }

    // RF12: Filtro por categoria
    if (!empty($params['categoria_id'])) {
        $resultado = array_filter($resultado, fn($l) =>
            $l['categoria_id'] === (int) $params['categoria_id']
        );
    }

    // RF13: Filtro por tipo
    if (!empty($params['tipo'])) {

        if (!in_array($params['tipo'], ['receita', 'despesa'], true)) {
            return jsonResponse($response, [
                'erro' => 'Tipo inválido'
            ], 400);
        }

        $resultado = array_filter($resultado, fn($l) =>
            $l['tipo'] === $params['tipo']
        );
    }

    return jsonResponse($response, array_values($resultado));
});

// RF08: Consultar lançamento por ID
$app->get('/lancamentos/{id}', function (Request $request, Response $response, array $args) use ($lancamentos) {

    $id = (int) $args['id'];

    $lanc = current(array_filter(
        $lancamentos,
        fn($l) => $l['id'] === $id
    )) ?: null;

    if (!$lanc) {
        return jsonResponse($response, [
            'erro' => 'Lançamento não encontrado'
        ], 404);
    }

    return jsonResponse($response, $lanc);
});

// RF09: Atualizar lançamento
$app->put('/lancamentos/{id}', function (Request $request, Response $response, array $args) use (&$lancamentos, $categorias) {

    $id = (int) $args['id'];
    $dados = (array) ($request->getParsedBody() ?? []);

    $indice = null;

    foreach ($lancamentos as $i => $l) {
        if ($l['id'] === $id) {
            $indice = $i;
            break;
        }
    }

    if ($indice === null) {
        return jsonResponse($response, [
            'erro' => 'Lançamento não encontrado'
        ], 404);
    }

    $atual = $lancamentos[$indice];

    // Verifica valor
    if (isset($dados['valor'])) {
        if (!is_numeric($dados['valor']) || $dados['valor'] <= 0) {
            return jsonResponse($response, [
                'erro' => 'Valor deve ser maior que zero'
            ], 422);
        }
    }

    // Verifica data
    if (isset($dados['data']) && !dataValida($dados['data'])) {
        return jsonResponse($response, [
            'erro' => 'Data inválida'
        ], 422);
    }

    // Verifica tipo
    $novoTipo = $dados['tipo'] ?? $atual['tipo'];

    if (!in_array($novoTipo, ['receita', 'despesa'], true)) {
        return jsonResponse($response, [
            'erro' => 'Tipo inválido'
        ], 422);
    }

    // Verifica categoria
    $novaCategoriaId = isset($dados['categoria_id'])
        ? (int) $dados['categoria_id']
        : $atual['categoria_id'];

    $categoria = null;

    foreach ($categorias as $cat) {
        if ($cat['id'] === $novaCategoriaId) {
            $categoria = $cat;
            break;
        }
    }

    if (!$categoria) {
        return jsonResponse($response, [
            'erro' => 'Categoria não encontrada'
        ], 422);
    }

    // Verifica compatibilidade entre tipo e categoria
    if ($novoTipo !== $categoria['tipo']) {
        return jsonResponse($response, [
            'erro' => 'Tipo incompatível com a categoria'
        ], 422);
    }

    foreach (['descricao', 'valor', 'data'] as $campo) {
        if (isset($dados[$campo])) {
            $lancamentos[$indice][$campo] = $campo === 'valor'
                ? (float) $dados[$campo]
                : $dados[$campo];
        }
    }

    $lancamentos[$indice]['tipo'] = $novoTipo;
    $lancamentos[$indice]['categoria_id'] = $novaCategoriaId;

    return jsonResponse($response, $lancamentos[$indice]);
});

// RF10: Excluir lançamento
$app->delete('/lancamentos/{id}', function (Request $request, Response $response, array $args) use (&$lancamentos) {

    $id = (int) $args['id'];
    $indice = null;

    foreach ($lancamentos as $i => $l) {
        if ($l['id'] === $id) {
            $indice = $i;
            break;
        }
    }

    if ($indice === null) {
        return jsonResponse($response, [
            'erro' => 'Lançamento não encontrado'
        ], 404);
    }

    array_splice($lancamentos, $indice, 1);

    return $response->withStatus(204);
});

// ========== RESUMO FINANCEIRO (RF14 E RF15) ==========

// RF14: Saldo atual
$app->get('/saldo', function (Request $request, Response $response) use ($lancamentos) {

    $receitas = 0;
    $despesas = 0;

    foreach ($lancamentos as $l) {
        if ($l['tipo'] === 'receita') {
            $receitas += $l['valor'];
        } else {
            $despesas += $l['valor'];
        }
    }

    return jsonResponse($response, [
        'total_receitas' => $receitas,
        'total_despesas' => $despesas,
        'saldo' => $receitas - $despesas
    ]);
});

// RF15: Totais em um período
$app->get('/saldo/periodo', function (Request $request, Response $response) use ($lancamentos) {

    $params = $request->getQueryParams();

    if (empty($params['data_inicio']) || empty($params['data_fim'])) {
        return jsonResponse($response, [
            'erro' => 'Informe data_inicio e data_fim'
        ], 400);
    }

    if (
        !dataValida($params['data_inicio']) ||
        !dataValida($params['data_fim']) ||
        $params['data_inicio'] > $params['data_fim']
    ) {
        return jsonResponse($response, [
            'erro' => 'Período de datas inválido'
        ], 400);
    }

    $receitas = 0;
    $despesas = 0;

    foreach ($lancamentos as $l) {

        if (
            $l['data'] >= $params['data_inicio'] &&
            $l['data'] <= $params['data_fim']
        ) {
            if ($l['tipo'] === 'receita') {
                $receitas += $l['valor'];
            } else {
                $despesas += $l['valor'];
            }
        }
    }

    return jsonResponse($response, [
        'periodo' => [
            'inicio' => $params['data_inicio'],
            'fim' => $params['data_fim']
        ],
        'total_receitas' => $receitas,
        'total_despesas' => $despesas
    ]);
});

// ========== EXECUÇÃO ==========

// Permite que o PHPUnit carregue o aplicativo sem executar o servidor
if (PHP_SAPI !== 'cli') {
    $app->run();
}

return $app;