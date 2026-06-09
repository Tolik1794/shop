<?php

namespace App\Manager;

use App\Entity\InventoryDocument;
use App\Entity\ProductionOrder;
use App\Entity\ProductionOrderMaterial;
use App\Entity\ProductionOrderStatus;
use App\Entity\ProductionRecipe;
use App\Entity\ProductionRecipeItem;
use App\Entity\Store;
use App\Entity\User\User;
use App\Entity\Warehouse;
use App\Entity\WarehouseStock;
use App\Enum\ProductKindEnum;
use App\Exception\StockOperationException;
use App\Repository\ProductionOrderRepository;
use App\Repository\ProductionRecipeRepository;
use App\Repository\WarehouseStockRepository;
use App\Service\Concurrency\ConcurrencyGuard;
use App\Service\InventoryPostingService;
use App\Service\Production\ProductionOrderFactory;
use App\Service\Order\StockReplenishmentQueue;
use App\Service\WarehouseStockService;
use App\Workflow\StatusTransitionService;
use App\Workflow\TransitionContext;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use RuntimeException;

class ProductionManager extends AbstractManager
{
	public function __construct(
		private readonly EntityManagerInterface $entityManager,
		private readonly ProductionOrderFactory $productionOrderFactory,
		private readonly StockReplenishmentQueue $stockReplenishmentQueue,
		private readonly InventoryPostingService $inventoryPostingService,
		private readonly WarehouseStockService $warehouseStockService,
		private readonly WarehouseStockRepository $warehouseStockRepository,
		private readonly StatusTransitionService $statusTransitionService,
		private readonly UserManager $userManager,
		private readonly ConcurrencyGuard $concurrencyGuard,
	)
	{
	}

	public function createOrderFromRecipe(
		ProductionRecipe $recipe,
		string $plannedQuantity,
		?Warehouse $warehouse = null,
		?DateTimeImmutable $plannedStartAt = null,
		?DateTimeImmutable $plannedEndAt = null,
		?string $comment = null,
	): ProductionOrder
	{
		$order = $this->productionOrderFactory->createFromRecipe(
			$recipe,
			$plannedQuantity,
			$warehouse,
			$this->currentActor(),
		);

		return $order
			->setPlannedStartAt($plannedStartAt)
			->setPlannedEndAt($plannedEndAt)
			->setComment($comment);
	}

	public function saveRecipe(ProductionRecipe $recipe, iterable $removedItems = []): void
	{
		$this->assertRecipeCanBeSaved($recipe);
		$recipe->setUpdatedAt(new DateTimeImmutable());

		foreach ($removedItems as $removedItem) {
			if ($removedItem instanceof ProductionRecipeItem) {
				$this->entityManager->remove($removedItem);
			}
		}

		foreach ($recipe->getItems() as $item) {
			$this->assertRecipeItemCanBeSaved($recipe, $item);
			$item->setRecipe($recipe);
			$this->entityManager->persist($item);
		}

		$this->save($recipe);
	}

	public function saveOrder(ProductionOrder $order, iterable $removedMaterials = []): void
	{
		$this->assertOrderCanBeSaved($order);
		$order
			->setUpdatedAt(new DateTimeImmutable())
			->setUpdatedBy($this->currentActor());

		foreach ($removedMaterials as $removedMaterial) {
			if ($removedMaterial instanceof ProductionOrderMaterial) {
				$this->entityManager->remove($removedMaterial);
			}
		}

		foreach ($order->getMaterials() as $material) {
			$this->assertOrderMaterialCanBeSaved($order, $material);
			$material->setProductionOrder($order);
			$this->entityManager->persist($material);
		}

		$this->save($order);
	}

	public function plan(ProductionOrder $order): void
	{
		$this->entityManager->wrapInTransaction(function () use ($order): void {
			$this->concurrencyGuard->lock($order);
			$this->statusTransitionService->apply($order, 'plan', $this->transitionContext());
			$this->touchAndSaveOrder($order);
		});
	}

	public function reserveMaterials(ProductionOrder $order): void
	{
		$this->entityManager->wrapInTransaction(function () use ($order): void {
			$this->concurrencyGuard->lock($order);
			$this->statusTransitionService->apply($order, 'reserve_materials', $this->transitionContext());

			foreach ($order->getMaterials() as $material) {
				$this->warehouseStockService->reserve($order->getWarehouse(), $material->getMaterial(), $material->getPlannedQuantity());
			}

			$this->touchAndSaveOrder($order);
		});
	}

	public function start(ProductionOrder $order): void
	{
		$this->entityManager->wrapInTransaction(function () use ($order): void {
			$this->concurrencyGuard->lock($order);
			$this->statusTransitionService->apply($order, 'start', $this->transitionContext());
			$this->touchAndSaveOrder($order);
		});
	}

	public function complete(ProductionOrder $order, ?string $completedQuantity = null): InventoryDocument
	{
		$quantity = $completedQuantity ?: $order->getPlannedQuantity();

		return $this->entityManager->wrapInTransaction(function () use ($order, $quantity): InventoryDocument {
			$this->concurrencyGuard->lock($order);
			$context = $this->transitionContext();
			if (!$this->statusTransitionService->can($order, 'complete', $context)) {
				throw new RuntimeException('Production order cannot be completed from current status.');
			}

			$document = $this->productionOrderFactory->createProductionDocument($order, $quantity, $this->currentActor());
			$this->entityManager->persist($document);

			$this->releaseReservedMaterials($order);
			$this->inventoryPostingService->post($document);
			$this->statusTransitionService->apply($order, 'complete', $context);
			$this->entityManager->persist($order);
			$this->entityManager->flush();
			$this->stockReplenishmentQueue->enqueueProductionOrder((int) $order->getId());
			$this->touchAndSaveOrder($order);

			return $document;
		});
	}

