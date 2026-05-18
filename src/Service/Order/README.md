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

## Важливі правила

- `OrderEntrySnapshotter` не визначає ціну і не виконує розрахунки.
- `OrderEntryPricingService` не перераховує вже введену ціну позиції. Якщо `unitPrice > 0`, значення зберігається як ручне або вже зафіксоване.
- Саме визначення каталожної ціни делегується в `src/Service/Pricing/CatalogPriceResolver.php`.

## Потік використання

Під час збереження замовлення `OrderManager`:

1. готує заголовок замовлення;
2. передає позицію в `OrderEntrySnapshotter`;
3. передає позицію в `OrderEntryPricingService`;
4. виконує підсумковий перерахунок через `OrderCalculator`.

## Пов’язані місця

- `src/Manager/OrderManager.php`
- `src/Service/Pricing/README.md`
- `src/Service/OrderCalculator.php`
