# Travel Orders API

Microsservico Laravel para gerenciar pedidos de viagem corporativa com API REST, autenticacao JWT, escopo por usuario, aprovacao administrativa, cancelamento com regra de negocio e notificacoes de status.

## Stack

- PHP 8.3
- Laravel 13
- MySQL 8.4
- PHPUnit 12
- JWT: `php-open-source-saver/jwt-auth`
- Docker Compose com `app`, `queue`, `mysql`, `mailpit` e profile de testes

## Regras De Negocio

- Cada pedido pertence ao usuario autenticado.
- Usuarios comuns podem criar, consultar, listar e editar apenas os proprios pedidos.
- Administradores podem consultar e listar todos os pedidos.
- Apenas administradores podem alterar status.
- Um administrador nao pode alterar o status de um pedido criado por ele mesmo.
- O dono do pedido nao pode aprovar ou cancelar o proprio pedido.
- Pedidos nascem com status `solicitado`.
- Transicoes validas:
  - `solicitado -> aprovado`
  - `solicitado -> cancelado`
- Pedidos `aprovado` ou `cancelado` nao podem mudar de status; a API retorna `409 Conflict`.
- Quando um pedido e aprovado ou cancelado, uma notificacao e enviada ao solicitante pelos canais `mail` e `database`.

## Executar Com Docker

Suba todos os servicos:

```bash
docker compose up --build
```

A API ficara disponivel em:

```text
http://localhost:8000/api/v1
```

Servicos:

- `app`: roda Laravel em `http://localhost:8000`
- `queue`: processa notificacoes enfileiradas
- `mysql`: banco relacional da aplicacao
- `mailpit`: caixa de email local em `http://localhost:8025`

O container `app` espera o MySQL, roda migrations e seeders automaticamente quando:

```yaml
APP_RUN_MIGRATIONS: "true"
APP_RUN_SEEDERS: "true"
```

Credenciais iniciais do admin no Docker:

```text
email: admin@example.com
senha: password
```

Para executar comandos Artisan dentro do container:

```bash
docker compose exec app php artisan route:list
docker compose exec app php artisan migrate
docker compose exec app php artisan queue:work
```

Para rodar os testes pelo Docker:

```bash
docker compose run --rm test
```

## Executar Localmente

Instale dependencias:

```bash
composer install
npm install
```

Configure ambiente:

```bash
cp .env.example .env
php artisan key:generate
php artisan jwt:secret
```

Configure MySQL no `.env` e rode:

```bash
php artisan migrate
php artisan db:seed
php artisan serve
php artisan queue:work
```

## Variaveis Importantes

```env
APP_URL=http://localhost:8000
JWT_SECRET=
JWT_TTL=60

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=travel
DB_USERNAME=root
DB_PASSWORD=

QUEUE_CONNECTION=database
MAIL_MAILER=log

ADMIN_NAME="Admin User"
ADMIN_EMAIL=admin@example.com
ADMIN_PASSWORD=password
```

## Autenticacao

Todas as rotas de pedidos exigem JWT Bearer token.

### Registrar Usuario

```http
POST /api/v1/auth/register
```

Payload:

```json
{
  "name": "Paulo Matos",
  "email": "paulo@example.com",
  "password": "password123",
  "password_confirmation": "password123"
}
```

Resposta:

```json
{
  "access_token": "...",
  "token_type": "bearer",
  "expires_in": 3600
}
```

### Login

```http
POST /api/v1/auth/login
```

Payload:

```json
{
  "email": "admin@example.com",
  "password": "password"
}
```

Use o token retornado nas proximas chamadas:

```bash
curl -H "Authorization: Bearer TOKEN" http://localhost:8000/api/v1/auth/me
```

### Rotas De Auth

| Metodo | Rota | Descricao |
| --- | --- | --- |
| POST | `/api/v1/auth/register` | Cria usuario comum |
| POST | `/api/v1/auth/login` | Autentica e retorna JWT |
| GET | `/api/v1/auth/me` | Retorna usuario autenticado |
| POST | `/api/v1/auth/refresh` | Renova token |
| POST | `/api/v1/auth/logout` | Invalida token |

## Pedidos De Viagem

