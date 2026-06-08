<?php

namespace App\Enum;

/**
 * Derived, presentation-only fulfillment state of a single order entry.
 *
 * This is NOT persisted. It mirrors, at the line level, the aggregate status logic in
 * {@see \App\Service\BusinessDocumentStatusSynchronizer::syncOrder()} so the order detail can show
 * how far each position has progressed when shipment times differ between positions.
 */
enum OrderEntryFulfillmentState: string
{
	case PENDING = 'pending';
	case PARTIALLY_SHIPPED = 'partially_shipped';
	case SHIPPED = 'shipped';
	case PARTIALLY_RETURNED = 'partially_returned';
	case RETURNED = 'returned';
	case REFUSED = 'refused';
}
