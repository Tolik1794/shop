# Податковий модуль — План інтеграції (Фаза 0)

> Результат Фази 0 за `TASK_tax_module.md`: аудит проєкту, мапінг нових сутностей на наявну схему,
> точки інтеграції, відкриті питання. Код і міграції не створювалися.
>
> **Дисклеймер:** модуль — інструмент обліку, а не податкова консультація. Всі довідкові значення
> (ставки, ліміти, МЗП) підлягають перевірці користувачем перед використанням.

---

## 1. Стек і релевантна наявна схема

### Стек

- **Symfony 7.4**, PHP 8 (атрибути), Doctrine ORM, PostgreSQL (predicate-індекси в міграціях).
- Міграції: `migrations/Version{YYYYMMDDHHMMSS}.php`, raw SQL через `$this->addSql()`, **обидва** `up()`/`down()` реалізовані, `getDescription()` обовʼязковий.
- Адмін-UI: Twig + Stimulus + Turbo (Encore), пагінація KnpPaginator, меню `src/Menu/MenuBuilder.php` з permission-гейтами.
- RBAC: `src/Security/PermissionCatalog.php` (константи + групи) → сід через `src/DataFixtures/PermissionFixtures.php`; контролери захищаються `#[IsGranted('permission.code')]`.
- Тести: `KernelTestCase` (менеджери/сервіси), `WebTestCase` (контролери), `tests/{Manager,Controller,Service,...}`.
- Конвенція грошей: суми `decimal(14,4)` (string у PHP), курси `decimal(18,8)`, `DateTimeImmutable`; Money value object **відсутній** — форматування через `number_format()` у менеджерах і Twig-фільтр `|money` (`src/Twig/MoneyExtension.php`).
- Side effects — **тільки явні виклики сервісів з менеджерів** (патерн `PaymentHistoryRecorder`, `PaymentRecalculationService`), Doctrine-лістенери для бізнес-логіки не використовуються.
- Soft-delete: `deletedAt` + `ActiveStatusEnum`; репозиторії фільтрують `deletedAt IS NULL`.
- Console-команди: `app:*`, конвенція **«звіт за замовчуванням + `--apply` для запису»** (див. `ReconcileStockReservationsCommand`).
- UML: `src/Uml/database/NN_Name.puml` + `_common.puml`, SVG генеруються локальним `/opt/homebrew/bin/plantuml`. Зайнято 01–10, наступний — **11**.

### Релевантні таблиці (звірено з кодом)

| Сутність | Ключове для податкового модуля |
|---|---|
| `Payment` (`src/Entity/Payment.php`) | `direction` (incoming/outgoing), `type` (cash/card/bank_transfer/online/other), `amount`+`currency`, `amountBase` у `Store.baseCurrency` за `exchangeRateToBase` (комерційний курс — **не** придатний для податку при валюті), `paidAt`, рівно один з `order`/`purchase`, `reversesPayment`/`reversedByPayment`, `externalReference` (вільний текст, без унікальності), `version`, `createdBy/updatedBy`. Створюється тільки вручну через `PaymentManager::savePayment()`. |
| `PaymentHistory` | Append-only фід для таймлайну, без backfill. **Джерелом даних для податків не є** (підтверджено завданням і кодом). |
| `Order` / `Purchase` | `paidAmountBase`, `paymentStatus`, `paidAt` — денормалізований кеш від `PaymentRecalculationService`. Не джерело для податків. |
| `Store` | `baseCurrency` (FK на `Currency.code`), **жодних реквізитів/юрособи немає**. Колекція `payments`. |
| `Currency` / `ExchangeRate` | Курси ручні, періодні (`validFrom`/`validTo`), глобальні або per-store, `source` — вільний рядок; `ExchangeRateResolver::resolve()`. Це **комерційні** курси. |
| `StatusHistory` | Generic-аудит зміни статусів будь-якої сутності (`StatusHistoryEntityType` + entityId) — реюзаємо для статусів звітних періодів. |
| `User`, `Permission`, `UserGroup` | Permission-based RBAC, резолюція: individual deny → individual allow → groups → `system.all`. |

---

