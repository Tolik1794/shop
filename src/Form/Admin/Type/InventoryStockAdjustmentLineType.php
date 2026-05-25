<?php

namespace App\Form\Admin\Type;

use App\Dto\Admin\Inventory\InventoryStockAdjustmentLine;
use App\Entity\Product;
use App\Entity\Store;
use App\Entity\Warehouse;
use App\Enum\InventoryDirection;
use App\Repository\ProductRepository;
use App\Repository\WarehouseRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\GreaterThan;
use Symfony\Component\Validator\Constraints\GreaterThanOrEqual;
use Symfony\Component\Validator\Constraints\NotBlank;

class InventoryStockAdjustmentLineType extends AbstractType
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
			->add('direction', EnumType::class, [
				'class' => InventoryDirection::class,
				'choice_label' => fn (InventoryDirection $direction): string => $direction->value,
				'constraints' => [new NotBlank(['message' => 'Select direction.'])],
			])
			->add('quantity', NumberType::class, [
				'html5' => true,
				'scale' => 4,
				'attr' => ['min' => '0.0001', 'step' => '0.0001'],
				'constraints' => [new GreaterThan(['value' => 0, 'message' => 'Quantity must be greater than zero.'])],
			])
			->add('unitCost', NumberType::class, [
				'required' => false,
				'html5' => true,
				'scale' => 4,
				'attr' => ['min' => 0, 'step' => '0.0001'],
				'help' => 'Used as incoming cost for positive adjustments. Leave empty for outgoing adjustments.',
				'constraints' => [new GreaterThanOrEqual(['value' => 0, 'message' => 'Unit cost cannot be negative.'])],
			]);
	}

	public function configureOptions(OptionsResolver $resolver): void
	{
		$resolver->setDefaults([
			'data_class' => InventoryStockAdjustmentLine::class,
			'store' => null,
		]);

		$resolver->setAllowedTypes('store', ['null', Store::class]);
	}
}
