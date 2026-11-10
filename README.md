
# Controle Financeiro

Projeto acadêmico desenvolvido no Senac Joinville utilizando PHP e Slim Framework 4.

## Sobre o projeto

A API permite gerenciar categorias e lançamentos financeiros, além de consultar saldos e filtrar movimentações.

## Tecnologias utilizadas

- PHP
- Slim Framework 4
- Composer
- PHPUnit

## Funcionalidades

- Cadastro, consulta, atualização e exclusão de categorias.
- Cadastro, consulta, atualização e exclusão de lançamentos.
- Consulta do saldo total.
- Consulta do saldo por período.
- Filtros e validações.

## Como executar

Instale as dependências:

composer install

Inicie o servidor:

php -S localhost:8080 index.php

Acesse: http://localhost:8080

## Testes

Para executar os testes automatizados:

./vendor/bin/phpunit tests/FinanceiroTestes.php

Resultado: 37 testes aprovados e 84 verificações realizadas.

## Observação

Os dados são armazenados em memória e podem ser perdidos ao reiniciar a aplicação.

