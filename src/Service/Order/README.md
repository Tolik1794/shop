# Order services

Цей каталог містить невеликі прикладні сервіси, які виконують окремі кроки підготовки позицій замовлення.

## Навіщо їх винесено

Раніше `OrderManager` одночасно:

- копіював snapshot товару в позицію замовлення;
- визначав початкову ціну позиції;
- координував збереження, перерахунок і історію.

Snapshot і ціноутворення мають різні причини для змін, тому вони винесені в окремі сервіси. Так `OrderManager` лишається orchestration-рівнем, а деталі можна розвивати незалежно.

## Класи

- `OrderEntrySnapshotter` — копіює поточні назву, артикул і одиницю товару в snapshot-поля `OrderEntry`.
- `OrderEntryPricingService` — початково заповнює `unitPrice`, якщо в позиції ще немає введеної ціни.
- `OrderBatchPricingService` — читає FIFO-партії залишку для пошуку, застосовує продажну ціну вибраної партії і перевіряє, що позиція замовлення відповідає цій партії.
- `OrderEntryDiscountService` — застосовує дозволену відсоткову знижку або ручний override, рахує snapshot `discountAmount` і блокує продаж нижче собівартості без permission `order.discount.override`.
- `OrderEntryProgressCalculator` — рахує похідний (не збережений) стан виконання однієї позиції: `ordered/shipped/returned/canceled/remaining/returnable` і `state` (`OrderEntryFulfillmentState`). Повертає `OrderEntryProgress`. Використовується на сторінці замовлення (через Twig-функцію `order_entry_progress`) і в модалці вибіркового відвантаження, щоб показувати виконання по кожній позиції, коли час відвантаження різний.
- `StockReplenishmentQueue` — request-scoped черга `[storeId => productIds]`. `InventoryPostingService` ставить у неї товари з IN-рядків (збільшення залишку) під час проведення; це лише запис у памʼять, безпечний навіть у вкладеній транзакції.
- `AwaitingStockReplenishmentService` — після коміту (drain на `kernel.terminate` через `StockReplenishmentSubscriber`) знаходить замовлення в `awaiting_stock` з цими товарами і по кожному викликає `OrderManager::refreshAvailability()` (best-effort, кожне у власній транзакції). Так замовлення автоматично переходить у `ready_to_ship`, коли надходить потрібний товар.

## Важливі правила

- `OrderEntrySnapshotter` не визначає ціну і не виконує розрахунки.
- `OrderEntryPricingService` не перераховує вже введену ціну позиції. Якщо `unitPrice > 0`, значення зберігається як ручне або вже зафіксоване.
- Партії показуються в пошуку як окремі stock-варіанти, найстаріша партія йде першою.
- `OrderBatchPricingService` працює тільки для позицій із конкретним складом і вибраною партією. Production/service/backorder fallback лишаються на каталожній ціні.
- `OrderEntryProgressCalculator` лише читає поля позиції; стан виконання НЕ зберігається. Його арифметика навмисно дзеркалить `OrderShipmentUseCase::remainingQuantity()`, `CustomerReturnUseCase::returnableQuantity()` і `BusinessDocumentStatusSynchronizer::syncOrder()`, тому стан позиції не суперечить агрегованому статусу замовлення.
- Пере-оцінка наявності (`refreshAvailability`/`AwaitingStockReplenishmentService`) виконується ПІСЛЯ коміту проведення (на `kernel.terminate`), а не всередині транзакції проведення: інакше тримання локів складу і подальше блокування замовлень дало б зворотний до `confirm()` порядок локів (Stock→Order) і ризик дедлоку. Кожне замовлення оновлюється у власній транзакції в порядку Order→Stock.
- Якщо `OrderEntry.warehouseStockBatch` заповнений, бронювання і відвантаження мають використовувати цю ж партію.
- Саме визначення каталожної ціни делегується в `src/Service/Pricing/CatalogPriceResolver.php`.
- Дозволені знижки визначаються `ProductDiscountRule`; дефолтне правило автоматично підставляється для нового рядка, а ручна знижка не дозволена без `order.discount.override`.
- Собівартість для перевірки знижки бере unit cost вибраної партії або середньозважену `WarehouseStock.averageCost`; доставка, комісії оплати й інші витрати зарезервовані як майбутні компоненти в `DiscountCostBasisCalculator`.

## Потік використання

Під час збереження замовлення `OrderManager`:

1. готує заголовок замовлення;
2. перевіряє batch-привʼязку stock-позицій через `OrderBatchPricingService`;
3. передає позицію в `OrderEntrySnapshotter`;
4. застосовує batch-ціну через `OrderBatchPricingService`;
5. передає позицію в `OrderEntryPricingService` для каталожного fallback;
6. застосовує `OrderEntryDiscountService`;
7. виконує підсумковий перерахунок через `OrderCalculator`.

## Пов’язані місця

- `src/Manager/OrderManager.php`
- `src/Service/Pricing/README.md`
- `src/Service/OrderCalculator.php`
- `src/Service/Discount/`