## 2. Розбіжності завдання з фактичним станом проєкту і прийняті рішення

Умова завдання описує можливості, яких **немає в репозиторії**. Перевірено пошуком по сутностях, сервісах, контролерах, міграціях і composer.json:

| # | Очікувалось у завданні | Фактичний стан | Рішення (погоджено з користувачем 2026-06-11) |
|---|---|---|---|
| 1 | Модуль обробки банківських виписок | **Відсутній** (немає сутностей/сервісів/міграцій) | MVP: джерела доходу — тільки `Payment` + ручні записи. `IncomeRecord.sourceType` резервує `bank_statement_row`, unique(sourceType, sourceId) закладається одразу. Імпорт виписок — окреме майбутнє завдання. |
| 2 | Інтеграції оплат (еквайринг) | **Відсутні**: всі `Payment` вводяться вручну, `paidAt` вводить оператор | Дохід визнається по `paidAt` — задокументоване спрощення (див. відкриті питання §5.1–5.2). |
| 3 | Розрахункові рахунки (IBAN) | **Не змодельовані** | У MVP не потрібні (без виписок). Поле для майбутньої привʼязки рахунків до `LegalEntity` — у дизайні передбачено, але не реалізується. |
| 4 | Джерело курсів НБУ | **Відсутнє** (`ExchangeRate` — ручні комерційні курси) | Валютні надходження Є (підтверджено користувачем) → у Фазі 2 окрема таблиця `nbu_exchange_rate` + sync-команда з API НБУ. Комерційні курси не чіпаємо. |
| 5 | Механізм нотифікацій, фонові задачі | **Відсутні** (немає Notifier/Messenger/cron-інфри) | Алерти лімітів — банер в адмінці + дашборд-віджет (Фаза 3), розрахунки on-the-fly + console-команди. |
| 6 | «Наявний механізм генерації документів» (PDF) | **Відсутній** (composer без dompdf/snappy/mpdf) | Фаза 5: друкована HTML-форма з водяним знаком «ЧЕРНЕТКА» (browser print → PDF). Додавання PDF-бібліотеки — окреме рішення користувача (зміна composer заборонена правилами без явного запиту). |
| 7 | Загальний аудит-лог | Є: `StatusHistory` (generic) + патерн доменних `*History` + recorder-сервіси | Реюзаємо `StatusHistory` для статусів періодів; для перекласифікацій доходу — власна `IncomeRecordHistory` за патерном `PaymentHistory`. |

**Рішення користувача:**
- Виписки — поза скоупом MVP (тільки Payment + ручні записи).
- Store ↔ юрособа — простий nullable FK (без таблиці періодів); незмінність історії гарантує снапшот юрособи в `IncomeRecord` на момент визнання.
- Валютні надходження існують → курси НБУ потрібні вже в MVP.

---

## 3. Мапінг нових сутностей (фінальні назви за конвенціями проєкту)

Усі сутності: id auto-increment, `createdAt`/`updatedAt` `DateTimeImmutable`, суми `decimal(14,4)`, курси `decimal(18,8)`. Таблиці — за дефолтною underscore-стратегією Doctrine.

### 3.1 `LegalEntity` → `legal_entity`

Юрособи (ФОП/ТОВ). CRUD — глобальний розділ адмінки (поза store-контекстом, як Stores/Managers).

- `name` (повна), `shortName`;
- `type` — `LegalEntityTypeEnum: string` (`fop` | `tov`);
- `taxNumber` (string, РНОКПП/ЄДРПОУ), unique;
- `taxSystem` — `TaxSystemEnum: string` (`simplified` | `general`);
- `epGroup` (int nullable: 1|2|3), `epRate` (`decimal(5,2)` nullable — 5.00/3.00 для 3 групи);
- `vatPayer` (bool), `esvExempt` (bool);
- `registeredAt`, `simplifiedSince` (nullable);
- `address` (text nullable), `kveds` (json nullable);
- `status` `ActiveStatusEnum` + `deletedAt` (soft-delete за конвенцією);
- `createdBy`/`updatedBy` (FK User, заповнюються в менеджері).

Рахунки (IBAN) — не реалізуються в MVP; при появі модуля виписок додається `legal_entity_bank_account`.

