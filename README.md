# Shop

Внутрішня система для керування магазином, каталогом, замовленнями, закупівлями, складом і пов’язаними бізнес-процесами.

Проєкт розвивається поетапно: поточна реалізація вже містить базові довідники, каталог, роботу із замовленнями, складські дані та основу для подальшого розвитку документів, історії змін і статусних workflow.

## Технології

- PHP `>= 8.4`
- Symfony `7.4`
- Doctrine ORM
- PostgreSQL `16`
- Redis `7`
- Twig
- Webpack Encore / Stimulus
- Docker Compose

## Локальний запуск

Проєкт налаштований на роботу через Docker.

```bash
docker compose up -d --build
```

Після запуску застосунок доступний за адресою:

```text
http://localhost:8088
```

Порт можна змінити через змінну `SHOP_HTTP_PORT`.

### Корисні команди

Перевірити Symfony container:

```bash
docker compose exec php php bin/console lint:container
```

Запустити тести:

```bash
docker compose exec php vendor/bin/phpunit
```

Запустити конкретний тестовий файл:

```bash
docker compose exec php vendor/bin/phpunit tests/Manager/OrderManagerTest.php
```

Збірка frontend assets виконується через наявні npm/yarn scripts:

```bash
yarn dev
yarn watch
yarn build
```

## Структура проєкту

## Backend structure

Main backend code is in `src/`:

- `src/Controller/` - HTTP controllers.
- `src/Controller/Admin/` - admin area, routes usually under `/admin`.
- `src/Controller/Api/Admin/` - admin AJAX/API endpoints, routes usually under `/api/admin`.
- `src/Entity/` - Doctrine entities.
- `src/Dto/` - DTO classes.
- `src/Repository/` - Doctrine repositories and query logic.
- `src/Form/` - Symfony forms, including Admin/Type, Admin/FilterType, and form extensions.
- `src/Manager/` - business operations on entities.
- `src/Service/` - application services: filters, upload, access, utility logic.
- `src/Security/` - voters, email verifier, security-related classes.
- `src/Menu/` - KnpMenu builders and voters.
- `src/Twig/` - Twig extensions.
- `src/EventSubscriber/` - event subscribers.
- `src/DataFixtures/` - Doctrine fixtures.
- `src/Enum/` - enum classes.
- `src/Validator/` - Symfony validators and constraints.
- `migrations/` - Doctrine migrations. Do not edit committed or already executed migrations.
- `tests/` - PHPUnit and controller tests.
- `docker/` - Docker configs.
- `docker-compose.yml` - Docker Compose configuration.

## Frontend structure

Frontend is located in `assets/` and Twig templates:

- `assets/admin/app.js` - admin-app entrypoint.
- `assets/main/app.js` - main app entrypoint.
- `assets/bootstrap.js` - Stimulus bootstrap.
- `assets/controllers/` - Stimulus controllers.
- `assets/controllers.json` - Stimulus controller config.
- `assets/theme/admin_kit/` - AdminKit theme: SCSS, JS modules, images.
- `assets/js/` - additional JavaScript files, including FontAwesome/Select2-related code.
- `templates/` - Twig views.
- `templates/admin/` - admin pages, layout, components.
- `templates/admin/_components/` - reusable Twig components.
- `public/images/` - public images.
- `public/index.php` - Symfony front controller.
- `public/build/` - generated Encore output. Do not edit manually.

## Документація та UML

### Текстова документація

- `docs/services/universal-status-transition-service.md` — повний опис універсального сервісу переходів статусів.

### UML

- `src/Uml/database/Database.puml` — модель бази даних.
- `src/Uml/services/` — архітектура окремих сервісів.
- `src/Uml/features/` — місце для майбутніх описів окремого функціоналу.

## Універсальний сервіс переходів статусів

Для сутностей, у яких статус є частиною бізнес-процесу, у проєкті використовується окремий модуль:

- `src/Workflow/`

Його мета — не дозволяти розносити бізнес-логіку зміни статусів по контролерах і менеджерах, а описувати переходи явно через окремі workflow definitions.

Поточна реалізація включає:

- `StatusTransitionService` — стабільне ядро переходів;
- `WorkflowRegistry` — пошук workflow для сутності;
- `TransitionDefinition` — опис дозволеного бізнес-переходу;
- guards — перевірки доступності переходу;
- actions — побічну логіку до або після зміни статусу;
- `OrderHistoryRecorder` — запис повної часової лінії замовлення;
- доменну подію після успішного переходу.

Зараз модуль уже використовується для `Order`:

- `confirm`: `draft -> confirmed`
- `return_to_draft`: `confirmed -> draft`
- `cancel`: будь-який нескасований статус `-> canceled`

Головне правило модуля:

> бізнес-статуси не слід змінювати напряму, якщо для дії існує workflow transition.

Детальніше:

- короткий огляд модуля: `src/Workflow/README.md`
- повна документація: `docs/services/universal-status-transition-service.md`
- UML-схема: `src/Uml/services/11.1_Universal_Status_Transition_Service.puml`

## Принципи розробки

- Зміни мають бути невеликими й ізольованими.
- Бізнес-логіка повинна жити в сервісах і менеджерах, а не в контролерах.
- Новий функціонал не повинен ламати наявні публічні контракти без окремого рішення.
- Для складних бізнес-процесів спочатку варто оновити UML/документацію, а вже потім реалізацію.
- Для нових workflow краще додавати окремі definitions, guards, actions і recorders, не змінюючи ядро сервісу.
