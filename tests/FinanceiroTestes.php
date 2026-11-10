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

    public function testListarCategorias()

    {

        $request = $this->requestFactory->createServerRequest(

            'GET',

            '/categorias'

        );

        $response = $this->app->handle($request);

        $this->assertEquals(200, $response->getStatusCode());

        $dados = json_decode((string) $response->getBody(), true);

        $this->assertIsArray($dados);

    }

    public function testListarLancamentos()

    {

        $request = $this->requestFactory->createServerRequest(

            'GET',

            '/lancamentos'

        );

        $response = $this->app->handle($request);

        $this->assertEquals(200, $response->getStatusCode());

        $dados = json_decode((string) $response->getBody(), true);

        $this->assertIsArray($dados);

    }

}
 