### 3.2 `Store.legalEntity` — FK у наявній таблиці `store`

Nullable `ManyToOne` на `LegalEntity`. **Єдина зміна наявної сутності.** Після заповнення для всіх магазинів — джерело мапінгу «Payment.store → юрособа» для нових оплат. Для визнаного доходу юрособа фіксується снапшотом у `IncomeRecord` і при зміні привʼязки магазину **не** перераховується.

### 3.3 `TaxRateSet` → `tax_rate_set`

Версійований по роках довідник, `unique(year)`:

- `year` (int), `minimumWage` (МЗП на 1 січня), `subsistenceMinimum` (ПМ);
- `group1IncomeLimit` / `group2IncomeLimit` / `group3IncomeLimit`;
- `group1EpMonthly`, `group2EpMonthly` (фіксовані суми/міс), `group3EpRatePct`, `group3EpRateVatPct` (`decimal(5,2)`);
- `esvRatePct` (`decimal(5,2)`), `esvMonthlyMin`;
- `vzGroup12Monthly`, `vzGroup3RatePct`;
- `comment` (text) — у seed 2026: «Verify before relying — значення станом на 01.01.2026, перевірити актуальність».

Seed 2026 (фікстура за патерном `PermissionFixtures`, ідемпотентна): МЗП 8 647; ПМ 3 328; ліміти 1 444 049 / 7 211 598 / 10 091 049; ЄП гр.1 332,80/міс, гр.2 1 729,40/міс, гр.3 5%/3%; ЄСВ 22% (мін. 1 902,34/міс); ВЗ гр.1–2 864,70/міс, гр.3 1%. Додавання 2027 року — одним рядком seed без зміни коду.

### 3.4 `IncomeRecord` → `income_record`

Визнаний дохід, append-only проєкція:

- `legalEntity` (FK, **снапшот на момент визнання**), `store` (FK nullable, довідково);
- `recognizedAt` (date), `amount` + `currency` (як у джерелі), `amountUah` `decimal(14,4)` + `nbuExchangeRate` `decimal(18,8)` (UAH: amountUah = amount, курс 1);
- `sourceType` — `IncomeSourceTypeEnum: string` (`payment` | `bank_statement_row` | `manual`) + `sourceId` (int nullable для manual), **`unique(source_type, source_id)`** — захист від задвоєння;
- `payment` (FK nullable — пряме посилання для зручності, коли sourceType=payment);
- `classification` — `IncomeClassificationEnum: string` (`income` | `refund` | `non_income_transfer` | `non_income_own_funds` | `non_income_other`);
- `refundOfIncomeRecord` (self-FK nullable) — звʼязок refund-запису з оригінальним доходом;
- `counterparty` (string nullable), `paymentPurpose` (text nullable), `comment`;
- `createdBy`/`updatedBy`.

Індекси: `(legal_entity_id, recognized_at)`, `(legal_entity_id, classification, recognized_at)`.

### 3.5 `IncomeRecordHistory` → `income_record_history`

Append-only аудит перекласифікацій за патерном `PaymentHistory`: `eventKey` (`income.recognized`, `income.reclassified`), снапшоти old/new класифікації в `payload` (json), `occurredAt`, `actor` + `actorNameSnapshot`. Пишеться recorder-сервісом, викликаним явно з менеджера.

### 3.6 `NbuExchangeRate` → `nbu_exchange_rate`

Окремо від комерційних `exchange_rate` (щоб `ExchangeRateResolver` їх не підхоплював):

- `currency` (FK), `date` (date), `rate` `decimal(18,8)`, `unique(currency_code, date)`;
- `source` (string: `nbu_api` | `manual`), `createdAt`.

Заповнення: команда `app:tax:nbu-rates-sync` (офіційний API НБУ) + ручний ввід як fallback. Фаза 2.

### 3.7 `TaxReportingPeriod` → `tax_reporting_period`

- `legalEntity` (FK), `type` — `TaxPeriodTypeEnum: string` (`month` | `quarter` | `year`);
- `dateFrom` / `dateTo`, `status` — `TaxPeriodStatusEnum: string` (`open` | `closed` | `declared`);
- `unique(legal_entity_id, type, date_from)`.

