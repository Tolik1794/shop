# Workflow module

Цей каталог містить універсальний механізм переходів статусів для сутностей із бізнес-процесами.

Повний опис архітектури, правил використання та прикладів знаходиться тут:

- `docs/services/universal-status-transition-service.md`
- `src/Uml/services/11.1_Universal_Status_Transition_Service.puml`

## Як читати модуль

Якщо потрібно швидко зрозуміти потік виконання, дивитися файли в такому порядку:

1. `StatusTransitionService.php` — основний orchestration flow.
2. `WorkflowRegistry.php` — як сервіс знаходить workflow для сутності.
3. `WorkflowDefinitionInterface.php` — контракт окремого workflow.
4. `TransitionDefinition.php` — опис одного дозволеного переходу.
5. `Definition/OrderWorkflowDefinition.php` — поточний реальний приклад.

## Структура каталогу

- `Action/` — побічні бізнес-дії до або після зміни статусу.
- `Definition/` — декларативні описи workflow для конкретних сутностей.
- `Event/` — доменні події після успішних переходів.
- `Exception/` — помилки workflow-рівня.
- `Guard/` — перевірки, які дозволяють або блокують перехід.
- `History/` — реалізації запису історії переходів.

## Базові ролі

- `WorkflowSubjectInterface` — контракт для сутності, яку можна переводити між статусами.
- `TransitionContext` — хто, звідки і коли ініціював зміну.
- `TransitionDefinition` — іменований бізнес-перехід, наприклад `confirm` або `cancel`.
- `TransitionGuardInterface` — перевіряє, чи можна виконати дію.
- `TransitionActionInterface` — виконує побічну логіку переходу.
- `HistoryRecorderInterface` — записує факт завершеного переходу.
- `TransitionResult` — результат успішної зміни статусу.

## Поточний приклад

Для `Order` зараз реалізовано:

- `confirm`: `draft -> confirmed`
- `return_to_draft`: `confirmed -> draft`
- `cancel`: будь-який нескасований статус `-> canceled`

Пов’язані класи:

- `Definition/OrderWorkflowDefinition.php`
- `Guard/OrderHasEntriesGuard.php`
- `Action/MarkOrderCanceledAction.php`
- `History/OrderHistoryRecorder.php`

## Inventory document status

`InventoryDocument` реалізує `WorkflowSubjectInterface`, але його `post` / `cancel` flow навмисно залишається у `InventoryPostingService`.

Причина: posting не є простою зміною статусу. Він має транзакційні побічні ефекти: stock movements, average cost, reservation completion, reversal document creation і перерахунок progress/status пов’язаних `Order`, `Purchase`, `ProductionOrder`.

Тому для inventory documents діє спеціалізований workflow:

- `InventoryPostingService` змінює статус і пише `StatusHistory` через `GenericStatusHistoryRecorder`.
- `DocumentProgressRecalculator` перераховує progress після posting/cancel.
- `BusinessDocumentStatusSynchronizer` пише derived `OrderHistory` / `StatusHistory`, коли статус пов’язаного бізнес-документа справді змінився.
- `InventoryDocumentWorkflowDefinition` не додається, доки немає окремого рішення переносити orchestration у generic workflow.
- `WorkflowStatusDriftTest` фіксує цю межу: прямі зміни `InventoryDocumentStatus` дозволені лише в `InventoryPostingService`, а generic definition для `InventoryDocument` відсутня.

Якщо пізніше буде прийняте рішення додати generic inventory workflow, definition має бути тонким orchestration-шаром і делегувати stock movements, average cost, reversal posting та progress sync у спеціалізовані сервіси. History має залишитися один раз на фактичний `post` або `cancel`.

## Як додати новий workflow

1. Реалізувати `WorkflowSubjectInterface` у сутності.
2. Створити окремий клас у `Definition/`.
3. Описати дозволені transitions через `TransitionDefinition`.
4. Додати окремі guards у `Guard/`, якщо потрібні перевірки.
5. Додати actions у `Action/`, якщо потрібна побічна логіка.
6. Підключити відповідний history recorder.
7. Додати тести для дозволених і заборонених переходів.

`StatusTransitionService` при цьому змінювати не потрібно. Якщо для нового workflow виникає бажання додати в сервіс `if ($subject instanceof ...)`, специфічну логіку треба перенести у definition, guard, action або recorder.

## Правила модуля

- Не змінювати бізнес-статуси напряму поза transition service.
- Виняток: `InventoryDocument` змінює статус у `InventoryPostingService`, бо posting/cancel має stock side effects і reversal flow.
- Називати transitions діями бізнесу, а не назвами цільових статусів.
- Зворотний перехід описувати окремо, якщо він справді дозволений.
- Guards не повинні змінювати стан сутності.
- Actions не повинні визначати, чи дозволений перехід — це зона guards.
- Definitions мають залишатися декларативними; складну логіку треба виносити в окремі класи.

## Тимчасовий стан

Для `Order` уже використовується постійний `History/OrderHistoryRecorder.php`.

`History/NullHistoryRecorder.php` залишається тимчасовою реалізацією для майбутніх workflow, якщо вони будуть підключені раніше, ніж з’явиться відповідна постійна історія.
