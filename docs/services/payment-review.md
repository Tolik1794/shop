# Payment review service

`OrderPaymentReviewService` derives read-only financial review state for orders.

For canceled orders, existing recorded payments are not edited, deleted or refunded automatically. If a canceled order still has a positive net paid amount, the service returns a review marker so the admin UI can warn accounting to process a manual outgoing payment or `Reverse` correction when money is actually returned.

The same service also derives order payment actions for the admin payments tab. Active orders with an unpaid remainder expose an incoming-payment action; canceled orders with a positive net paid amount expose an outgoing-refund action. Those actions are only UI hints for prefilled forms. The payment method/type remains editable; order refunds are identified by outgoing direction. `PaymentAmountLimitService` and `PaymentManager` enforce the backend caps so an order payment cannot exceed the remaining amount and a refund cannot exceed the net paid amount.

This service must not create `Payment` rows. Payment creation and reversal remain in `PaymentManager`, where document locks, history and payment-status recalculation are handled.
