
<?php

use PHPUnit\Framework\TestCase;
use Slim\Psr7\Factory\ServerRequestFactory;

class FinanceiroTestes extends TestCase
{
    private $app;
    private $requestFactory;

    protected function setUp(): void
    {
        $this->app = require __DIR__ . '/../index.php';
        $this->requestFactory = new ServerRequestFactory();
    }

    private function requisicao(string $metodo, string $uri, ?array $dados = null)
    {
        $request = $this->requestFactory->createServerRequest($metodo, $uri);

        if ($dados !== null) {
            $request = $request->withParsedBody($dados);
        }

        return $this->app->handle($request);
    }

    private function respostaJson($response): array
    {
        $conteudo = (string) $response->getBody();
        return $conteudo === '' ? [] : (json_decode($conteudo, true) ?? []);
    }

    // CATEGORIAS — RF01 a RF05

    public function testRF01CadastrarCategoria(): void
    {
        $response = $this->requisicao('POST', '/categorias', [
            'nome' => 'Transporte',
            'tipo' => 'despesa',
        ]);

        $this->assertSame(201, $response->getStatusCode());
        $dados = $this->respostaJson($response);
        $this->assertSame('Transporte', $dados['nome']);
        $this->assertSame('despesa', $dados['tipo']);
        $this->assertArrayHasKey('id', $dados);
    }

    public function testRF01RejeitarCategoriaSemCamposObrigatorios(): void
    {
        $response = $this->requisicao('POST', '/categorias', [
            'nome' => 'Transporte'
        ]);

        $this->assertSame(400, $response->getStatusCode());
        $this->assertArrayHasKey('erro', $this->respostaJson($response));
    }

    public function testRF01RejeitarTipoDeCategoriaInvalido(): void
    {
        $response = $this->requisicao('POST', '/categorias', [
            'nome' => 'Outros',
            'tipo' => 'outro',
        ]);

        $this->assertSame(400, $response->getStatusCode());
        $this->assertArrayHasKey('erro', $this->respostaJson($response));
    }

    public function testRF01RejeitarNomeDeCategoriaDuplicado(): void
    {
        $response = $this->requisicao('POST', '/categorias', [
            'nome' => 'salário',
            'tipo' => 'receita',
        ]);

        $this->assertSame(422, $response->getStatusCode());
        $this->assertArrayHasKey('erro', $this->respostaJson($response));
    }

    public function testRF02ListarCategorias(): void
    {
        $response = $this->requisicao('GET', '/categorias');

        $this->assertSame(200, $response->getStatusCode());
        $dados = $this->respostaJson($response);
        $this->assertIsArray($dados);
        $this->assertCount(2, $dados);
    }

    public function testRF03ConsultarCategoriaExistente(): void
    {
        $response = $this->requisicao('GET', '/categorias/1');

        $this->assertSame(200, $response->getStatusCode());
        $dados = $this->respostaJson($response);
        $this->assertSame('Salário', $dados['nome']);
    }

    public function testRF03ConsultarCategoriaInexistente(): void
    {
        $response = $this->requisicao('GET', '/categorias/999');

        $this->assertSame(404, $response->getStatusCode());
        $this->assertArrayHasKey('erro', $this->respostaJson($response));
    }

    public function testRF04AtualizarCategoria(): void
    {
        $response = $this->requisicao('PUT', '/categorias/1', [
            'nome' => 'Salário mensal',
        ]);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('Salário mensal', $this->respostaJson($response)['nome']);
    }

    public function testRF04RejeitarAtualizacaoDeCategoriaInexistente(): void
    {
        $response = $this->requisicao('PUT', '/categorias/999', [
            'nome' => 'Nova categoria',
        ]);

        $this->assertSame(404, $response->getStatusCode());
        $this->assertArrayHasKey('erro', $this->respostaJson($response));
    }

    public function testRF04RejeitarNomeDuplicadoAoAtualizarCategoria(): void
    {
        $response = $this->requisicao('PUT', '/categorias/1', [
            'nome' => 'Alimentação',
        ]);

        $this->assertSame(422, $response->getStatusCode());
        $this->assertArrayHasKey('erro', $this->respostaJson($response));
    }

    public function testRF04RejeitarTipoIncompativelComLancamentosExistentes(): void
    {
        $response = $this->requisicao('PUT', '/categorias/1', [
            'tipo' => 'despesa',
        ]);

        $this->assertSame(422, $response->getStatusCode());
        $this->assertArrayHasKey('erro', $this->respostaJson($response));
    }

