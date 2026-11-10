Web Service de Controle Financeiro Pessoal
API REST desenvolvida em PHP com Slim 4 para registrar receitas e despesas, organizar categorias e consultar o saldo financeiro.
Requisitos
- PHP compatível com as dependências do projeto.
- Composer.
- Dependências do projeto instaladas na pasta vendor.
Estrutura esperada
controle-financeiro/
├── index.php
├── src/
│   └── app.php
├── tests/
│   └── FinanceiroTestes.php
├── vendor/
├── composer.json
└── README.md
Como configurar
Na pasta raiz do projeto, instale as dependências:
composer install
O arquivo index.php deve carregar a aplicação localizada em src/app.php:
<?php

return require __DIR__ . '/src/app.php';
No início de src/app.php, o autoload deve apontar para a pasta vendor da raiz:
require __DIR__ . '/../vendor/autoload.php';
No final de src/app.php, a aplicação deve ser executada apenas fora do modo CLI e retornada para permitir os testes:
if (PHP_SAPI !== 'cli') {
    $app->run();
}

return $app;
Como executar a API
Na raiz do projeto, execute:
php -S localhost:8080 index.php
A API ficará disponível em http://localhost:8080.

As requisições que enviam dados devem utilizar JSON, com o cabeçalho:
Content-Type: application/json
Endpoints
Categorias
Método	Endpoint	Descrição
POST	/categorias	Cadastrar categoria
GET	/categorias	Listar categorias
GET	/categorias/{id}	Consultar categoria por ID
PUT	/categorias/{id}	Atualizar categoria
DELETE	/categorias/{id}	Excluir categoria


Exemplo de cadastro:
{
  "nome": "Transporte",
  "tipo": "despesa"
}
O campo tipo aceita receita ou despesa.
Lançamentos
Método	Endpoint	Descrição
POST	/lancamentos	Cadastrar lançamento
GET	/lancamentos	Listar lançamentos
GET	/lancamentos/{id}	Consultar lançamento por ID
PUT	/lancamentos/{id}	Atualizar lançamento
DELETE	/lancamentos/{id}	Excluir lançamento


Exemplo de cadastro:
{
  "descricao": "Conta de luz",
  "valor": 120.50,
  "data": "2026-05-15",
  "tipo": "despesa",
  "categoria_id": 2
}
Filtros disponíveis na listagem:
- Período: /lancamentos?data_inicio=2026-05-01&data_fim=2026-05-31
- Categoria: /lancamentos?categoria_id=2
- Tipo: /lancamentos?tipo=despesa
Os filtros podem ser combinados.
Resumo financeiro
Método	Endpoint	Descrição
GET	/saldo	Consultar totais de receitas, despesas e saldo
GET	/saldo/periodo?data_inicio=AAAA-MM-DD&data_fim=AAAA-MM-DD	Consultar receitas e despesas de um período


Regras de negócio implementadas
- O valor do lançamento deve ser maior que zero.
- O tipo do lançamento deve ser compatível com o tipo da categoria.
- Não podem existir categorias com nomes repetidos.
- A categoria informada no lançamento deve existir.
- A data do lançamento deve ser informada e válida.
- Uma categoria vinculada a lançamentos não pode ser excluída.
Como executar os testes
Na raiz do projeto, execute:
./vendor/bin/phpunit tests/FinanceiroTestes.php
Os testes automatizados verificam operações de categorias, lançamentos, filtros e resumo financeiro, incluindo cenários de sucesso e de erro.
Observação sobre armazenamento
Nesta versão, os dados são mantidos em arrays na memória. Portanto, não há persistência em banco de dados: os dados voltam aos valores iniciais quando a aplicação é reiniciada.