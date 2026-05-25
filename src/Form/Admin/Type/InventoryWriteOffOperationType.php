<?php

namespace App\Form\Admin\Type;

use App\Dto\Admin\Inventory\InventoryWriteOffOperation;
use App\Entity\InventoryReason;
use App\Entity\Product;
use App\Entity\Store;
use App\Entity\Warehouse;
use App\Enum\ActiveStatusEnum;
use App\Enum\InventoryReasonType;
use App\Repository\InventoryReasonRepository;
use App\Repository\ProductRepository;
use App\Repository\WarehouseRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\GreaterThan;
use Symfony\Component\Validator\Constraints\NotBlank;

class InventoryWriteOffOperationType extends AbstractType
{
	public function buildForm(FormBuilderInterface $builder, array $options): void
	{
		$builder
			->add('product', EntityType::class, [
				'class' => Product::class,
				'query_builder' => fn (ProductRepository $repository) => $options['store'] instanceof Store
					? $repository->findAvailableByStoreQB($options['store'])->orderBy('product.name', 'ASC')
					: $repository->createQueryBuilder('product')->andWhere('1 = 0'),
				'choice_label' => fn (Product $product): string => sprintf('%s (%s)', $product->getName(), $product->getCode()),
				'placeholder' => 'Select product',
				'attr' => ['class' => 'select2'],
				'constraints' => [new NotBlank(['message' => 'Select product.'])],
			])
			->add('warehouse', EntityType::class, [
				'class' => Warehouse::class,
				'query_builder' => fn (WarehouseRepository $repository) => $options['store'] instanceof Store
					? $repository->findAvailableByStoreQB($options['store'])->orderBy('warehouse.name', 'ASC')
					: $repository->createQueryBuilder('warehouse')->andWhere('1 = 0'),
				'choice_label' => 'name',
				'placeholder' => 'Select warehouse',
				'attr' => ['class' => 'select2'],
				'constraints' => [new NotBlank(['message' => 'Select warehouse.'])],
			])
			->add('reason', EntityType::class, [
				'class' => InventoryReason::class,
				'query_builder' => fn (InventoryReasonRepository $repository) => $this->reasonQueryBuilder($repository, $options, [
					InventoryReasonType::WRITE_OFF,
					InventoryReasonType::DAMAGE,
					InventoryReasonType::PRODUCTION_LOSS,
					InventoryReasonType::OTHER,
				]),
				'choice_label' => fn (InventoryReason $reason): string => sprintf('%s (%s)', $reason->getName(), $reason->getType()->value),
				'placeholder' => 'Select reason',
				'attr' => ['class' => 'select2'],
				'help' => 'Required for write-off documents.',
				'constraints' => [new NotBlank(['message' => 'Select reason.'])],
			])
			->add('quantity', NumberType::class, [
				'html5' => true,
				'scale' => 4,
				'attr' => ['min' => '0.0001', 'step' => '0.0001'],
				'constraints' => [new GreaterThan(['value' => 0, 'message' => 'Quantity must be greater than zero.'])],
			])
			->add('number', TextType::class, [
				'required' => false,
				'help' => 'Leave empty to generate automatically.',
			]);
	}

	/**
	 * @param list<InventoryReasonType> $types
	 */
	private function reasonQueryBuilder(InventoryReasonRepository $repository, array $options, array $types)
	{
		return $options['store'] instanceof Store
			? $repository->findAvailableByStoreQB($options['store'])
				->andWhere('inventoryReason.status = :status')
				->andWhere('inventoryReason.type IN (:types)')
				->setParameter('status', ActiveStatusEnum::ACTIVE)
				->setParameter('types', $types)
				->orderBy('inventoryReason.type', 'ASC')
				->addOrderBy('inventoryReason.name', 'ASC')
			: $repository->createQueryBuilder('inventoryReason')->andWhere('1 = 0');
	}

	public function configureOptions(OptionsResolver $resolver): void
	{
		$resolver->setDefaults([
			'data_class' => InventoryWriteOffOperation::class,
			'store' => null,
		]);

		$resolver->setAllowedTypes('store', ['null', Store::class]);
	}
}
