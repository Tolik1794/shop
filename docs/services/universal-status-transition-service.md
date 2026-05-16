# Універсальний сервіс переходів статусів

## Призначення

`StatusTransitionService` — це єдина точка для зміни статусів сутностей, у яких статус є частиною бізнес-процесу, а не просто полем у базі даних.

Сервіс потрібен для того, щоб:

- не розносити перевірки статусів по контролерах, менеджерах і сутностях;
- описувати дозволені переходи явно, за бізнес-подіями, а не прямим присвоєнням нового значення;
- централізовано запускати перевірки, побічні дії, запис історії та доменні події;
- підключати нові сутності без редагування ядра сервісу.

Поточний UML-опис архітектури знаходиться у файлі:

- `src/Uml/services/11.1_Universal_Status_Transition_Service.puml`

## Головний принцип

Статус не слід змінювати напряму там, де це є бізнес-дією.

Погано:

```php
$order->setStatus(OrderStatus::CONFIRMED);
```

Правильно:

```php
$this->statusTransitionService->apply(
	$order,
	'confirm',
	TransitionContext::system(),
);
```

Пряме встановлення статусу допустиме лише:

- під час початкового створення сутності;
- у технічних місцях, де сервіс ще свідомо не підключений;
- після окремого рішення, якщо статус не має workflow-логіки.

## З чого складається сервіс

### `WorkflowSubjectInterface`

Інтерфейс для сутностей, які можуть брати участь у workflow переходів.

Сутність повинна вміти:

- сказати, до якого workflow вона належить;
- повернути поточний статус як рядок;
- прийняти новий статус як рядок.

Зараз цей інтерфейс реалізує `Order`.

### `StatusTransitionService`

Стабільне ядро переходів. Воно:

1. знаходить потрібний workflow для сутності;
2. знаходить потрібний перехід за його ключем;
3. перевіряє, чи дозволено виконувати цей перехід із поточного статусу;
4. запускає guards;
5. виконує `beforeActions`;
6. змінює статус;
7. виконує `afterActions`;
8. передає результат у history recorder;
9. відправляє доменну подію після успішного переходу.

Сервіс не повинен містити умов на кшталт `if ($subject instanceof Order)`. Уся специфіка конкретної сутності має жити за межами ядра.

### `WorkflowRegistry`

Реєстр визначень workflow. Він знаходить потрібний `WorkflowDefinitionInterface` для конкретної сутності.

Нові workflow автоматично підключаються через Symfony tag `app.workflow_definition`.

### `WorkflowDefinitionInterface`

Опис workflow для однієї конкретної сутності.

Визначення workflow відповідає за:

- список доступних transition key;
- дозволені переходи між статусами;
- guard/action pipeline для кожного переходу;
- вибір recorder-а для історії.

Приклад поточної реалізації:

- `OrderWorkflowDefinition`

### `TransitionDefinition`

Опис одного іменованого бізнес-переходу.

Наприклад:

- `confirm`: `draft -> confirmed`
- `return_to_draft`: `confirmed -> draft`
- `cancel`: будь-який нескасований статус `-> canceled`

Важливо: зворотний перехід не з’являється автоматично. Якщо бізнес дозволяє повернення назад, для нього треба окремо створити окремий transition key з власними правилами.

### Guards

Guards відповідають на питання: **чи можна виконати перехід зараз?**

Приклад:

- `OrderHasEntriesGuard` не дозволяє підтвердити порожнє замовлення.

Guards повинні лише перевіряти умови та викидати виняток, якщо перехід заборонений. Вони не повинні змінювати стан сутності.

### Actions

Actions виконують побічну бізнес-логіку до або після зміни статусу.

Приклад:

- `MarkOrderCanceledAction` записує час скасування замовлення.

Рекомендація:

- `beforeActions` використовувати для підготовки або резервування ресурсів;
- `afterActions` — для оновлення похідних даних після фактичної зміни статусу.

### `HistoryRecorderInterface`

Точка розширення для історії переходів.

Зараз використовується `NullHistoryRecorder`, бо сутності `OrderHistory` / `StatusHistory` ще не реалізовані в коді. Це тимчасова реалізація без запису в БД.

Коли історія буде додана, саме recorder треба замінити на реальну реалізацію:

- `OrderHistoryRecorder` для повної часової лінії замовлення;
- `GenericStatusHistoryRecorder` для інших сутностей зі звичайною історією зміни статусів.

Ядро `StatusTransitionService` при цьому змінювати не потрібно.

### `TransitionContext`

Контекст пояснює, **хто**, **звідки** і **коли** ініціював перехід.

Він містить:

- `actor` — користувач, якщо дія ручна;
- `source` — джерело події (`system`, `admin`, `api`, інтеграція тощо);
- `occurredAt` — час події;
- `comment` — службовий або користувацький коментар;
- `payload` — додаткові дані для recorder-а чи actions;
- `correlationId` — зв’язок між кількома подіями одного процесу.

Для системних переходів уже є зручний фабричний метод:

