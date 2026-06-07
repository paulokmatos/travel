# Travel Orders API

## Resumo do Projeto

O projeto consiste em um desafio tecnico para criacao de um microsservico em Laravel que gerencia pedidos de viagem corporativa.

A API permite registrar usuarios, autenticar com JWT, criar pedidos de viagem, consultar pedidos, listar com filtros, editar pedidos em status inicial e permitir que usuarios administradores aprovem ou cancelem pedidos de outros usuarios.

## Requisitos do Projeto

- Docker
- Docker Compose
- Git

Para execucao local sem Docker, tambem sera necessario:

- PHP 8.3
- Composer 2
- MySQL 8 ou compativel
- Redis, caso use cache/fila Redis localmente
- Node.js e NPM, apenas para executar scripts Vite

## Tecnologias utilizadas

#### **Infraestrutura:**

- **Docker:** Ferramenta de virtualizacao de containers.
- **Docker Compose:** Utilizado para definir e gerenciar os containers Docker.
- **MySQL 8.4:** Banco de dados relacional da aplicacao.
- **Redis 7:** Utilizado no Docker para cache e fila.
- **Mailpit:** Caixa de email local para apoio em testes de notificacao.

#### **Analise de Codigo:**

- **Laravel Pint:** Ferramenta de formatacao automatica do codigo PHP.

#### **Testes:**

- **PHPUnit:** Framework para testes unitarios e de integracao em PHP.
- **Infection:** Framework para testes de mutacao e validacao da qualidade da cobertura.

#### **Desenvolvimento:**

- **PHP 8.3:** Linguagem base da aplicacao.
- **Laravel 13:** Framework utilizado para API, validacao, Eloquent ORM, rotas, policies e notificacoes.
- **JWT Auth:** Pacote `php-open-source-saver/jwt-auth` para autenticacao via token.
- **Eloquent ORM:** Camada de persistencia utilizada para interacao com o banco MySQL.
- **Laravel Notifications:** Envio de notificacoes por `mail` e `database`.
- **Laravel Queues:** Processamento assincromo de notificacoes.

## Como iniciar o projeto

1 - Clone o repositorio

```bash
git clone https://github.com/paulokmatos/travel.git
cd travel
```

2 - Suba os containers da aplicacao

```bash
docker compose up --build -d
```

O build da imagem instala as dependencias do Composer. O container `app` aguarda o MySQL ficar saudavel e executa automaticamente:

```bash
php artisan migrate --force
php artisan db:seed --force
```

3 - Verifique se os containers estao ativos

```bash
docker compose ps
```

4 - Liste as rotas da API

```bash
docker compose exec app php artisan route:list --path=api
```

5 - Execute os testes da aplicacao

```bash
docker compose --profile test run --rm test
```

6 - Execute os testes de mutacao

```bash
docker compose --profile mutation run --rm mutation
```

Apos subir os containers, o projeto estara acessivel na URL:

```text
http://localhost:8000/api/v1
```

Health check:

```text
http://localhost:8000/up
```

Servicos disponiveis:

- API: `http://localhost:8000/api/v1`
- Mailpit: `http://localhost:8025`
- MySQL: `localhost:3306`
- Redis: `localhost:6379`

Credenciais do administrador inicial:

```text
email: admin@example.com
senha: password
```

## Como iniciar localmente

1 - Instale as dependencias

```bash
composer install
npm install
```

2 - Copie o arquivo `.env.example`

```bash
cp .env.example .env
```

3 - Gere as chaves da aplicacao e do JWT

```bash
php artisan key:generate
php artisan jwt:secret
```

4 - Configure o banco no `.env`

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=travel
DB_USERNAME=root
DB_PASSWORD=

CACHE_STORE=database
QUEUE_CONNECTION=database
MAIL_MAILER=log

ADMIN_NAME="Admin User"
ADMIN_EMAIL=admin@example.com
ADMIN_PASSWORD=password
```

5 - Rode migrations e seeders

```bash
php artisan migrate --seed
```

6 - Inicie a API e o worker da fila

```bash
php artisan serve
php artisan queue:work --tries=3 --timeout=60
```

## Comandos uteis

Rodar suite completa:

```bash
php artisan test --compact
```

Rodar testes de feature:

```bash
composer test:feature
```

Rodar testes unitarios:

```bash
composer test:unit
```

Rodar teste focado da API:

```bash
php artisan test --compact tests/Feature/TravelOrderApiTest.php
```

Rodar mutation testing local com Xdebug:

```bash
composer test:mutation
```

Rodar quality gate de mutation testing:

```bash
composer test:mutation:ci
```

Formatar codigo PHP:

```bash
vendor/bin/pint --dirty --format agent
```

Parar containers:

```bash
docker compose down
```

Zerar banco e volumes Docker:

```bash
docker compose down -v
```

# Arquitetura do projeto

**Domain:** Contem o enum `TravelOrderStatus`, o model `TravelOrder`, regras puras de transicao de status e validacoes de datas.

**Application:** Inclui Actions, Queries e Services responsaveis pelos casos de uso, filtros de listagem e cache.

**Infra:** Contem migrations, factories, seeders, Docker, banco MySQL, Redis, filas e notificacoes persistidas.

**Presentation:** Contem Controllers, Form Requests, API Resources, Policies e rotas HTTP em `routes/api.php`.

Estrutura principal:

```text
app/
  Actions/
  Enums/TravelOrderStatus.php
  Http/Controllers/Api/V1/
  Http/Requests/Api/V1/
  Http/Resources/TravelOrderResource.php
  Models/
  Notifications/
  Policies/
  Queries/
  Rules/
  Services/
  Support/
