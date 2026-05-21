<?php

namespace App\Form\Admin\Type;

use App\Entity\Product;
use App\Entity\ProductionRecipe;
use App\Entity\Store;
use App\Enum\ActiveStatusEnum;
use App\Repository\ProductRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ProductionRecipeType extends AbstractType
{
	public function buildForm(FormBuilderInterface $builder, array $options): void
	{
		$builder
			->add('name')
			->add('product', EntityType::class, [
				'class' => Product::class,
				'query_builder' => fn (ProductRepository $repository) => $options['store'] instanceof Store
					? $repository->findManufacturableByStoreQB($options['store'])->orderBy('product.name', 'ASC')
					: $repository->createQueryBuilder('product')->andWhere('1 = 0'),
				'choice_label' => fn (Product $product): string => sprintf('%s (%s)', $product->getName(), $product->getCode()),
				'placeholder' => 'Select product',
				'attr' => ['class' => 'select2'],
			])
			->add('isDefault')
			->add('status', EnumType::class, [
				'class' => ActiveStatusEnum::class,
				'choices' => ActiveStatusEnum::userSelectableCases(),
				'choice_label' => fn (ActiveStatusEnum $choice): string => $choice->value,
			])
			->add('items', CollectionType::class, [
				'entry_type' => ProductionRecipeItemType::class,
				'entry_options' => [
					'label' => false,
					'store' => $options['store'],
					'material_ajax_url' => $options['material_ajax_url'],
				],
				'allow_add' => true,
				'allow_delete' => true,
				'by_reference' => false,
				'label' => false,
			]);
	}

	public function configureOptions(OptionsResolver $resolver): void
	{
		$resolver->setDefaults([
			'data_class' => ProductionRecipe::class,
			'store' => null,
			'material_ajax_url' => null,
		]);

		$resolver->setAllowedTypes('store', ['null', Store::class]);
		$resolver->setAllowedTypes('material_ajax_url', ['null', 'string']);
	}
}