Зміни статусу — через наявний `StatusHistory` (новий case у `StatusHistoryEntityType`). Закриття періоду блокує зміну `IncomeRecord` у ньому (з переввідкриттям під аудит) — Фаза 5.

### 3.8 `TaxAccrual` → `tax_accrual`

- `legalEntity` (FK), `period` (FK на `TaxReportingPeriod`);
- `taxType` — `TaxTypeEnum: string` (`ep` | `esv` | `vz`);
- `accruedAmount`, `paidAmount` (`decimal(14,4)`), `dueDate` (date), `paidAt` (nullable);
- `status` — `TaxAccrualStatusEnum: string` (`accrued` | `paid` | `overdue`);
- `bankStatementRowId` (int nullable — резерв під майбутню звірку з виписками);
- `unique(legal_entity_id, period_id, tax_type)`.

Індекси: `(legal_entity_id, due_date)`, `(status, due_date)`.

### 3.9 `TaxReportDraft` → `tax_report_draft`

- `legalEntity` (FK), `period` (FK), `reportType` (string), `formVersion` (string — напр. «наказ МФУ 31.01.2025 №57», верифікувати у Фазі 5);
- `payload` (json — всі поля звіту; ручні коригування з позначкою `manual`);
- `generatedAt`, `status` (`draft` | `exported`), `createdBy`.

ТОВ/загальна система: структура (`taxSystem=general`, `TaxTypeEnum` розширюваний) дозволяє майбутні податок на прибуток/ПДВ, формули в MVP не реалізуються.

---

## 4. Точки інтеграції з наявним кодом

1. **`PaymentManager`** — єдина зміна платіжного ядра: інʼєкція нового `IncomeRecognitionService` (за патерном `PaymentHistoryRecorder`) + виклик у `savePayment()` і reverse-флоу. Без Doctrine-лістенерів — за конвенцією проєкту side effects ідуть явними викликами з менеджера. Ідемпотентність гарантує `unique(source_type, source_id)`. Правила мапінгу: `incoming` → кандидат `income` по `paidAt`; реверс → `refund` у періоді реверсу зі звʼязком на оригінал; `outgoing`+`purchase` → ігнорується.
2. **`Store` + `StoreType` + шаблон форми магазину** — поле `legalEntity` (мінімальна зміна).
3. **`StatusHistoryEntityType`** — новий case `TAX_REPORTING_PERIOD` для аудиту статусів періодів.
4. **`PermissionCatalog` + `PermissionFixtures`** — Фаза 6: `tax.view`, `tax.income.manage`, `tax.reports.manage`, `tax.settings.manage`; дефолтні групи: `super_admin`/`admin` — все, `store_admin` — `tax.view` (фінансові дані не видно ролям без прав). Тимчасовий гейт Фаз 1–5 — наявний `dashboard.financial`.
5. **`MenuBuilder`** — глобальний розділ «Податки» (юрособи, доходи, податковий контроль, звіти), permission-гейт як у наявних пунктів.
6. **UML** — новий `src/Uml/database/11_Tax.puml` (стиль наявних, `!include ./_common.puml`, ноти про правила мапінгу з `Payment`) + SVG локальним plantuml. Оновлення `10_Database_Invariants_And_Indexes.puml` новими інваріантами.
7. **Backfill** — `app:tax:income-backfill`: за конвенцією проєкту **звіт за замовчуванням** (кількість записів, розподіл за класифікацією, суми по юрособах/роках), запис — тільки з `--apply`. Це відповідає вимозі завдання про обовʼязковий dry-run.
8. **Дашборд** — віджет «% ліміту по юрособах» у наявний дашборд під `dashboard.financial` (Фаза 3) + окрема сторінка «Податковий контроль».
9. **Переклади** — англійські source-рядки + `translations/messages.uk.yaml`, перевірка `debug:translation uk`.
10. **Документація** — `docs/tax-module/README.md` (Фаза 6) + оновлення `docs/admin-user-guide.md` (нові розділи адмінки) + `AGENTS.md` (згадка модуля).

---

## 5. Відкриті питання для користувача

