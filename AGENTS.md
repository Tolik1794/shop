# Project instructions

## Project overview

- Project runs on Docker.
- Project uses Symfony 7.4. Verify exact installed Symfony component versions from `composer.json` and `composer.lock` before relying on version-specific APIs.
- This is a Symfony shop/admin project with products, warehouses, stock, orders, forms, admin UI, AJAX endpoints, Twig templates, Doctrine entities, fixtures, and frontend assets built with Encore.

## Required workflow

For any code change:

1. Inspect the relevant files first.
2. Explain the likely root cause or implementation target.
3. Propose a small, isolated plan before editing.
4. Edit only the required files.
5. Preserve existing behavior unless the task explicitly asks to change it.
6. After editing, show a summary and list changed files.
7. Suggest verification commands.

Do not rewrite large parts of the project unless explicitly asked.
Do not mix unrelated refactoring with the requested fix.
Do not reformat unrelated files.

## Protected files and directories

Do not edit:

- `.env`
- `.env.local`
- `.env.*.local`
- production configs
- `config/packages/prod/`
- secrets
- keys
- dumps
- `vendor/`
- `node_modules/`
- `var/cache/`
- `var/log/`
- `public/build/`
- already committed or already executed Doctrine migrations

Do not change Composer dependencies unless explicitly requested.
Do not run `composer update` unless explicitly requested.
Do not change lock files unless dependency changes were explicitly requested.

## Docker rules

- Always run project commands through Docker.
- If a required tool is missing inside the container, do not install it silently.
- Explain why it is needed and suggest adding it to the Docker image or project dependencies if it will be used repeatedly.
- After editing styles or JavaScript, run or suggest running `yarn encore dev` inside Docker to recompile assets.
- Do not run destructive Docker, database, or filesystem commands.

## General coding rules

- Prefer small, isolated changes.
- Follow the existing code style in the modified file.
- Do not apply modern Symfony patterns blindly to legacy or older parts of the project.
- Do not invent or assume existing classes, methods, services, routes, environment variables, database fields, translation keys, selectors, or config values.
- New classes, services, routes, config values, or database fields may be added only when the task requires it and after explaining why.
- Do not change public APIs unless explicitly requested.
- Do not remove comments that explain business logic.
- Do not rename routes, services, form field names, JS selectors, data attributes, translation keys, templates, or database columns without explicit approval.
- Prefer typed properties and strict types if the file already follows this style.
- Use constructor dependency injection where appropriate.
- Prefer services over static helpers in the modern Symfony application.

## Architecture direction

This project should follow a practical layered Symfony architecture:

- Controllers handle HTTP only.
- Forms handle input structure and basic validation.
- Managers handle business operations on entities.
- UseCase/Handler classes may be used for complex business flows.
- Services handle reusable technical/application logic.
- Repositories contain query logic only.
- Entities contain simple domain behavior and state.
- Twig renders data and should not contain business logic.

For complex flows such as order status changes, procurement, stock corrections, stock movements, and currency calculations, prefer a dedicated Manager or UseCase/Handler instead of putting logic into controllers, repositories, forms, or Twig.

## Backend architecture rules

- Keep controllers thin.
- Controllers should orchestrate request handling only.
- Put business logic into managers or services.
- Managers should handle business operations on entities: create, update, delete, change status, save files together with an entity, or perform a business action.
- Services should handle reusable technical or application logic: filters, uploads, access checks, transformations, integrations.
- Repositories should contain query logic, not business decisions.
- Use Doctrine repositories and query builders consistently with the existing project style.
- Preserve existing validation and form behavior.
- Forms should preserve existing field names, options, constraints, and data mapping unless explicitly changed.
- Twig templates should not contain business logic.
- Use Symfony conventions for configuration, routing, services, and events.

## Doctrine rules

- Inspect existing entity mappings before changing queries or entities.
- Do not create migrations automatically.
- Never edit existing committed or already executed Doctrine migrations.
- If a schema change is required, propose a new migration and explain the data-safety impact.
- Do not run `doctrine:migrations:migrate` without explicit approval.
- Prefer `doctrine:migrations:migrate --dry-run` when verification is needed.
- Do not assume nullable or non-nullable fields without checking mapping or schema.
- Avoid N+1 queries.
- Use joins carefully and only when needed.
- Do not change fetch modes globally unless explicitly requested.
- Do not add eager loading without checking performance impact.
- If optimizing performance, explain the tradeoff between fewer queries and heavier joins.
- Preserve historical business data.

## Business data rules

- Do not physically delete business-critical history.
- Orders, payments, procurement documents, stock movements, posted documents, and exchange rates used in documents should preserve historical data.
- Prefer statuses, reversals, corrections, inactive flags, or soft delete where appropriate.
- For orders and procurement items, preserve price/name/currency snapshots where applicable.
- Do not calculate historical document totals from current product prices.

## Admin and AJAX rules

- Before changing an admin AJAX endpoint, inspect the related controller, route, JavaScript caller, Twig template, and CSS if relevant.
- Preserve existing response shape unless explicitly asked to change it.
- Do not change JSON keys, status codes, HTML fragment structure, or selector-dependent markup without approval.
- Be careful with AJAX race conditions, aborting previous requests, loading indicators, debounced input, Select2, checkbox-triggered searches, and repeated requests.
- Preserve existing DOM selectors unless a change is required.

## JavaScript rules

- Check the existing JavaScript style before changing code.
- Avoid introducing modern JS syntax if the surrounding code is old and may not be transpiled.
- Do not change global event handlers unless required.
- Preserve existing DOM selectors, data attributes, and event names unless explicitly approved.
- If changing UI behavior, check related templates and CSS.

## CSS / UI rules

- Preserve existing layout and class names where possible.
- Do not introduce large CSS rewrites for small UI fixes.
- Be careful with Select2 styling and browser-specific issues.
- Do not rely on unsupported CSS features if the target browsers may not support them.
- When adding styles, scope them narrowly.
- Do not edit generated assets in `public/build/`.

## Security rules

- Do not weaken access control, voters, roles, CSRF protection, validation, or authentication behavior.
- When adding admin actions, check permissions and CSRF protection where applicable.
- Do not expose internal paths, secrets, stack traces, debug data, or sensitive data to users.
- Do not log sensitive data.

## Git rules

- Do not create commits unless explicitly requested.
- Do not amend commits unless explicitly requested.
- Do not push changes unless explicitly requested.
- Before large changes, show a plan.
- After changes, summarize modified files and the reason for each change.

## Verification commands

When relevant, run or suggest verification commands through Docker:

- `php bin/console lint:container`
- `php bin/console lint:twig templates`
- `php bin/console doctrine:schema:validate`
- `php bin/console doctrine:migrations:migrate --dry-run`
- `vendor/bin/phpunit`
- `yarn encore dev`

Do not run database-changing commands without explicit approval.

If the exact Docker service name is unclear, inspect `docker-compose.yml` first.

## Communication style

- Be concise but specific.
- Explain risks when changing legacy or sensitive code.
- If there are several possible solutions, recommend the safest one.
- Prefer ready-to-paste code when asked.
- For bugs, identify the likely root cause before changing code.
- For performance issues, include both the query-count impact and the memory/complexity tradeoff.

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