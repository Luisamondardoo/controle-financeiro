<?php
// index.php
require __DIR__ . '/vendor/autoload.php';
 
use Slim\Factory\AppFactory;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
 
$app = AppFactory::create();
$app->addBodyParsingMiddleware(); // permite ler JSON do body
$app->addRoutingMiddleware();
$app->addErrorMiddleware(true, true, true);
 
// DADOS de MEMÓRIA
$categorias = [
    ['id' => 1, 'nome' => 'Salário', 'tipo' => 'receita'],
    ['id' => 2, 'nome' => 'Alimentação', 'tipo' => 'despesa'],
];
 
$lancamentos = [
    ['id' => 1, 'descricao' => 'Salário de maio', 'valor' => 3000.00, 'data' => '2026-05-05', 'tipo' => 'receita', 'categoria_id' => 1],
    ['id' => 2, 'descricao' => 'Supermercado', 'valor' => 450.00, 'data' => '2026-05-10', 'tipo' => 'despesa', 'categoria_id' => 2],
];
 
// Função auxiliar pra resposta JSON
function jsonResponse(Response $response, $data, int $status = 200): Response
{
    $response->getBody()->write(json_encode($data));
    return $response->withHeader('Content-Type', 'application/json')->withStatus($status);
}
 
// ========== CATEGORIAS (RF01 a RF05) ==========
 
// RF01: Cadastrar categoria
$app->post('/categorias', function (Request $request, Response $response) use (&$categorias) {
    $dados = $request->getParsedBody();
 
    // RN03: nome único
    foreach ($categorias as $cat) {
        if (strtolower($cat['nome']) === strtolower($dados['nome'] ?? '')) {

         $response->getBody()->write(json_encode(['erro' => 'Já existe categoria com esse nome']));
    return $response->withHeader('Content-Type', 'application/json')->withStatus(422);
        }
    }
 
    if (empty($dados['nome']) || empty($dados['tipo'])) { 

  $response->getBody()->write(json_encode('erro' => 'Nome e tipo são obrigatórios'));
    return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
       
    }
 
    if (!in_array($dados['tipo'], ['receita', 'despesa'])) {
        return jsonResponse($response, ['erro' => 'Tipo deve ser receita ou despesa'], 400);
    }
 
    $novaCategoria = [
        'id' => count($categorias) + 1,
        'nome' => $dados['nome'],
        'tipo' => $dados['tipo'],
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
    $categoria = current(array_filter($categorias, fn ($c) => $c['id'] === $id)) ?: null;
 
    if (!$categoria) {
        return jsonResponse($response, ['erro' => 'Categoria não encontrada'], 404);
    }
    return jsonResponse($response, $categoria);
});
 
// RF04: Atualizar categoria
$app->put('/categorias/{id}', function (Request $request, Response $response, array $args) use (&$categorias) {
    $id = (int) $args['id'];
    $dados = $request->getParsedBody();
 
    $indice = null;
    foreach ($categorias as $i => $cat) {
        if ($cat['id'] === $id) {
            $indice = $i;
            break;
        }
    }
 
    if ($indice === null) {
        return jsonResponse($response, ['erro' => 'Categoria não encontrada'], 404);
    }
 
    foreach ($categorias as $cat) {
        if ($cat['id'] !== $id && strtolower($cat['nome']) === strtolower($dados['nome'] ?? '')) {
            return jsonResponse($response, ['erro' => 'Já existe categoria com esse nome'], 422);
        }
    }
 
    if (!empty($dados['nome'])) {
        $categorias[$indice]['nome'] = $dados['nome'];
    }
    if (!empty($dados['tipo']) && in_array($dados['tipo'], ['receita', 'despesa'])) {
        $categorias[$indice]['tipo'] = $dados['tipo'];
    }
 
    return jsonResponse($response, $categorias[$indice]);
});
 
// RF05: Excluir categoria (bloqueia se tiver lançamentos)
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
        return jsonResponse($response, ['erro' => 'Categoria não encontrada'], 404);
    }
 
    foreach ($lancamentos as $lanc) {
        if ($lanc['categoria_id'] === $id) {
            return jsonResponse($response, ['erro' => 'Categoria possui lançamentos vinculados'], 422);
        }
    }
 
    array_splice($categorias, $indice, 1);
    return $response->withStatus(204);
});
 
// ========== LANÇAMENTOS (RF06 a RF13) ==========
 
// RF06: Cadastrar lançamento
$app->post('/lancamentos', function (Request $request, Response $response) use (&$lancamentos, $categorias) {
    $dados = $request->getParsedBody();
 
    // RN01: valor > 0
    if (!isset($dados['valor']) || $dados['valor'] <= 0) {
        return jsonResponse($response, ['erro' => 'Valor deve ser maior que zero'], 422);
    }
 
    // RN05: data obrigatória e válida
    if (empty($dados['data']) || !strtotime($dados['data'])) {
        return jsonResponse($response, ['erro' => 'Data inválida ou ausente'], 422);
    }
 
    // RN04: categoria existe
    $categoria = null;
    foreach ($categorias as $cat) {
        if ($cat['id'] === (int) ($dados['categoria_id'] ?? 0)) {
            $categoria = $cat;
            break;
        }
    }
    if (!$categoria) {
        return jsonResponse($response, ['erro' => 'Categoria não encontrada'], 422);
    }
 
    // RN02: tipo compatível com a categoria
    if ($dados['tipo'] !== $categoria['tipo']) {
        return jsonResponse($response, ['erro' => 'Tipo do lançamento incompatível com a categoria'], 422);
    }
 
    $novoLancamento = [
        'id' => count($lancamentos) + 1,
        'descricao' => $dados['descricao'] ?? '',
        'valor' => (float) $dados['valor'],
        'data' => $dados['data'],
        'tipo' => $dados['tipo'],
        'categoria_id' => (int) $dados['categoria_id'],
    ];
    $lancamentos[] = $novoLancamento;
 
    return jsonResponse($response, $novoLancamento, 201);
});
 