    public function testRF05ExcluirCategoriaSemLancamentosVinculados(): void
    {
        $criada = $this->requisicao('POST', '/categorias', [
            'nome' => 'Transporte',
            'tipo' => 'despesa',
        ]);

        $id = $this->respostaJson($criada)['id'];

        $response = $this->requisicao('DELETE', '/categorias/' . $id);

        $this->assertSame(204, $response->getStatusCode());
    }

    public function testRF05ImpedirExclusaoDeCategoriaVinculada(): void
    {
        $response = $this->requisicao('DELETE', '/categorias/1');

        $this->assertSame(422, $response->getStatusCode());
        $this->assertArrayHasKey('erro', $this->respostaJson($response));
    }

    public function testRF05RejeitarExclusaoDeCategoriaInexistente(): void
    {
        $response = $this->requisicao('DELETE', '/categorias/999');

        $this->assertSame(404, $response->getStatusCode());
        $this->assertArrayHasKey('erro', $this->respostaJson($response));
    }

    // LANÇAMENTOS — RF06 a RF13

    private function dadosLancamentoValido(): array
    {
        return [
            'descricao' => 'Conta de luz',
            'valor' => 120.50,
            'data' => '2026-05-15',
            'tipo' => 'despesa',
            'categoria_id' => 2,
        ];
    }

    public function testRF06CadastrarLancamento(): void
    {
        $response = $this->requisicao(
            'POST',
            '/lancamentos',
            $this->dadosLancamentoValido()
        );

        $this->assertSame(201, $response->getStatusCode());
        $dados = $this->respostaJson($response);
        $this->assertSame('Conta de luz', $dados['descricao']);
        $this->assertEquals(120.50, $dados['valor']);
    }

    public function testRF06RejeitarValorMenorOuIgualAZero(): void
    {
        $dados = $this->dadosLancamentoValido();
        $dados['valor'] = 0;

        $response = $this->requisicao('POST', '/lancamentos', $dados);

        $this->assertSame(422, $response->getStatusCode());
        $this->assertArrayHasKey('erro', $this->respostaJson($response));
    }

    public function testRF06RejeitarDataInvalida(): void
    {
        $dados = $this->dadosLancamentoValido();
        $dados['data'] = '31/02/2026';

        $response = $this->requisicao('POST', '/lancamentos', $dados);

        $this->assertSame(422, $response->getStatusCode());
        $this->assertArrayHasKey('erro', $this->respostaJson($response));
    }

    public function testRF06RejeitarCategoriaInexistente(): void
    {
        $dados = $this->dadosLancamentoValido();
        $dados['categoria_id'] = 999;

        $response = $this->requisicao('POST', '/lancamentos', $dados);

        $this->assertSame(422, $response->getStatusCode());
        $this->assertArrayHasKey('erro', $this->respostaJson($response));
    }

    public function testRF06RejeitarTipoIncompativelComCategoria(): void
    {
        $dados = $this->dadosLancamentoValido();
        $dados['tipo'] = 'receita';

        $response = $this->requisicao('POST', '/lancamentos', $dados);

        $this->assertSame(422, $response->getStatusCode());
        $this->assertArrayHasKey('erro', $this->respostaJson($response));
    }

    public function testRF07ListarLancamentos(): void
    {
        $response = $this->requisicao('GET', '/lancamentos');

        $this->assertSame(200, $response->getStatusCode());
        $dados = $this->respostaJson($response);
        $this->assertIsArray($dados);
        $this->assertCount(2, $dados);
    }

    public function testRF07FiltrarLancamentosPorPeriodo(): void
    {
        $response = $this->requisicao(
            'GET',
            '/lancamentos?data_inicio=2026-05-06&data_fim=2026-05-31'
        );

        $this->assertSame(200, $response->getStatusCode());
        $dados = $this->respostaJson($response);
        $this->assertCount(1, $dados);
        $this->assertSame('Supermercado', $dados[0]['descricao']);
    }

    public function testRF07RejeitarPeriodoInvalido(): void
    {
        $response = $this->requisicao(
            'GET',
            '/lancamentos?data_inicio=2026-05-31&data_fim=2026-05-01'
        );

        $this->assertSame(400, $response->getStatusCode());
        $this->assertArrayHasKey('erro', $this->respostaJson($response));
    }

    public function testRF07FiltrarLancamentosPorCategoria(): void
    {
        $response = $this->requisicao('GET', '/lancamentos?categoria_id=1');

        $this->assertSame(200, $response->getStatusCode());
        $dados = $this->respostaJson($response);
        $this->assertCount(1, $dados);
        $this->assertSame('Salário de maio', $dados[0]['descricao']);
    }

