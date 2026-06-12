# Податковий модуль (ФОП / Єдиний податок)

Модуль відстежує оподатковуваний дохід, нарахування ЄП/ЄСВ/ВЗ та декларації для ФОП на спрощеній системі оподаткування (групи 1–3).

## Архітектурний огляд

```
Payment → IncomeRecognitionService → IncomeRecord
                                          ↓
                               TaxCalculationService
                                          ↓
                          TaxAccrualGeneratorService → TaxAccrual
                                          ↓
                          TaxReportGeneratorService → TaxReportDraft
                                          ↓
                               TaxPeriodManager → TaxReportingPeriod
```

## Entities

| Entity | Таблиця | Призначення |
|---|---|---|
| `LegalEntity` | `legal_entity` | ФОП: група, ставка ЄП, ліміт, ознака платника ПДВ, ознака звільнення від ЄСВ |
| `TaxRateSet` | `tax_rate_set` | Щорічні ставки і ліміти (мінзарплата, прожитковий мінімум, ліміти по групах) |
| `IncomeRecord` | `income_record` | Касовий запис доходу: джерело (payment/manual), класифікація, сума UAH, курс НБУ |
| `IncomeRecordHistory` | `income_record_history` | Аудит-лог кожного income_record (recognized / reclassified) |
| `TaxAccrual` | `tax_accrual` | Нарахування ЄП/ЄСВ/ВЗ: тип, сума, строк сплати, статус (accrued/paid) |
| `TaxReportingPeriod` | `tax_reporting_period` | Звітний період (квартал/рік): статус (open/closed/declared), аудит-лог переходів |
| `TaxReportDraft` | `tax_report_draft` | Чернетка декларації: payload полів з розрахованим / ручним значенням, версія форми |
| `NbuExchangeRate` | `nbu_exchange_rate` | Офіційний курс НБУ на дату |

## Services

### `IncomeRecognitionService`
Касовий метод: розпізнає `Payment` → `IncomeRecord`. Виклик через `PaymentManager::savePayment()` / `reversePayment()` в одній транзакції. Правила:
- вхідна оплата зі store.legalEntity → `classification=income`
- реверс вхідної → `classification=refund` в поточному періоді
- store без legalEntity → skip (оплата зберігається, дохід не фіксується)
- валюта без курсу НБУ → skip (оплата зберігається, backfill підхопить пізніше)
- закритий/задекларований період → skip (тихий пропуск, не блокує платіж)

### `NbuExchangeRateProvider`
Повертає офіційний курс НБУ на дату. UAH → '1.00000000'. Використовується в IncomeRecognitionService та IncomeRecordManager.

### `NbuRateSyncService`
Синхронізує курси з API НБУ (`app:tax:nbu-rates-sync`). Ідемпотентний upsert.

### `IncomeBackfillService`
Ретроспективне визнання доходу (`app:tax:income-backfill [--apply]`). Батчами по ~200 записів.

### `IncomeLimitService`
Будує `LimitStatusDto` з відсотком використання ліміту ЄП, прогнозом виходу на ліміт (90-денний середньоденний дохід), рівнем попередження (70/85/95/100%).

### `TaxCalculationService`
Чисті формули без БД. Unit-testable:
- `calculateEpGroup3(netIncome, ratePct)` — ЄП гр.3 = дохід × ставка
- `calculateEpMonthly(epGroup, rateSet)` — фіксований ЄП гр.1/2
- `calculateEsvQuarterly(rateSet, months, exempt)` — ЄСВ = мінзарплата × 22% × місяці
- `calculateVzGroup3(netIncome, rateSet)` — ВЗ гр.3 = дохід × 1%
- `calculateVzMonthly(rateSet)` — ВЗ гр.1/2 = мінзарплата × 1%
- `epDueDateGroup3(year, quarter)` — 10-е число першого місяця наступного кварталу
- `esvDueDate(year, quarter)` — 20-е число першого місяця наступного кварталу
- `epVzDueDateMonthly(year, month)` — 20-е число поточного місяця

### `TaxAccrualGeneratorService`
Ідемпотентне нарахування (upsert). Гр.3: 4 квартали × 3 типи (ЄП+ВЗ по доходу, ЄСВ). Гр.1/2: 12 місяців (ЄП+ВЗ) + 4 квартали (ЄСВ). Пропускає PAID-записи без зміни суми.

### `TaxReportGeneratorService`
Генерує `TaxReportDraft` з payload полів. Гр.3: наростаючий підсумок (cumulative − previous = quarter). Гр.1/2: річна декларація. Регенерація оновлює той самий чернетку (ручні коригування скидаються — навмисно, бо вхідні дані змінились). XML-формат для Е-кабінету — TODO (значення переносяться вручну).

### `TaxPeriodGuard`
Два режими захисту:
- `isClosed()` — тихий skip для автоматичних потоків (визнання оплат ніколи не блокується)
- `assertOpen()` — кидає RuntimeException для ручних UI-дій (форма показує помилку)

## Controllers

| Маршрут | Permission | Призначення |
|---|---|---|
| `/admin/legal-entity` | `tax.settings.manage` | CRUD юридичних осіб |
| `/admin/tax/control` | `tax.view` | Панель лімітів ЄП (прогрес-бари, прогноз) |
| `/admin/tax/income` | `tax.view` / write: `tax.income.manage` | Список доходів, ручний запис, перекласифікація |
| `/admin/tax/accruals` | `tax.view` / write: `tax.income.manage` | Нарахування, генерація, позначка оплачено |
| `/admin/tax/reports` | `tax.view` / write: `tax.reports.manage` | Декларації, ручні коригування, закриття периодів |

## Permissions

| Permission | Призначення |
|---|---|
| `tax.view` | Перегляд всіх сторінок модуля |
| `tax.income.manage` | Ручний дохід, перекласифікація, нарахування, оплата акрюелів |
| `tax.reports.manage` | Генерація декларацій, коригування полів, lifecycle периодів |
| `tax.settings.manage` | Юридичні особи, ставки |

Групи за замовчуванням: `super_admin` (через `system.all`), `admin`, `store_admin` — усі 4 tax permissions.

## Закриття периодів

Статуси `TaxReportingPeriod`: `open` → `closed` → `declared` → (reopen) → `open`.

- `close`: блокує ручні зміни доходів за цей квартал
- `declare`: фіксує факт подання декларації
- `reopen`: вимагає коментар; повертає в `open` для виправлення помилок

Кожен перехід записується в `status_log` (JSON array): `{at, by, from, to, comment}`.

Автоматичне визнання оплат ніколи не блокується закритим/задекларованим периодом — для платіжного модуля використовується тихий skip.

## Команди

- `app:tax:nbu-rates-sync [--date=YYYY-MM-DD] [--from=...] [--to=...]` — завантажити курси НБУ
- `app:tax:income-backfill [--apply] [--store-id=N]` — ретроспективне визнання доходу

## UML

`src/Uml/database/11_Tax.puml` — ER-діаграма всіх Tax-сутностей.

## Обмеження і відомі TODO

- XML-формат для Е-кабінету не реалізований (код форми і XSD не верифіковано). Значення переносяться вручну з друкованої форми.
- Автоматичного підрахунку avans / авансових внесків немає.
- Перевіряйте актуальність форми декларації перед поданням (`formVersion` у чернетці містить номер наказу Мінфіну).