### Criar Pedido

```http
POST /api/v1/travel-orders
```

Payload:

```json
{
  "destination": "Lisbon",
  "departure_date": "2026-07-10",
  "return_date": "2026-07-20"
}
```

Observacoes:

- `requester_name` vem do usuario autenticado.
- `status` inicial sempre sera `solicitado`.
- `return_date` deve ser maior ou igual a `departure_date`.

### Consultar Pedido

```http
GET /api/v1/travel-orders/{id}
```

Usuarios comuns so acessam pedidos proprios. Admins acessam qualquer pedido.

### Listar Pedidos

```http
GET /api/v1/travel-orders
```

Filtros opcionais:

| Filtro | Formato | Descricao |
| --- | --- | --- |
| `status` | `solicitado`, `aprovado`, `cancelado` | Filtra por status |
| `destination` | texto | Busca parcial por destino |
| `created_from` | `YYYY-MM-DD` | Data inicial de criacao |
| `created_to` | `YYYY-MM-DD` | Data final de criacao |
| `travel_from` | `YYYY-MM-DD` | Data inicial da viagem |
| `travel_to` | `YYYY-MM-DD` | Data final da viagem |
| `per_page` | inteiro de 1 a 100 | Tamanho da pagina |

Exemplo:

```bash
curl -H "Authorization: Bearer TOKEN" \
  "http://localhost:8000/api/v1/travel-orders?status=aprovado&destination=Lis&travel_from=2026-07-01&travel_to=2026-07-31"
```

### Editar Pedido

```http
PATCH /api/v1/travel-orders/{id}
```

Payload parcial:

```json
{
  "destination": "Porto",
  "departure_date": "2026-08-01",
  "return_date": "2026-08-08"
}
```

Somente o dono pode editar e apenas enquanto o status for `solicitado`.

### Atualizar Status

```http
PATCH /api/v1/travel-orders/{id}/status
```

Payload:

```json
{
  "status": "aprovado"
}
```

Valores aceitos:

- `aprovado`
- `cancelado`

Somente admin pode alterar status, e apenas pedidos `solicitado` podem ser alterados.

## Formato Das Respostas

Pedido:

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

Erros comuns:

| Status | Quando ocorre |
| --- | --- |
| 401 | Token ausente ou invalido |
| 403 | Usuario sem permissao |
| 409 | Tentativa de alterar pedido aprovado/cancelado |
| 422 | Payload invalido |

## Banco De Dados

Tabelas principais:

- `users`: usuarios autenticaveis, com `is_admin`.
- `travel_orders`: pedidos de viagem, relacionados a `users`.
- `notifications`: notificacoes database do Laravel.
- `jobs`: fila database para notificacoes.

Indices relevantes em `travel_orders`:

- `user_id + status`
- `created_at + status`
- `departure_date + return_date`
- `destination`

## Testes

Rodar a suite completa:

```bash
php artisan test --compact
```

Rodar apenas os testes da API:

```bash
php artisan test --compact tests/Feature/TravelOrderApiTest.php
```

Formatar PHP com Pint:

```bash
vendor/bin/pint --dirty --format agent
```

Cobertura atual dos testes feature:

- registro/login JWT
- protecao de rotas sem token
- criacao de pedido
- validacao de datas
- listagem por dono e admin
- consulta restrita por permissao
- filtros por status, destino, criacao e viagem
- edicao apenas de pedidos solicitados
- bloqueio de status por usuario comum e pelo proprio solicitante admin
- aprovacao/cancelamento por admin
- notificacao de alteracao de status
- bloqueio de transicoes em estados terminais

## Estrutura Principal

```text
app/
  Actions/UpdateTravelOrderStatus.php
  Enums/TravelOrderStatus.php
  Http/Controllers/Api/V1/
  Http/Requests/Api/V1/
  Http/Resources/TravelOrderResource.php
  Models/TravelOrder.php
  Notifications/TravelOrderStatusChanged.php
  Policies/TravelOrderPolicy.php
routes/api.php
database/migrations/
tests/Feature/TravelOrderApiTest.php
```

## Publicacao

Repositorio GitHub:

```text
https://github.com/paulokmatos/travel
```