// RF07: Listar lançamentos (com filtros RF11, RF12, RF13)
$app->get('/lancamentos', function (Request $request, Response $response) use ($lancamentos) {
    $params = $request->getQueryParams();
    $resultado = $lancamentos;
 
    if (!empty($params['data_inicio']) && !empty($params['data_fim'])) {
        $resultado = array_filter($resultado, fn ($l) => $l['data'] >= $params['data_inicio'] && $l['data'] <= $params['data_fim']);
    }
    if (!empty($params['categoria_id'])) {
        $resultado = array_filter($resultado, fn ($l) => $l['categoria_id'] === (int) $params['categoria_id']);
    }
    if (!empty($params['tipo'])) {
        $resultado = array_filter($resultado, fn ($l) => $l['tipo'] === $params['tipo']);
    }
 
    return jsonResponse($response, array_values($resultado));
});
 
// RF08: Consultar lançamento por ID
$app->get('/lancamentos/{id}', function (Request $request, Response $response, array $args) use ($lancamentos) {
    $id = (int) $args['id'];
    $lanc = current(array_filter($lancamentos, fn ($l) => $l['id'] === $id)) ?: null;
 
    if (!$lanc) {
        return jsonResponse($response, ['erro' => 'Lançamento não encontrado'], 404);
    }
    return jsonResponse($response, $lanc);
});
 
// RF09: Atualizar lançamento
$app->put('/lancamentos/{id}', function (Request $request, Response $response, array $args) use (&$lancamentos, $categorias) {
    $id = (int) $args['id'];
    $dados = $request->getParsedBody();
 
    $indice = null;
    foreach ($lancamentos as $i => $l) {
        if ($l['id'] === $id) {
            $indice = $i;
            break;
        }
    }
 
    if ($indice === null) {
        return jsonResponse($response, ['erro' => 'Lançamento não encontrado'], 404);
    }
 
    if (isset($dados['valor']) && $dados['valor'] <= 0) {
        return jsonResponse($response, ['erro' => 'Valor deve ser maior que zero'], 422);
    }
 
    if (isset($dados['categoria_id'])) {
        $catExiste = false;
        foreach ($categorias as $cat) {
            if ($cat['id'] === (int) $dados['categoria_id']) {
                $catExiste = true;
                if (isset($dados['tipo']) && $dados['tipo'] !== $cat['tipo']) {
                    return jsonResponse($response, ['erro' => 'Tipo incompatível com a categoria'], 422);
                }
                break;
            }
        }
        if (!$catExiste) {
            return jsonResponse($response, ['erro' => 'Categoria não encontrada'], 422);
        }
    }
 
    if (isset($dados['data']) && !strtotime($dados['data'])) {
        return jsonResponse($response, ['erro' => 'Data inválida'], 422);
    }
 
    foreach (['descricao', 'valor', 'data', 'tipo', 'categoria_id'] as $campo) {
        if (isset($dados[$campo])) {
            $lancamentos[$indice][$campo] = $dados[$campo];
        }
    }
 
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
        return jsonResponse($response, ['erro' => 'Lançamento não encontrado'], 404);
    }
 
    array_splice($lancamentos, $indice, 1);
    return $response->withStatus(204);
});
 
//  RESUMO FINANCEIRO (RF14 e RF15)
 
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
        'saldo' => $receitas - $despesas,
    ]);
});
 
// RF15: Totais em um período
$app->get('/saldo/periodo', function (Request $request, Response $response) use ($lancamentos) {
    $params = $request->getQueryParams();
 
    if (empty($params['data_inicio']) || empty($params['data_fim'])) {
        return jsonResponse($response, ['erro' => 'Informe data_inicio e data_fim'], 400);
    }
 
    $receitas = 0;
    $despesas = 0;
 
    foreach ($lancamentos as $l) {
        if ($l['data'] >= $params['data_inicio'] && $l['data'] <= $params['data_fim']) {
            if ($l['tipo'] === 'receita') {
                $receitas += $l['valor'];
            } else {
                $despesas += $l['valor'];
            }
        }
    }
 
    return jsonResponse($response, [
        'periodo' => ['inicio' => $params['data_inicio'], 'fim' => $params['data_fim']],
        'total_receitas' => $receitas,
        'total_despesas' => $despesas,
    ]);
});
 
// Só roda se não for via CLI (assim o PHPUnit consegue carregar o $app)
if (php_sapi_name() !== 'cli') {
    $app->run();
}
 
// return $app;