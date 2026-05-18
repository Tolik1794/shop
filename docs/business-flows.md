# Business flows

## Product creation

Admin form → ProductController → ProductManager → ImageUploader → EntityManager

## Order creation

Admin form → OrderController → OrderManager → WarehouseStockManager → OrderHistory

## Stock update

Procurement → ProcurementManager → WarehouseStockManager → StockMovement