# Travel Orders API

Microsservico em Laravel para gerenciar pedidos de viagem corporativa. A API usa JWT, MySQL, cache Redis, filas para notificacoes e regras de autorizacao por usuario/admin.

Repositorio: `https://github.com/paulokmatos/travel`

## Sumario

- [Visao Geral](#visao-geral)
- [Stack](#stack)
- [Iniciar Do Zero Com Docker](#iniciar-do-zero-com-docker)
- [Iniciar Do Zero Localmente](#iniciar-do-zero-localmente)
- [Mini Documentacao Da API](#mini-documentacao-da-api)
- [Regras De Negocio](#regras-de-negocio)
- [Arquitetura Do Projeto](#arquitetura-do-projeto)
- [Banco De Dados](#banco-de-dados)
- [Cache, Filas E Notificacoes](#cache-filas-e-notificacoes)
- [Testes E Qualidade](#testes-e-qualidade)
- [Troubleshooting](#troubleshooting)

## Visao Geral

O sistema permite que usuarios criem e acompanhem pedidos de viagem corporativa. Cada pedido contem destino, data de ida, data de volta e status. Usuarios comuns gerenciam apenas seus proprios pedidos; administradores podem consultar todos e aprovar ou cancelar pedidos de outros usuarios.

Fluxo principal:

1. Um usuario se registra ou faz login.
2. O usuario cria um pedido de viagem.
3. Enquanto o pedido estiver `solicitado`, o dono pode editar destino e datas.
4. Um administrador aprova ou cancela o pedido.
5. O solicitante recebe notificacao por email e no banco.

## Stack

- PHP 8.3
- Laravel 13
- MySQL 8.4
- Redis 7 para cache e fila no Docker
- JWT com `php-open-source-saver/jwt-auth`
- PHPUnit 12
- Infection para testes de mutacao
- Laravel Pint para formatacao PHP
- Docker Compose com `app`, `queue`, `mysql`, `redis`, `mailpit`, `test` e `mutation`

## Iniciar Do Zero Com Docker

Este e o caminho recomendado para validar o projeto sem instalar PHP, MySQL ou Redis localmente.

### 1. Pre-requisitos

- Git
- Docker
- Docker Compose v2

### 2. Clonar O Repositorio

```bash
git clone git@github.com:paulokmatos/travel.git
cd travel
```

Tambem funciona via HTTPS:

```bash
git clone https://github.com/paulokmatos/travel.git
cd travel
```

### 3. Subir A Aplicacao

```bash
docker compose up --build -d
```

O container `app` espera o MySQL ficar saudavel e, por padrao no Compose, executa:

- `php artisan migrate --force`
- `php artisan db:seed --force`

### 4. Acessar Os Servicos

| Servico | URL / porta | Uso |
| --- | --- | --- |
| API | `http://localhost:8000/api/v1` | Endpoints REST |
| Health check | `http://localhost:8000/up` | Verificar app online |
| Mailpit | `http://localhost:8025` | UI para emails locais |
| MySQL | `localhost:3306` | Banco `travel` |
| Redis | `localhost:6379` | Cache e fila |

Credenciais do admin criado pelo seeder no Docker:

```text
email: admin@example.com
senha: password
```

### 5. Verificar Se Esta Tudo De Pe

```bash
docker compose ps
curl http://localhost:8000/up
```

### 6. Rodar Comandos Dentro Do Container

```bash
docker compose exec app php artisan route:list --path=api
docker compose exec app php artisan migrate:status
docker compose exec app php artisan queue:work redis --tries=3 --timeout=60
```

O servico `queue` ja roda o worker automaticamente. O comando acima e util apenas para debug manual.

### 7. Parar Ou Resetar O Ambiente

Parar containers mantendo o volume do banco:

```bash
docker compose down
```

Remover containers e zerar dados do MySQL:

```bash
docker compose down -v
```

## Iniciar Do Zero Localmente

Use este fluxo quando quiser rodar a aplicacao fora do Docker.

### 1. Pre-requisitos Locais

- PHP 8.3
- Composer 2
- MySQL 8 ou compativel
- Redis, se usar `CACHE_STORE=redis` e `QUEUE_CONNECTION=redis`
- Node.js e NPM, apenas se for executar Vite/build frontend

Extensoes PHP relevantes:

- `bcmath`
- `pcntl`
- `pdo_mysql`
- `zip`
- `redis`, quando usar Redis local

### 2. Instalar Dependencias

```bash
composer install
npm install
```

### 3. Criar E Configurar O `.env`

```bash
cp .env.example .env
php artisan key:generate
php artisan jwt:secret
```

Configure o banco no `.env`:

```env
APP_URL=http://localhost:8000

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

Para Redis local:

```env
CACHE_STORE=redis
QUEUE_CONNECTION=redis
REDIS_HOST=127.0.0.1
REDIS_PORT=6379
```

### 4. Criar Banco E Rodar Migrations

Crie o banco `travel` no MySQL e execute:

```bash
php artisan migrate --seed
```

O seeder cria um usuario administrador usando `ADMIN_NAME`, `ADMIN_EMAIL` e `ADMIN_PASSWORD`.

### 5. Rodar API E Worker

Terminal 1:

```bash
php artisan serve
```

Terminal 2:

```bash
php artisan queue:work --tries=3 --timeout=60
```

API local:

```text
http://localhost:8000/api/v1
```

### 6. Fluxo De Desenvolvimento Opcional

O script abaixo roda servidor, fila, logs e Vite juntos:

```bash
composer run dev
```

## Mini Documentacao Da API

Base URL:

```text
http://localhost:8000/api/v1
```

Todas as rotas de pedidos exigem:

```http
Authorization: Bearer <token>
```

As rotas ficam sob throttle global de `60` requisicoes por minuto. Login tem limite proprio de `10` requisicoes por minuto.

### Autenticacao

| Metodo | Rota | Auth | Descricao |
| --- | --- | --- | --- |
| POST | `/auth/register` | Nao | Cria usuario comum e retorna JWT |
| POST | `/auth/login` | Nao | Autentica e retorna JWT |
| GET | `/auth/me` | Sim | Retorna usuario autenticado |
| POST | `/auth/refresh` | Sim | Renova token |
| POST | `/auth/logout` | Sim | Invalida token |

#### Registrar Usuario

```bash
curl -X POST http://localhost:8000/api/v1/auth/register \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Paulo Matos",
    "email": "paulo@example.com",
    "password": "password123",
    "password_confirmation": "password123"
  }'
```

Resposta:

```json
{
  "access_token": "...",
  "token_type": "bearer",
  "expires_in": 3600
}
```

Registro publico sempre cria usuario comum. A criacao de admin acontece pelo seeder.

#### Login

```bash
curl -X POST http://localhost:8000/api/v1/auth/login \
  -H "Content-Type: application/json" \
  -d '{
    "email": "admin@example.com",
    "password": "password"
  }'
```

Guarde o valor de `access_token` para as proximas chamadas.

#### Usuario Autenticado

```bash
curl http://localhost:8000/api/v1/auth/me \
  -H "Authorization: Bearer <token>"
```

Resposta:

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

### Pedidos De Viagem

| Metodo | Rota | Auth | Descricao |
| --- | --- | --- | --- |
| GET | `/travel-orders` | Sim | Lista pedidos paginados |
| POST | `/travel-orders` | Sim | Cria pedido para o usuario autenticado |
| GET | `/travel-orders/{id}` | Sim | Consulta um pedido |
| PATCH/PUT | `/travel-orders/{id}` | Sim | Edita destino e datas |
| PATCH | `/travel-orders/{id}/status` | Sim/admin | Aprova ou cancela pedido |

#### Criar Pedido

```bash
curl -X POST http://localhost:8000/api/v1/travel-orders \
  -H "Authorization: Bearer <token>" \
  -H "Content-Type: application/json" \
  -d '{
    "destination": "Lisbon",
    "departure_date": "2026-07-10",
    "return_date": "2026-07-20"
  }'
```

Validacoes:

- `destination`: obrigatorio, string, 2 a 255 caracteres; espacos extras sao normalizados.
- `departure_date`: obrigatorio, formato `YYYY-MM-DD`.
- `return_date`: obrigatorio, formato `YYYY-MM-DD`, maior ou igual a `departure_date`.
- `requester_name`, `user_id` e `status` nao devem ser enviados; o backend define esses valores.

#### Listar Pedidos

```bash
curl "http://localhost:8000/api/v1/travel-orders?status=aprovado&destination=Lis&travel_from=2026-07-01&travel_to=2026-07-31" \
  -H "Authorization: Bearer <token>"
```

Usuarios comuns veem apenas os proprios pedidos. Administradores veem todos.

Filtros aceitos:

| Filtro | Formato | Descricao |
| --- | --- | --- |
| `status` | `solicitado`, `aprovado`, `cancelado` | Filtra por status |
| `destination` | texto | Busca parcial por destino |
| `created_from` | `YYYY-MM-DD` | Data inicial de criacao |
| `created_to` | `YYYY-MM-DD` | Data final de criacao |
| `travel_from` | `YYYY-MM-DD` | Data inicial da viagem |
| `travel_to` | `YYYY-MM-DD` | Data final da viagem |
| `per_page` | 1 a 100 | Tamanho da pagina |
| `page` | a partir de 1 | Numero da pagina |

Intervalos invalidos retornam `422`, por exemplo `created_to` antes de `created_from`.

#### Consultar Pedido

```bash
curl http://localhost:8000/api/v1/travel-orders/1 \
  -H "Authorization: Bearer <token>"
```

Usuario comum so acessa pedidos proprios. Admin acessa qualquer pedido.

#### Editar Pedido

```bash
curl -X PATCH http://localhost:8000/api/v1/travel-orders/1 \
  -H "Authorization: Bearer <token>" \
  -H "Content-Type: application/json" \
  -d '{
    "destination": "Porto",
    "departure_date": "2026-08-01",
    "return_date": "2026-08-08"
  }'
```

Regras:

- Somente o dono pode editar.
- O pedido precisa estar com status `solicitado`.
- O payload e parcial, mas deve conter pelo menos um dos campos: `destination`, `departure_date`, `return_date`.
- Datas continuam respeitando o intervalo valido. Em atualizacao parcial, a regra compara com a data ja salva.

#### Aprovar Ou Cancelar Pedido

```bash
curl -X PATCH http://localhost:8000/api/v1/travel-orders/1/status \
  -H "Authorization: Bearer <admin-token>" \
  -H "Content-Type: application/json" \
  -d '{
    "status": "aprovado"
  }'
```

Valores aceitos:

- `aprovado`
- `cancelado`

Regras:

- Apenas admin pode alterar status.
- O admin nao pode alterar o status de um pedido criado por ele mesmo.
- Somente pedidos `solicitado` podem virar `aprovado` ou `cancelado`.
- Tentar cancelar pedido ja aprovado retorna `409 Conflict`.
- Tentar alterar um pedido `cancelado` ou `aprovado` tambem retorna `409 Conflict`.

### Formato De Resposta

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

Listagens seguem o formato padrao de Resource Collection do Laravel:

```json
{
  "data": [],
  "links": {},
  "meta": {}
}
```

### Erros Comuns

| Status | Quando ocorre |
| --- | --- |
| 401 | Token ausente, invalido ou credenciais invalidas |
| 403 | Usuario autenticado sem permissao para o recurso |
| 404 | Pedido inexistente |
| 409 | Transicao de status invalida |
| 422 | Payload ou filtro invalido |
| 429 | Limite de requisicoes excedido |

## Regras De Negocio

- Cada pedido pertence a um usuario autenticado.
- Usuarios comuns podem criar, consultar, listar e editar apenas seus proprios pedidos.
- Administradores podem consultar e listar todos os pedidos.
- Apenas administradores podem aprovar ou cancelar pedidos.
- O proprio solicitante nunca pode aprovar ou cancelar seu pedido, mesmo sendo admin.
- Pedidos nascem sempre com status `solicitado`.
- Transicoes validas:
  - `solicitado -> aprovado`
  - `solicitado -> cancelado`
- Estados terminais:
  - `aprovado`
  - `cancelado`
- Estados terminais nao podem mudar de status.
- Sempre que um pedido e aprovado ou cancelado, o solicitante recebe notificacao pelos canais `mail` e `database`.

## Arquitetura Do Projeto

Principais responsabilidades:

| Camada | Arquivo/pasta | Responsabilidade |
| --- | --- | --- |
| Rotas | `routes/api.php` | Versionamento `/api/v1`, throttle, auth JWT |
| Controllers | `app/Http/Controllers/Api/V1` | Orquestrar requests, actions, resources e policies |
| Form Requests | `app/Http/Requests/Api/V1` | Autorizacao e validacao de entrada |
| Resources | `app/Http/Resources` | Formato JSON das respostas |
| Models | `app/Models` | Entidades Eloquent e relacionamentos |
| Policies | `app/Policies` | Regras de acesso por dono/admin |
| Actions | `app/Actions` | Casos de uso: criar, editar e alterar status |
| Queries | `app/Queries` | Consulta paginada com filtros |
| Services | `app/Services` | Cache versionado de listagens |
| Support | `app/Support` | Regras puras de transicao de status |
| Notifications | `app/Notifications` | Notificacao enfileirada por mail e database |

Estrutura resumida:

```text
app/
  Actions/
  Enums/TravelOrderStatus.php
  Http/Controllers/Api/V1/
  Http/Requests/Api/V1/
  Http/Resources/TravelOrderResource.php
  Models/TravelOrder.php
  Notifications/TravelOrderStatusChanged.php
  Policies/TravelOrderPolicy.php
  Queries/TravelOrderQuery.php
  Rules/ValidTravelDateRange.php
  Services/TravelOrderCache.php
  Support/TravelOrderStatusTransition.php
database/
  factories/
  migrations/
  seeders/
routes/api.php
tests/Feature/TravelOrderApiTest.php
tests/Unit/
```

## Banco De Dados

Tabelas principais:

| Tabela | Descricao |
| --- | --- |
| `users` | Usuarios autenticaveis; possui `is_admin` |
| `travel_orders` | Pedidos de viagem ligados a `users` |
| `notifications` | Notificacoes persistidas do Laravel |
| `jobs` / `failed_jobs` | Fila database, quando usada |

Campos de `travel_orders`:

| Campo | Tipo | Observacao |
| --- | --- | --- |
| `id` | bigint | Identificador do pedido |
| `user_id` | foreign id | Solicitante |
| `destination` | string | Destino |
| `departure_date` | date | Data de ida |
| `return_date` | date | Data de volta |
| `status` | string | `solicitado`, `aprovado` ou `cancelado` |
| `created_at` / `updated_at` | timestamps | Controle temporal |

Indices relevantes:

- `user_id, status`
- `created_at, status`
- `departure_date, return_date`
- `destination`

## Cache, Filas E Notificacoes

Cache:

- A listagem de pedidos usa cache por 60 segundos.
- A chave considera usuario/admin, filtros, pagina, `per_page` e versao.
- Mutacoes em pedidos incrementam versoes de cache para invalidar listagens afetadas.
- Usuarios comuns usam versao por usuario; admins usam versao global.

Fila:

- No Docker, `QUEUE_CONNECTION=redis`.
- O servico `queue` executa `php artisan queue:work --tries=3 --timeout=60`.
- Localmente, e possivel usar `database` ou `redis`.

Notificacoes:

- A notificacao `TravelOrderStatusChanged` implementa `ShouldQueue`.
- Os canais sao `mail` e `database`.
- O disparo acontece depois do commit da transacao.
- No Compose atual, `MAIL_MAILER=log`. Para ver emails no Mailpit, configure `MAIL_MAILER=smtp`, `MAIL_HOST=mailpit` e `MAIL_PORT=1025`.

## Testes E Qualidade

### Testes Locais

Suite completa:

```bash
php artisan test --compact
```

Feature tests:

```bash
composer test:feature
```

Unit tests:

```bash
composer test:unit
```

Teste focado da API:

```bash
php artisan test --compact tests/Feature/TravelOrderApiTest.php
```

### Testes No Docker

```bash
docker compose --profile test run --rm test
```

### Mutation Testing

Local com Xdebug coverage:

```bash
composer test:mutation
```

Gate de CI/local estrito com Xdebug:

```bash
composer test:mutation:ci
```

Docker com PCOV:

```bash
docker compose --profile mutation run --rm mutation
```

Configuracao:

- Arquivo: `infection.json5`
- Thresholds do gate: MSI minimo `70%` e Covered MSI minimo `80%`
- Escopo principal: policies, rules, services e support

### Formatacao

```bash
vendor/bin/pint --dirty --format agent
```

### Cobertura Relevante

Feature tests cobrem:

- Registro/login JWT
- Bloqueio de rotas sem token
- Criacao de pedidos
- Validacao de datas
- Listagem por dono e por admin
- Consulta restrita por permissao
- Filtros por status, destino, periodo de criacao e periodo de viagem
- Edicao apenas de pedidos `solicitado`
- Bloqueio de status por usuario comum
- Bloqueio de status pelo proprio solicitante admin
- Aprovacao/cancelamento por admin
- Notificacao de alteracao de status
- Bloqueio de transicoes terminais
- Invalidacao de cache apos mutacoes

Unit tests cobrem:

- Policy de dono/admin
- Transicoes de status
- Regra de intervalo de datas
- Cache versionado de listagens

## Troubleshooting

### Token JWT Invalido Ou Ausente

Confira se a chamada envia:

```http
Authorization: Bearer <token>
```

Se estiver rodando localmente, garanta que `JWT_SECRET` existe:

```bash
php artisan jwt:secret
```

### Banco Nao Conecta No Docker

Verifique a saude dos containers:

```bash
docker compose ps
docker compose logs mysql
docker compose logs app
```

Para recriar tudo do zero:

```bash
docker compose down -v
docker compose up --build -d
```

### Migrations Nao Rodaram

No Docker, confira se estas variaveis estao ativas no servico `app`:

```yaml
APP_RUN_MIGRATIONS: "true"
APP_RUN_SEEDERS: "true"
```

Rode manualmente se necessario:

```bash
docker compose exec app php artisan migrate --seed
```

### Notificacoes Nao Processam

Confira o worker:

```bash
docker compose logs queue
```

Rode um worker manual para debug:

```bash
docker compose exec app php artisan queue:work redis --tries=3 --timeout=60
```

### Portas Ocupadas

Portas usadas pelo Compose:

- `8000`: API
- `3306`: MySQL
- `6379`: Redis
- `8025`: Mailpit

Altere o mapeamento em `docker-compose.yml` se alguma ja estiver em uso.
