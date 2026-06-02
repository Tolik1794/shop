<?php

namespace App\Service\Discount;

use App\Entity\Order;
use App\Entity\OrderEntry;
use App\Entity\Product;
use App\Entity\ProductDiscountRule;
use App\Entity\Store;
use App\Enum\OrderDiscountModeEnum;
use App\Manager\UserManager;
use App\Security\PermissionChecker;
use RuntimeException;

class OrderEntryDiscountService
{
	public const string OVERRIDE_PERMISSION = 'order.discount.override';

	public function __construct(
		private readonly ProductDiscountResolver $productDiscountResolver,
		private readonly DiscountCostBasisCalculator $discountCostBasisCalculator,
		private readonly PermissionChecker $permissionChecker,
		private readonly UserManager $userManager,
	)
	{
	}

	public function apply(OrderEntry $orderEntry, Order $order): void
	{
		$product = $orderEntry->getProduct();
		$store = $order->getStore();

		if (!$product instanceof Product || !$store instanceof Store) {
			return;
		}

		$mode = $orderEntry->getDiscountMode();
		$rule = $orderEntry->getDiscountRule();
		$percent = null;

		if ($mode === null && $rule === null && $orderEntry->getDiscountPercent() === null) {
			$rule = $this->productDiscountResolver->defaultRule($product, $store);
		}

		if ($mode === OrderDiscountModeEnum::MANUAL_OVERRIDE) {
			$this->assertCanOverride('Manual order discount requires discount override permission.');
			$percent = $this->normalizedPercent($orderEntry->getDiscountPercent());
			$orderEntry
				->setDiscountRule(null)
				->setDiscountRuleNameSnapshot(null);
		} elseif ($rule instanceof ProductDiscountRule) {
			if (!$this->productDiscountResolver->isAllowed($rule, $product, $store)) {
				throw new RuntimeException('Selected discount is not allowed for this product.');
			}

			$percent = $this->normalizedPercent($rule->getPercent());
			$orderEntry
				->setDiscountMode(OrderDiscountModeEnum::RULE)
				->setDiscountRule($rule)
				->setDiscountRuleNameSnapshot($rule->getName());
		} else {
			$orderEntry
				->setDiscountMode(OrderDiscountModeEnum::NONE)
				->setDiscountRule(null)
				->setDiscountRuleNameSnapshot(null)
				->setDiscountPercent(null)
				->setDiscountAmount(null)
				->setDiscountAmountBase(null);

			return;
		}

		if ($percent <= 0.00005) {
			$orderEntry
				->setDiscountMode(OrderDiscountModeEnum::NONE)
				->setDiscountRule(null)
				->setDiscountRuleNameSnapshot(null)
				->setDiscountPercent(null)
				->setDiscountAmount(null)
				->setDiscountAmountBase(null);

			return;
		}

		$quantity = $this->numberValue($orderEntry->getQuantity());
		$subtotal = max(0.0, $quantity * $this->numberValue($orderEntry->getUnitPrice()));
		$discount = min($subtotal, $subtotal * $percent / 100);

		$orderEntry
			->setDiscountPercent($this->formatPercent($percent))
			->setDiscountAmount($discount > 0.00005 ? $this->formatMoney($discount) : null);

		$this->assertNotBelowCost($orderEntry, $order, $discount);
	}

	private function assertNotBelowCost(OrderEntry $orderEntry, Order $order, float $discount): void
	{
		$costBasis = $this->discountCostBasisCalculator->forOrderEntry($orderEntry)->getEffectiveUnitCostBase();

		if ($costBasis === null) {
			return;
		}

		$quantity = $this->numberValue($orderEntry->getQuantity());
		$exchangeRateToBase = $this->numberValue($order->getExchangeRateToBase());
		$subtotal = max(0.0, $quantity * $this->numberValue($orderEntry->getUnitPrice()));
		$netTotalBase = max(0.0, $subtotal - $discount) * ($exchangeRateToBase > 0 ? $exchangeRateToBase : 1.0);
		$costTotalBase = $quantity * $this->numberValue($costBasis);

		if ($costTotalBase <= 0.00005 || $netTotalBase + 0.00005 >= $costTotalBase) {
			return;
		}

		$this->assertCanOverride('Discounted order line total is below cost and requires discount override permission.');
	}

	private function assertCanOverride(string $message): void
	{
		if ($this->permissionChecker->isGranted($this->userManager->getCurrentUser(), self::OVERRIDE_PERMISSION)) {
			return;
		}

		throw new RuntimeException($message);
	}

	private function normalizedPercent(mixed $value): float
	{
		$percent = max(0.0, min(100.0, $this->numberValue($value)));

		return is_finite($percent) ? $percent : 0.0;
	}

	private function numberValue(mixed $value): float
	{
		$number = (float) str_replace(',', '.', (string) $value);

		return is_finite($number) ? $number : 0.0;
	}

	private function formatMoney(float $value): string
	{
		return number_format($value, 4, '.', '');
	}

	private function formatPercent(float $value): string
	{
		return number_format($value, 4, '.', '');
	}
}