```php
TransitionContext::system();
```

### `TransitionResult`

Результат успішного переходу. Містить:

- сутність;
- ключ переходу;
- попередній статус;
- новий статус.

Саме цей об’єкт передається recorder-у та доменній події.

## Як виконується перехід

Для виклику:

```php
$result = $this->statusTransitionService->apply(
	$order,
	'confirm',
	TransitionContext::system(),
);
```

відбувається така послідовність:

1. `WorkflowRegistry` знаходить `OrderWorkflowDefinition`.
2. `OrderWorkflowDefinition` повертає transition `confirm`.
3. Сервіс перевіряє, що поточний статус — `draft`.
4. `OrderHasEntriesGuard` перевіряє наявність позицій у замовленні.
5. Сервіс змінює статус на `confirmed`.
6. Recorder отримує факт переходу.
7. Відправляється `StatusTransitionAppliedEvent`.
8. Повертається `TransitionResult`.

## Як перевірити можливість переходу без зміни стану

Для UI, кнопок або попередньої перевірки можна використовувати:

```php
$canConfirm = $this->statusTransitionService->can(
	$order,
	'confirm',
	TransitionContext::system(),
);
```

`can()`:

- виконує ті самі перевірки доступності переходу;
- не змінює статус;
- не запускає actions;
- не записує історію;
- повертає `false`, якщо перехід невідомий, не підтримується або заблокований guard-ом.

## Як додати нову сутність у майбутньому

Приклад: потрібно додати workflow для `Purchase`.

### 1. Сутність реалізує `WorkflowSubjectInterface`

```php
class Purchase implements WorkflowSubjectInterface
{
	public function getWorkflowKey(): string
	{
		return 'purchase';
	}

	public function getStatusValue(): string
	{
		return $this->status->value;
	}

	public function setStatusValue(string $status): void
	{
		$this->status = PurchaseStatus::from($status);
	}
}
```

### 2. Створюється окремий workflow definition

Наприклад:

- `PurchaseWorkflowDefinition`

У ньому треба явно перелічити:

- transition key;
- початкові статуси;
- цільовий статус;
- guards;
- actions;
- recorder історії.

### 3. За потреби додаються окремі guards/actions

Наприклад:

- guard, який забороняє завершити закупівлю без документів приходу;
- action, який оновлює складський прогрес після отримання товару.

### 4. Обирається recorder історії

Коли `StatusHistory` буде реалізований, для звичайних сутностей можна буде використовувати загальний recorder.

### 5. Ядро сервісу не змінюється

Якщо для нової сутності доводиться редагувати `StatusTransitionService`, це сигнал, що частину специфічної логіки треба винести у definition, guard, action або recorder.

## Поточний стан реалізації

На момент створення документа реалізовано:

- загальне ядро переходів;
- автопідключення workflow definitions через container tag;
- workflow для `Order`;
- переходи `confirm` і `cancel`;
- перехід `return_to_draft` для повернення `confirmed -> draft`;
- guard перевірки позицій замовлення;
- action встановлення часу скасування;
- доменна подія після успішного переходу;
- unit/integration тести для ядра та `OrderManager`.

Ще не реалізовано:

- реальний запис `OrderHistory`;
- загальний `StatusHistory` для інших сутностей;
- workflow definitions для `Purchase`, `InventoryDocument`, `ProductionOrder`;
- транзакційна обгортка всередині самого сервісу.

Останній пункт важливий: зараз persistence і flush усе ще залишаються в менеджерах сутностей, як це вже зроблено в проєкті. Коли з’явиться реальний запис історії та більше складних transitions, варто окремо вирішити, чи переносити транзакційну межу всередину сервісу, чи залишати її на рівні прикладного use case.

## Правила для майбутніх змін

1. Не додавати пряме встановлення статусу там, де це бізнес-перехід.
2. Не додавати entity-specific `if/else` у `StatusTransitionService`.
3. Нові переходи називати бізнес-дією, а не цільовим статусом.
4. Важкі правила виносити в окремі guards/actions, а definition залишати декларативним.
5. Зворотні переходи описувати окремо, не вважати їх автоматично дозволеними.
6. Після появи історії кожен значущий перехід повинен бути зафіксований recorder-ом.
7. Для нових workflow додавати тести щонайменше на:
   - граф переходів;
   - guards/actions;
   - успішний та заборонений сценарій через `StatusTransitionService`.

## Пов’язані файли

- `src/Workflow/StatusTransitionService.php`
- `src/Workflow/WorkflowRegistry.php`
- `src/Workflow/WorkflowDefinitionInterface.php`
- `src/Workflow/Definition/OrderWorkflowDefinition.php`
- `src/Workflow/TransitionDefinition.php`
- `src/Workflow/TransitionContext.php`
- `src/Workflow/HistoryRecorderInterface.php`
- `src/Workflow/Event/StatusTransitionAppliedEvent.php`
- `src/Uml/services/11.1_Universal_Status_Transition_Service.puml`