    public function testRF07FiltrarLancamentosPorTipo(): void
    {
        $response = $this->requisicao('GET', '/lancamentos?tipo=despesa');

        $this->assertSame(200, $response->getStatusCode());
        $dados = $this->respostaJson($response);
        $this->assertCount(1, $dados);
        $this->assertSame('despesa', $dados[0]['tipo']);
    }

    public function testRF07RejeitarFiltroDeTipoInvalido(): void
    {
        $response = $this->requisicao('GET', '/lancamentos?tipo=transferencia');

        $this->assertSame(400, $response->getStatusCode());
        $this->assertArrayHasKey('erro', $this->respostaJson($response));
    }

    public function testRF08ConsultarLancamentoExistente(): void
    {
        $response = $this->requisicao('GET', '/lancamentos/1');

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(
            'Salário de maio',
            $this->respostaJson($response)['descricao']
        );
    }

    public function testRF08ConsultarLancamentoInexistente(): void
    {
        $response = $this->requisicao('GET', '/lancamentos/999');

        $this->assertSame(404, $response->getStatusCode());
        $this->assertArrayHasKey('erro', $this->respostaJson($response));
    }

    public function testRF09AtualizarLancamento(): void
    {
        $response = $this->requisicao('PUT', '/lancamentos/2', [
            'descricao' => 'Mercado atualizado',
            'valor' => 500,
        ]);

        $this->assertSame(200, $response->getStatusCode());
        $dados = $this->respostaJson($response);
        $this->assertSame('Mercado atualizado', $dados['descricao']);
        $this->assertEquals(500, $dados['valor']);
    }

    public function testRF09RejeitarAtualizacaoDeLancamentoInexistente(): void
    {
        $response = $this->requisicao('PUT', '/lancamentos/999', [
            'descricao' => 'Não existe',
        ]);

        $this->assertSame(404, $response->getStatusCode());
        $this->assertArrayHasKey('erro', $this->respostaJson($response));
    }

    public function testRF09RejeitarValorInvalidoNaAtualizacao(): void
    {
        $response = $this->requisicao('PUT', '/lancamentos/2', [
            'valor' => -10
        ]);

        $this->assertSame(422, $response->getStatusCode());
        $this->assertArrayHasKey('erro', $this->respostaJson($response));
    }

    public function testRF09RejeitarDataInvalidaNaAtualizacao(): void
    {
        $response = $this->requisicao('PUT', '/lancamentos/2', [
            'data' => '2026-02-30'
        ]);

        $this->assertSame(422, $response->getStatusCode());
        $this->assertArrayHasKey('erro', $this->respostaJson($response));
    }

    public function testRF10ExcluirLancamento(): void
    {
        $response = $this->requisicao('DELETE', '/lancamentos/2');

        $this->assertSame(204, $response->getStatusCode());
    }

    public function testRF10RejeitarExclusaoDeLancamentoInexistente(): void
    {
        $response = $this->requisicao('DELETE', '/lancamentos/999');

        $this->assertSame(404, $response->getStatusCode());
        $this->assertArrayHasKey('erro', $this->respostaJson($response));
    }

    // RESUMO FINANCEIRO — RF14 e RF15

    public function testRF14ConsultarSaldoAtual(): void
    {
        $response = $this->requisicao('GET', '/saldo');

        $this->assertSame(200, $response->getStatusCode());
        $dados = $this->respostaJson($response);
        $this->assertEquals(3000.00, $dados['total_receitas']);
        $this->assertEquals(450.00, $dados['total_despesas']);
        $this->assertEquals(2550.00, $dados['saldo']);
    }

    public function testRF15ConsultarTotaisPorPeriodo(): void
    {
        $response = $this->requisicao(
            'GET',
            '/saldo/periodo?data_inicio=2026-05-01&data_fim=2026-05-31'
        );

        $this->assertSame(200, $response->getStatusCode());
        $dados = $this->respostaJson($response);
        $this->assertEquals(3000.00, $dados['total_receitas']);
        $this->assertEquals(450.00, $dados['total_despesas']);
    }

    public function testRF15RejeitarPeriodoSemDatas(): void
    {
        $response = $this->requisicao('GET', '/saldo/periodo');

        $this->assertSame(400, $response->getStatusCode());
        $this->assertArrayHasKey('erro', $this->respostaJson($response));
    }

    public function testRF15RejeitarPeriodoComDatasInvalidas(): void
    {
        $response = $this->requisicao(
            'GET',
            '/saldo/periodo?data_inicio=2026-06-30&data_fim=2026-05-01'
        );

        $this->assertSame(400, $response->getStatusCode());
        $this->assertArrayHasKey('erro', $this->respostaJson($response));
    }
}
