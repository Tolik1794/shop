# Project instructions

- Project run on docker.
- Project uses Symfony 6.4
- Do not change database migrations that were already executed.
- Do not rename routes, services, form field names, JS selectors, data attributes, or translation keys without explicit approval.
- Prefer small, isolated changes.
- Before editing, explain the plan.
- After editing, show a summary and list changed files.
- For PHP code, follow existing project style.
- Do not run destructive commands.
- Do not run composer update unless explicitly requested.
- Do not edit .env, .env.local, production configs, secrets, keys, dumps, vendor, node_modules, var/cache.

## General rules

- Do not rewrite large parts of the project unless explicitly asked.
- Prefer small, isolated changes.
- Preserve existing behavior unless the task explicitly asks to change it.
- Before modifying code, inspect the related classes, services, templates, routes, forms, and JavaScript handlers.
- Do not invent classes, methods, services, routes, environment variables, or database fields.
- If a dependency, method, service, route, column, or config value is not present in the project, ask before using it or add a clear note.
- Follow the existing code style in the modified file.
- Do not apply modern Symfony patterns blindly to the legacy Symfony 1.4 part.
- Do not mix unrelated refactoring with the requested fix.
- Do not change public APIs unless explicitly requested.
- Do not remove comments that explain business logic.
- Do not rename database columns, services, routes, form fields, or templates unless explicitly requested.
- Do not change composer dependencies unless explicitly requested.
- Use constructor dependency injection where appropriate.
- Prefer services over static helpers in the modern Symfony application.
- Prefer typed properties and strict types if the file already follows this style.
- Keep controllers thin.
- Put business logic into services.
- Use Doctrine repositories/query builders consistently with the existing project style.
- Preserve existing validation and form behavior.
- Use Symfony conventions for configuration, routing, services, and events.
- When working with Twig, preserve existing block structure and variable names.

## Doctrine rules

- Avoid N+1 queries.
- Inspect existing entity mappings before changing queries.
- Use joins carefully and only when needed.
- Do not change fetch modes globally unless explicitly requested.
- Do not add eager loading without checking performance impact.
- Do not create migrations automatically.
- Do not assume nullable/non-nullable fields without checking mapping or schema.
- If optimizing performance, explain the tradeoff between fewer queries and heavier joins.

## JavaScript rules

- Check the existing JavaScript style before changing code.
- Avoid introducing modern JS syntax if the surrounding code is old and may not be transpiled.
- Do not change global event handlers unless required.
- Be careful with AJAX race conditions, aborting previous requests, loading indicators, debounced input, and checkbox-triggered searches.
- Preserve existing DOM selectors unless a change is required.
- If changing UI behavior, check related templates and CSS.

## CSS / UI rules

- Preserve existing layout and class names where possible.
- Do not introduce large CSS rewrites for small UI fixes.
- Be careful with Select2 styling and browser-specific issues.
- Do not rely on unsupported CSS features if the target browsers may not support them.
- When adding styles, scope them narrowly.

## Git rules

- Do not create commits unless explicitly requested.
- Do not amend commits unless explicitly requested.
- Do not push changes unless explicitly requested.
- Before large changes, show a plan.
- After changes, summarize modified files and the reason for each change.

## Communication style

- Be concise but specific.
- Explain risks when changing legacy code.
- If there are several possible solutions, recommend the safest one.
- Prefer ready-to-paste code when asked.
- For bugs, identify the likely root cause before changing code.
- For performance issues, include both the query-count impact and the memory/complexity tradeoff.

## Backend
Основний backend у src:

- src/Controller/ - HTTP-контролери.
- src/Controller/Admin/ - адмінська частина, маршрути переважно під /admin.
- src/Entity/ - Doctrine entities.
- src/Repository/ - Doctrine repositories і query logic.
- src/Form/ - Symfony forms, окремо Admin/Type, Admin/FilterType, form extensions.
- src/Manager/ - відповідає за прикладні операції над сутностями: створити, оновити, видалити, змінити статус, зберегти зображення разом із сутністю, виконати бізнес-дію.
- src/Service/ - прикладні сервіси: фільтри, upload, доступ.
- src/Security/ - voters/email verifier.
- src/Menu/ - KnpMenu builders/voters.
- src/Twig/ - Twig extensions.
- src/EventSubscriber/ - request subscriber.
- migrations/ - Doctrine migrations, їх не чіпати, якщо вони вже виконані/закомічені.
- tests/ - PHPUnit/controller тести.
- docker/ - docker configs
- docker-compose.yml - docker-compose.

## Frontend
Frontend розташований у assets і Twig:

- assets/admin/app.js - entrypoint admin-app.
- assets/main/app.js - entrypoint app.
- assets/bootstrap.js, assets/controllers/, assets/controllers.json - Stimulus.
- assets/theme/admin_kit/ - AdminKit тема: SCSS, JS modules, images.
- assets/js/ - додаткові JS файли, зокрема FontAwesome/Select2.
- templates/ - Twig views.
- templates/admin/ - адмінські сторінки, layout, components.
- templates/admin/_components/ - повторно використовувані Twig-компоненти.
- public/images/ - публічні зображення.
- public/index.php - Symfony front controller.
- public/build/ генерується Encore, але зараз у списку файлів не видно як джерело для ручного редагування.

Never edit:
- vendor/
- node_modules/
- var/cache/
- var/log/
- .env
- .env.local
- config/packages/prod/
- migrations/ already committed