database/
  factories/
  migrations/
  seeders/
routes/api.php
tests/
  Feature/
  Unit/
```

# Regras de negocio

- Cada pedido pertence a um usuario autenticado.
- Usuarios comuns podem criar, listar, consultar e editar apenas seus proprios pedidos.
- Administradores podem listar e consultar todos os pedidos.
- Apenas administradores podem aprovar ou cancelar pedidos.
- Um administrador nao pode aprovar ou cancelar pedido criado por ele mesmo.
- O cadastro publico sempre cria usuario comum.
- O administrador inicial e criado pelo `DatabaseSeeder` usando `ADMIN_NAME`, `ADMIN_EMAIL` e `ADMIN_PASSWORD`.
- Pedidos nascem com status `solicitado`.
- Pedidos em status `solicitado` podem ser alterados para `aprovado` ou `cancelado`.
- Pedidos `aprovado` ou `cancelado` sao estados terminais e nao podem mudar de status.
- Quando um pedido e aprovado ou cancelado, uma notificacao e enviada para o solicitante.

Status publicos da API:

- `solicitado`
- `aprovado`
- `cancelado`

Cases internos do enum PHP:

- `REQUESTED`
- `APPROVED`
- `CANCELED`

# Autenticacao

Todas as rotas de pedidos exigem token JWT no header:

```http
Authorization: Bearer <token>
```

Caso uma rota protegida seja chamada sem token, a API retorna:

**HTTP STATUS 401**

```json
{
  "message": "Unauthenticated."
}
```

# Endpoints do projeto

## [ POST ] /api/v1/auth/register

Cria um usuario comum e retorna um token JWT.

Exemplo de entrada:

```json
{
  "name": "Paulo Matos",
  "email": "paulo@example.com",
  "password": "password123",
  "password_confirmation": "password123"
}
```

Exemplo de saida:

**HTTP STATUS 201**

```json
{
  "access_token": "jwt-token",
  "token_type": "bearer",
  "expires_in": 3600
}
```

**HTTP STATUS 422** caso sejam enviados dados invalidos.

## [ POST ] /api/v1/auth/login

Autentica um usuario e retorna um token JWT.

Exemplo de entrada:

```json
{
  "email": "admin@example.com",
  "password": "password"
}
```

Exemplo de saida:

**HTTP STATUS 200**

```json
{
  "access_token": "jwt-token",
  "token_type": "bearer",
  "expires_in": 3600
}
```

**HTTP STATUS 401** caso as credenciais sejam invalidas.

## [ GET ] /api/v1/auth/me

Retorna os dados do usuario autenticado.

Exemplo de saida:

**HTTP STATUS 200**

```json
{
  "data": {
    "id": 1,
    "name": "Admin User",
    "email": "admin@example.com",
    "is_admin": true
  }
}
```

**HTTP STATUS 401** caso o token nao seja enviado ou seja invalido.

## [ POST ] /api/v1/auth/refresh

Renova o token JWT do usuario autenticado.

Exemplo de saida:

**HTTP STATUS 200**

```json
{
  "access_token": "new-jwt-token",
  "token_type": "bearer",
  "expires_in": 3600
}
```

## [ POST ] /api/v1/auth/logout

Invalida o token JWT atual.

Exemplo de saida:

**HTTP STATUS 200**

```json
{
  "message": "Token invalidated."
}
```

## [ POST ] /api/v1/travel-orders

Cria um pedido de viagem para o usuario autenticado.

Exemplo de entrada:

```json
{
  "destination": "Lisbon",
  "departure_date": "2026-07-10",
  "return_date": "2026-07-20"
}
```

Exemplo de saida:

**HTTP STATUS 201**

```json
{
  "data": {
    "id": 1,
    "requester_name": "Paulo Matos",
    "requester": {
      "id": 2,
      "name": "Paulo Matos",
      "email": "paulo@example.com"
    },
    "destination": "Lisbon",
    "departure_date": "2026-07-10",
    "return_date": "2026-07-20",
    "status": "solicitado",
    "created_at": "2026-06-06T19:00:00.000000Z",
    "updated_at": "2026-06-06T19:00:00.000000Z"
  }
}
```

**HTTP STATUS 422** caso a data de volta seja anterior a data de ida ou existam campos invalidos.

## [ GET ] /api/v1/travel-orders

Lista pedidos paginados.

Usuarios comuns visualizam apenas os proprios pedidos. Administradores visualizam todos.

Filtros disponiveis:

- `status`: `solicitado`, `aprovado`, `cancelado`
- `destination`: busca parcial por destino
- `created_from`: data inicial de criacao no formato `YYYY-MM-DD`
- `created_to`: data final de criacao no formato `YYYY-MM-DD`
- `travel_from`: data inicial de viagem no formato `YYYY-MM-DD`
- `travel_to`: data final de viagem no formato `YYYY-MM-DD`
- `per_page`: quantidade por pagina, de 1 a 100
- `page`: pagina atual

Exemplo de chamada:

```text
GET /api/v1/travel-orders?status=aprovado&destination=Lis&travel_from=2026-07-01&travel_to=2026-07-31
```

Exemplo de saida:

**HTTP STATUS 200**

```json
{
  "data": [
    {
      "id": 1,
      "requester_name": "Paulo Matos",
      "requester": {
        "id": 2,
        "name": "Paulo Matos",
        "email": "paulo@example.com"
      },
      "destination": "Lisbon",
      "departure_date": "2026-07-10",
      "return_date": "2026-07-20",
      "status": "aprovado",
      "created_at": "2026-06-06T19:00:00.000000Z",
      "updated_at": "2026-06-06T19:00:00.000000Z"
    }
  ],
  "links": {},
  "meta": {}
}
```

**HTTP STATUS 422** caso os filtros sejam invalidos.

## [ GET ] /api/v1/travel-orders/{id}

Consulta um pedido de viagem pelo ID.

Exemplo de saida:

**HTTP STATUS 200**

```json
{
  "data": {
    "id": 1,
    "requester_name": "Paulo Matos",
    "requester": {
      "id": 2,
      "name": "Paulo Matos",
      "email": "paulo@example.com"
    },
    "destination": "Lisbon",
    "departure_date": "2026-07-10",
    "return_date": "2026-07-20",
    "status": "solicitado",
    "created_at": "2026-06-06T19:00:00.000000Z",
    "updated_at": "2026-06-06T19:00:00.000000Z"
  }
}
```

**HTTP STATUS 403** caso um usuario comum tente consultar pedido de outro usuario.

**HTTP STATUS 404** caso o pedido nao exista.

## [ PATCH ] /api/v1/travel-orders/{id}

Edita destino, data de ida ou data de volta de um pedido.

Somente o dono pode editar, e apenas enquanto o pedido estiver com status `solicitado`.

Exemplo de entrada:

```json
{
  "destination": "Porto",
  "departure_date": "2026-08-01",
  "return_date": "2026-08-08"
}
```

Exemplo de saida:

**HTTP STATUS 200**

```json
{
  "data": {
    "id": 1,
    "requester_name": "Paulo Matos",
    "requester": {
      "id": 2,
      "name": "Paulo Matos",
      "email": "paulo@example.com"
    },
    "destination": "Porto",
    "departure_date": "2026-08-01",
    "return_date": "2026-08-08",
    "status": "solicitado",
    "created_at": "2026-06-06T19:00:00.000000Z",
    "updated_at": "2026-06-06T19:10:00.000000Z"
  }
}
```

**HTTP STATUS 403** caso o usuario nao seja dono do pedido ou o pedido nao esteja mais `solicitado`.

**HTTP STATUS 422** caso o payload seja invalido.

## [ PATCH ] /api/v1/travel-orders/{id}/status

Atualiza o status de um pedido para `aprovado` ou `cancelado`.

Somente administradores podem executar esta operacao, e o administrador nao pode alterar o proprio pedido.

Exemplo de entrada:

```json
{
  "status": "aprovado"
}
```

Exemplo de saida:

**HTTP STATUS 200**

```json
{
  "data": {
    "id": 1,
    "requester_name": "Paulo Matos",
    "requester": {
      "id": 2,
      "name": "Paulo Matos",
      "email": "paulo@example.com"
    },
    "destination": "Lisbon",
    "departure_date": "2026-07-10",
    "return_date": "2026-07-20",
    "status": "aprovado",
    "created_at": "2026-06-06T19:00:00.000000Z",
    "updated_at": "2026-06-06T19:15:00.000000Z"
  }
}
```

**HTTP STATUS 403** caso o usuario nao seja administrador ou tente alterar o proprio pedido.

**HTTP STATUS 409** caso o pedido ja esteja `aprovado` ou `cancelado`.

**HTTP STATUS 422** caso o status enviado seja invalido.

# Observacoes sobre notificacoes

Quando um pedido e aprovado ou cancelado, a aplicacao dispara a notificacao `TravelOrderStatusChanged` para o solicitante.

Canais utilizados:

- `mail`
- `database`

No Docker, a fila usa Redis e o worker roda no servico `queue`.

# Observacoes sobre cache

A listagem de pedidos usa cache versionado por 60 segundos.

O cache considera:

- usuario autenticado ou visao de administrador
- filtros aplicados
- pagina atual
- quantidade por pagina
- versao de cache

Ao criar, editar, aprovar ou cancelar um pedido, a versao do cache e incrementada para evitar retorno de dados antigos.