1. **Семантика `paidAt` для card/online.** Оплати вводяться вручну. Яку дату фактично вводить оператор — дату оплати покупцем чи дату зарахування на рахунок? MVP визнає дохід по `paidAt` (консервативно: дохід раніше; критично лише на межі кварталу/року). Підтвердити прийнятність спрощення.
2. **Повнота сум card/online.** Чи вводиться повна сума оплати (до утримання комісії еквайра)? Якщо вводиться «на руки» — податковий дохід занижується; потрібно змінити процес вводу (дохід завжди в повній сумі).
3. **Мапінг магазин → юрособа.** Даних у системі немає; після Фази 1 користувач заповнює `Store.legalEntity` для всіх магазинів. Backfill (Фаза 2) можливий лише після цього.
4. **PDF-експорт чернеток (Фаза 5).** Чи достатньо друкованої HTML-форми (browser print → PDF), чи додаємо PDF-бібліотеку (dompdf) — це зміна composer-залежностей і потребує явного дозволу.
5. **Алерти лімітів (Фаза 3).** Notification-механізму в проєкті немає. Чи достатньо банера в адмінці + віджета на дашборді, чи нотифікації (email/Telegram) — окреме завдання?
6. **API НБУ.** Sync курсів планую з офіційного API НБУ (`bank.gov.ua/NBUStatService/v1/statdirectory/exchange?json`). Підтвердити доступність зовнішніх HTTP-запитів з прод-серверу.
7. **Звітні періоди до впровадження.** З якого року/кварталу починати backfill і генерувати періоди (дата реєстрації юрособи? перший Payment у системі?).

---

## 6. План фаз 1–6 (з урахуванням прийнятих рішень)

- **Фаза 1 — Модель даних:** міграції (зворотні) + сутності §3 + enum-и; `Store.legalEntity`; seed `TaxRateSet` 2026; CRUD юросіб (глобальний розділ, за зразком Supplier-модуля, тимчасовий гейт `dashboard.financial`); `11_Tax.puml` + SVG.
- **Фаза 2 — Визнання доходу:** `IncomeRecognitionService` + інтеграція в `PaymentManager`; `NbuExchangeRate` + sync-команда; ручні записи; перекласифікація з аудитом (`IncomeRecordHistory`); backfill-команда (звіт → `--apply`); сторінка «Доходи» по юрособі. Виписки — поза скоупом (рішення §2).
- **Фаза 3 — Ліміти і дашборд:** наростаючий підсумок (income мінус refund), порівняння з лімітом групи з `TaxRateSet`, пороги 70/85/95% (конфігуровані), прогноз по середньоденному за 30/90 днів, віджет + сторінка «Податковий контроль». Обчислення on-the-fly (фонової інфри немає).
- **Фаза 4 — Податки і календар:** формули ЄП/ЄСВ/ВЗ тільки з `TaxRateSet`; автогенерація `TaxAccrual` з дедлайнами; статуси нараховано/сплачено/прострочено; юніт-тести формул з округленням до копійки і межовими кейсами (нульовий дохід, реєстрація/зміна групи всередині року). Звірка сплат з виписками — відкладена разом із модулем виписок.
- **Фаза 5 — Чернетки звітів:** верифікація актуальної форми декларації ЄП (наказ МФУ від 31.01.2025 № 57 — перевірити) + додаток 1 (ЄСВ); генерація payload; перегляд з ручним коригуванням; друкована форма з написом «ЧЕРНЕТКА» (PDF — після відповіді на §5.4); XML для Електронного кабінету — тільки за надійної верифікації XSD, інакше задокументований TODO; закриття періоду блокує зміни доходів.
- **Фаза 6 — RBAC, тести, документація:** пермішени `tax.*` у каталог + фікстури; юніт/інтеграційні тести + повний прогін наявного сьюту; `docs/tax-module/README.md` + інструкція користувача; оновлення `AGENTS.md`.

Кожна фаза: тести → окремий commit `tax-module: phase N — ...` → підсумок → СТОП до підтвердження.

---

*Фаза 0 завершена 2026-06-11. Наступний крок — погодження цього плану користувачем, після чого стартує Фаза 1.*