	public function cancel(ProductionOrder $order): void
	{
		$this->entityManager->wrapInTransaction(function () use ($order): void {
			$this->concurrencyGuard->lock($order);
			if (in_array($order->getStatus(), [
				ProductionOrderStatus::MATERIALS_RESERVED,
				ProductionOrderStatus::IN_PROGRESS,
			], true)) {
				$this->releaseReservedMaterials($order);
			}

			$this->statusTransitionService->apply($order, 'cancel', $this->transitionContext());
			$this->touchAndSaveOrder($order);
		});
	}

	public function getRepository(): ProductionOrderRepository
	{
		return $this->entityManager->getRepository(ProductionOrder::class);
	}

	public function getRecipeRepository(): ProductionRecipeRepository
	{
		return $this->entityManager->getRepository(ProductionRecipe::class);
	}

	public function getEntityManager(): EntityManagerInterface
	{
		return $this->entityManager;
	}

	private function releaseReservedMaterials(ProductionOrder $order): void
	{
		foreach ($order->getMaterials() as $material) {
			$warehouse = $order->getWarehouse();
			$product = $material->getMaterial();
			if (!$warehouse || !$product) {
				throw new RuntimeException('Production order material reservation cannot be released without warehouse and product.');
			}

			$warehouseStock = $this->warehouseStockRepository->findOneByProductAndWarehouse($product, $warehouse);
			if (!$warehouseStock instanceof WarehouseStock) {
				throw new StockOperationException(sprintf('Reserved material "%s" has no warehouse stock.', $product->getName()));
			}

			$quantityToRelease = min((float) $warehouseStock->getReservedQuantity(), (float) $material->getPlannedQuantity());
			if ($quantityToRelease <= 0) {
				continue;
			}

			$this->warehouseStockService->release($warehouse, $product, $this->formatQuantity($quantityToRelease));
		}
	}

	private function touchAndSaveOrder(ProductionOrder $order): void
	{
		$order
			->setUpdatedAt(new DateTimeImmutable())
			->setUpdatedBy($this->currentActor());

		$this->save($order);
	}

	private function assertRecipeCanBeSaved(ProductionRecipe $recipe): void
	{
		$store = $recipe->getStore();
		$product = $recipe->getProduct();

		if (!$store || !$product || $product->getStore()?->getId() !== $store->getId()) {
			throw new RuntimeException('Production recipe product must belong to recipe store.');
		}

		if (!$product->isCanBeManufactured()) {
			throw new RuntimeException('Production recipe product must be manufacturable.');
		}

		if ($product->getProductKind() === ProductKindEnum::SERVICE) {
			throw new RuntimeException('Service product cannot be production output.');
		}

		if ($recipe->getItems()->isEmpty()) {
			throw new RuntimeException('Production recipe must contain at least one material.');
		}
	}

	private function assertRecipeItemCanBeSaved(ProductionRecipe $recipe, ProductionRecipeItem $item): void
	{
		$material = $item->getMaterial();
		if (!$material || $material->getStore()?->getId() !== $recipe->getStore()?->getId()) {
			throw new RuntimeException('Production recipe material must belong to recipe store.');
		}

		if ($material->getProductKind() === ProductKindEnum::SERVICE) {
			throw new RuntimeException('Service product cannot be production material.');
		}

		if ((float) $item->getQuantity() <= 0) {
			throw new RuntimeException('Production recipe material quantity must be greater than zero.');
		}
	}

	private function assertOrderCanBeSaved(ProductionOrder $order): void
	{
		$store = $order->getStore();
		$product = $order->getProduct();

		if (!$store || !$product || $product->getStore()?->getId() !== $store->getId()) {
			throw new RuntimeException('Production order product must belong to order store.');
		}

		if ((float) $order->getPlannedQuantity() <= 0) {
			throw new RuntimeException('Production order planned quantity must be greater than zero.');
		}

		if ($product->getProductKind() === ProductKindEnum::SERVICE) {
			throw new RuntimeException('Service product cannot be production output.');
		}
	}

	private function assertOrderMaterialCanBeSaved(ProductionOrder $order, ProductionOrderMaterial $material): void
	{
		$product = $material->getMaterial();
		if (!$product || $product->getStore()?->getId() !== $order->getStore()?->getId()) {
			throw new RuntimeException('Production order material must belong to order store.');
		}

		if ($product->getProductKind() === ProductKindEnum::SERVICE) {
			throw new RuntimeException('Service product cannot be production material.');
		}

		if ((float) $material->getPlannedQuantity() <= 0) {
			throw new RuntimeException('Production order material quantity must be greater than zero.');
		}
	}

	private function transitionContext(): TransitionContext
	{
		$user = $this->currentActor();

		return $user instanceof User
			? TransitionContext::manual($user)
			: TransitionContext::system();
	}

	private function currentActor(): ?User
	{
		$user = $this->userManager->getCurrentUser();

		return $user instanceof User ? $user : null;
	}

	private function formatQuantity(float $quantity): string
	{
		return number_format($quantity, 4, '.', '');
	}
}
