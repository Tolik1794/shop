<?php

namespace App\Form\Admin\Type;

use App\Entity\Product;
use App\Entity\ProductionOrderMaterial;
use App\Entity\Store;
use App\Repository\ProductRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\GreaterThan;
use Symfony\Component\Validator\Constraints\GreaterThanOrEqual;
use Symfony\Component\Validator\Constraints\NotBlank;

class ProductionOrderMaterialType extends AbstractType
{
	public function buildForm(FormBuilderInterface $builder, array $options): void
	{
		$builder
			->add('material', EntityType::class, [
				'class' => Product::class,
				'query_builder' => fn (ProductRepository $repository) => $options['store'] instanceof Store
					? $repository->findProductionMaterialsByStoreQB($options['store'])->orderBy('product.name', 'ASC')
					: $repository->createQueryBuilder('product')->andWhere('1 = 0'),
				'choice_label' => fn (Product $product): string => sprintf('%s (%s)', $product->getName(), $product->getCode()),
				'placeholder' => 'Select material',
				'attr' => ['class' => 'select2'],
				'constraints' => [new NotBlank(['message' => 'Select material.'])],
			])
			->add('plannedQuantity', NumberType::class, [
				'html5' => true,
				'scale' => 4,
				'attr' => [
					'min' => '0.0001',
					'step' => '0.0001',
				],
				'constraints' => [
					new GreaterThan([
						'value' => 0,
						'message' => 'Planned quantity must be greater than zero.',
					]),
				],
			])
			->add('wastePercent', NumberType::class, [
				'required' => false,
				'html5' => true,
				'scale' => 2,
				'attr' => [
					'min' => 0,
					'step' => '0.01',
				],
				'constraints' => [
					new GreaterThanOrEqual([
						'value' => 0,
						'message' => 'Waste percent cannot be negative.',
					]),
				],
			])
			->add('comment', TextareaType::class, [
				'required' => false,
			]);
	}

	public function configureOptions(OptionsResolver $resolver): void
	{
		$resolver->setDefaults([
			'data_class' => ProductionOrderMaterial::class,
			'store' => null,
		]);

		$resolver->setAllowedTypes('store', ['null', Store::class]);
	}
}
